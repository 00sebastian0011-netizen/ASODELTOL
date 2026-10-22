<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff;

use context_course;
use core_user;
use moodle_exception;
use stdClass;

/**
 * Domain service for academic session and provisional attendance records.
 *
 * All writes derive the actor from the authenticated Moodle session. Public
 * methods centralise capability, group and consistency checks so that pages,
 * CLI acceptance tests and future integrations share the same controls.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /** @var string Pending administrative review. */
    public const VALIDATION_PENDING = 'pending';

    /** @var string Accepted technical test scenario. */
    public const VALIDATION_VALIDATED = 'validated';

    /** @var string Rejected technical test scenario. */
    public const VALIDATION_REJECTED = 'rejected';

    /** @var list<string> Provisional attendance states, not institutional policy. */
    public const ATTENDANCE_STATUSES = [
        'reported_present',
        'reported_absent',
        'reported_late',
        'reported_excused',
    ];

    /**
     * Return the course context for a session record.
     *
     * @param stdClass $session Session record.
     * @return context_course
     */
    public static function get_context(stdClass $session): context_course {
        return context_course::instance((int) $session->courseid);
    }

    /**
     * Get one session record.
     *
     * @param int $id Session id.
     * @return stdClass
     */
    public static function get_session(int $id): stdClass {
        global $DB;

        return $DB->get_record('local_asstaff_session', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Whether a user has transversal access in a course.
     *
     * @param context_course $context Course context.
     * @param int|null $userid User id, or current user.
     * @return bool
     */
    public static function can_view_all(context_course $context, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        return is_siteadmin($userid) || has_capability('local/asodeltolstaff:viewall', $context, $userid);
    }

    /**
     * Require access to a specific session without bypassing group isolation.
     *
     * @param stdClass $session Session record.
     * @param int|null $userid User id, or current user.
     */
    public static function require_session_view(stdClass $session, ?int $userid = null): void {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        $context = self::get_context($session);
        require_capability('local/asodeltolstaff:viewsessions', $context, $userid);

        if (!self::can_view_all($context, $userid) && !groups_is_member((int) $session->groupid, $userid)) {
            throw new moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
        }
    }

    /**
     * Require permission to manage the session's assigned trainer/group pair.
     *
     * @param stdClass $session Session record.
     */
    public static function require_session_manage(stdClass $session): void {
        global $USER;

        $context = self::get_context($session);
        require_capability('local/asodeltolstaff:manageown', $context);
        if (self::can_view_all($context)) {
            return;
        }
        if ((int) $session->trainerid !== (int) $USER->id
                || !groups_is_member((int) $session->groupid, (int) $USER->id)) {
            throw new moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
        }
    }

    /**
     * Return groups visible to the current actor.
     *
     * @param int $courseid Course id.
     * @return array<int, stdClass>
     */
    public static function get_available_groups(int $courseid): array {
        global $USER;

        $context = context_course::instance($courseid);
        require_capability('local/asodeltolstaff:viewsessions', $context);
        $userid = self::can_view_all($context) ? 0 : (int) $USER->id;
        return groups_get_all_groups($courseid, $userid, 0, 'g.id,g.name,g.idnumber');
    }

    /**
     * Return trainers who may be assigned to the specified group.
     *
     * @param int $courseid Course id.
     * @param int $groupid Group id.
     * @return array<int, stdClass>
     */
    public static function get_group_trainers(int $courseid, int $groupid): array {
        $context = context_course::instance($courseid);
        return get_enrolled_users(
            $context,
            'local/asodeltolstaff:manageown',
            $groupid,
            'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
            'u.lastname,u.firstname'
        );
    }

    /**
     * Return students eligible for provisional attendance in a group.
     *
     * @param int $courseid Course id.
     * @param int $groupid Group id.
     * @return array<int, stdClass>
     */
    public static function get_group_students(int $courseid, int $groupid): array {
        $context = context_course::instance($courseid);
        return get_enrolled_users(
            $context,
            'local/asodeltolstaff:beattended',
            $groupid,
            'u.id,u.firstname,u.lastname,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename',
            'u.lastname,u.firstname'
        );
    }

    /**
     * Create or update a session through validated plugin data.
     *
     * @param stdClass $data Submitted values.
     * @return stdClass Saved record.
     */
    public static function save_session(stdClass $data): stdClass {
        global $DB, $USER;

        $course = get_course((int) $data->courseid);
        $context = context_course::instance((int) $course->id);
        require_capability('local/asodeltolstaff:manageown', $context);

        $existing = empty($data->id) ? null : self::get_session((int) $data->id);
        if ($existing && (int) $existing->courseid !== (int) $course->id) {
            throw new moodle_exception('invalidcourse');
        }
        if ($existing) {
            self::require_session_manage($existing);
        }

        $record = self::normalise_session($data, $existing);
        self::validate_course_relations($course, $record);
        self::require_manage_pair($context, (int) $record->groupid, (int) $record->trainerid);

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        if ($existing) {
            $record->id = (int) $existing->id;
            $record->timemodified = $now;
            $record->validationstatus = self::VALIDATION_PENDING;
            $record->validatedminutes = null;
            $record->validatedby = null;
            $record->timevalidated = null;
            $DB->update_record('local_asstaff_session', $record);
            $saved = self::get_session((int) $existing->id);
            self::add_history(
                $saved,
                'session',
                (int) $saved->id,
                (int) $saved->trainerid,
                'updated',
                ['before' => self::session_snapshot($existing), 'after' => self::session_snapshot($saved)],
                (string) ($data->reason ?? '')
            );
        } else {
            $record->createdby = (int) $USER->id;
            $record->validationstatus = self::VALIDATION_PENDING;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $record->id = $DB->insert_record('local_asstaff_session', $record);
            $saved = self::get_session((int) $record->id);
            self::add_history(
                $saved,
                'session',
                (int) $saved->id,
                (int) $saved->trainerid,
                'created',
                ['after' => self::session_snapshot($saved)],
                (string) ($data->reason ?? '')
            );
        }
        $transaction->allow_commit();
        return $saved;
    }

    /**
     * Save provisional attendance for students in the session group.
     *
     * @param int $sessionid Session id.
     * @param array<int, string> $statuses Status indexed by user id.
     * @param string $reason Reason for this declaration/correction.
     */
    public static function save_attendance(int $sessionid, array $statuses, string $reason): void {
        global $DB, $USER;

        $session = self::get_session($sessionid);
        self::require_session_manage($session);
        $students = self::get_group_students((int) $session->courseid, (int) $session->groupid);
        $transaction = $DB->start_delegated_transaction();
        foreach ($statuses as $studentid => $status) {
            $studentid = (int) $studentid;
            if (!isset($students[$studentid])) {
                throw new moodle_exception('invalidattendanceuser', 'local_asodeltolstaff');
            }
            if (!in_array($status, self::ATTENDANCE_STATUSES, true)) {
                throw new moodle_exception('invalidparameter');
            }
            $before = $DB->get_record('local_asstaff_attendance', [
                'sessionid' => $sessionid,
                'studentid' => $studentid,
            ]);
            $now = time();
            if ($before) {
                $record = (object) [
                    'id' => (int) $before->id,
                    'status' => $status,
                    'reportedby' => (int) $USER->id,
                    'timemodified' => $now,
                ];
                $DB->update_record('local_asstaff_attendance', $record);
                $action = 'attendance_updated';
            } else {
                $record = (object) [
                    'sessionid' => $sessionid,
                    'studentid' => $studentid,
                    'status' => $status,
                    'reportedby' => (int) $USER->id,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $record->id = $DB->insert_record('local_asstaff_attendance', $record);
                $action = 'attendance_created';
            }
            $after = $DB->get_record('local_asstaff_attendance', ['id' => $record->id], '*', MUST_EXIST);
            self::add_history($session, 'attendance', (int) $after->id, $studentid, $action, [
                'studentid' => $studentid,
                'before' => $before ? ['status' => $before->status, 'reportedby' => (int) $before->reportedby] : null,
                'after' => ['status' => $after->status, 'reportedby' => (int) $after->reportedby],
            ], $reason);
        }
        $transaction->allow_commit();
    }

    /**
     * Administratively validate or reject a reported session.
     *
     * @param int $sessionid Session id.
     * @param string $status Validated or rejected.
     * @param int|null $validatedminutes Accepted duration for a validated record.
     * @param string $reason Mandatory rationale.
     * @return stdClass Updated record.
     */
    public static function validate_session(
        int $sessionid,
        string $status,
        ?int $validatedminutes,
        string $reason
    ): stdClass {
        global $DB, $USER;

        $session = self::get_session($sessionid);
        $context = self::get_context($session);
        require_capability('local/asodeltolstaff:validate', $context);
        if (!in_array($status, [self::VALIDATION_VALIDATED, self::VALIDATION_REJECTED], true)) {
            throw new moodle_exception('invalidparameter');
        }
        if (trim($reason) === '') {
            throw new moodle_exception('missingparam', '', '', 'reason');
        }
        if ($status === self::VALIDATION_VALIDATED) {
            if (empty($session->reportedstart) || empty($session->reportedend)) {
                throw new moodle_exception('invalidvalidatedminutes', 'local_asodeltolstaff');
            }
            $reportedminutes = (int) floor(((int) $session->reportedend - (int) $session->reportedstart) / 60);
            if ($validatedminutes === null || $validatedminutes <= 0 || $validatedminutes > $reportedminutes) {
                throw new moodle_exception('invalidvalidatedminutes', 'local_asodeltolstaff');
            }
        } else {
            $validatedminutes = null;
        }

        $before = self::session_snapshot($session);
        $update = (object) [
            'id' => (int) $session->id,
            'validationstatus' => $status,
            'validatedminutes' => $validatedminutes,
            'validatedby' => (int) $USER->id,
            'timevalidated' => time(),
            'timemodified' => time(),
        ];
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('local_asstaff_session', $update);
        $saved = self::get_session($sessionid);
        self::add_history(
            $saved,
            'session',
            $sessionid,
            (int) $saved->trainerid,
            'validation_' . $status,
            [
                'before' => $before,
                'after' => self::session_snapshot($saved),
            ],
            $reason
        );
        $transaction->allow_commit();
        return $saved;
    }

    /**
     * Return sessions that a user may see in a course.
     *
     * @param int $courseid Course id.
     * @param int|null $userid User id, or current user.
     * @return array<int, stdClass>
     */
    public static function get_accessible_sessions(int $courseid, ?int $userid = null): array {
        global $DB, $USER;

        $userid = $userid ?? (int) $USER->id;
        $context = context_course::instance($courseid);
        require_capability('local/asodeltolstaff:viewsessions', $context, $userid);
        if (self::can_view_all($context, $userid)) {
            return $DB->get_records('local_asstaff_session', ['courseid' => $courseid], 'scheduledstart, id');
        }
        $groups = groups_get_all_groups($courseid, $userid, 0, 'g.id');
        if (!$groups) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'grp');
        $params['courseid'] = $courseid;
        return $DB->get_records_select(
            'local_asstaff_session',
            "courseid = :courseid AND groupid {$insql}",
            $params,
            'scheduledstart, id'
        );
    }

    /**
     * Return attendance visible to the current actor for one session.
     *
     * @param stdClass $session Session record.
     * @return array<int, stdClass>
     */
    public static function get_visible_attendance(stdClass $session): array {
        global $DB, $USER;

        self::require_session_view($session);
        $context = self::get_context($session);
        if (self::can_view_all($context)
                || has_capability('local/asodeltolstaff:manageown', $context)) {
            return $DB->get_records('local_asstaff_attendance', ['sessionid' => $session->id], 'studentid');
        }
        $record = $DB->get_record('local_asstaff_attendance', [
            'sessionid' => $session->id,
            'studentid' => (int) $USER->id,
        ]);
        return $record ? [(int) $record->id => $record] : [];
    }

    /**
     * Return append-only history after enforcing staff/admin access.
     *
     * @param stdClass $session Session record.
     * @return array<int, stdClass>
     */
    public static function get_history(stdClass $session): array {
        $context = self::get_context($session);
        self::require_session_view($session);
        if (!self::can_view_all($context)) {
            require_capability('local/asodeltolstaff:manageown', $context);
            self::require_session_manage($session);
        }
        return self::get_history_records((int) $session->id);
    }

    /**
     * Get history records. Callers must enforce access first.
     *
     * @param int $sessionid Session id.
     * @return array<int, stdClass>
     */
    public static function get_history_records(int $sessionid): array {
        global $DB;

        return $DB->get_records('local_asstaff_history', ['sessionid' => $sessionid], 'id ASC');
    }

    /**
     * Build flattened report rows for Moodle data format exporters.
     *
     * @param int $courseid Course id.
     * @return list<array<string, int|string|null>>
     */
    public static function get_report_rows(int $courseid): array {
        global $DB;

        $context = context_course::instance($courseid);
        require_capability('local/asodeltolstaff:export', $context);
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $rows = [];
        foreach (self::get_accessible_sessions($courseid) as $session) {
            $group = $DB->get_record('groups', ['id' => $session->groupid], 'id,name', MUST_EXIST);
            $sectioninfo = $modinfo->get_section_info((int) $session->sectionnum, MUST_EXIST);
            $trainer = core_user::get_user((int) $session->trainerid, '*', IGNORE_MISSING);
            $validator = empty($session->validatedby)
                ? null
                : core_user::get_user((int) $session->validatedby, '*', IGNORE_MISSING);
            $attendance = $DB->get_records('local_asstaff_attendance', ['sessionid' => $session->id], 'studentid');
            if (!$attendance) {
                $attendance = [(object) ['studentid' => 0, 'status' => '']];
            }
            foreach ($attendance as $entry) {
                $student = empty($entry->studentid)
                    ? null
                    : core_user::get_user((int) $entry->studentid, '*', IGNORE_MISSING);
                $rows[] = [
                    'sessioncode' => (string) $session->sessioncode,
                    'scheduleddate' => userdate((int) $session->scheduledstart, get_string('strftimedatetimeshort')),
                    'section' => 'UT' . (int) $session->sectionnum . ' — ' . get_section_name($course, $sectioninfo),
                    'group' => (string) $group->name,
                    'trainer' => $trainer ? fullname($trainer) : '',
                    'student' => $student ? fullname($student) : '',
                    'attendance' => (string) $entry->status,
                    'scheduledminutes' => self::duration_minutes(
                        (int) $session->scheduledstart,
                        (int) $session->scheduledend
                    ),
                    'reportedminutes' => self::nullable_duration_minutes(
                        $session->reportedstart,
                        $session->reportedend
                    ),
                    'validatedminutes' => $session->validatedminutes === null
                        ? null
                        : (int) $session->validatedminutes,
                    'validationstatus' => (string) $session->validationstatus,
                    'validator' => $validator ? fullname($validator) : '',
                ];
            }
        }
        return $rows;
    }

    /**
     * Return integer duration in minutes.
     *
     * @param int $start Start timestamp.
     * @param int $end End timestamp.
     * @return int
     */
    public static function duration_minutes(int $start, int $end): int {
        return (int) floor(($end - $start) / 60);
    }

    /**
     * Return duration or null for an unreported session.
     *
     * @param int|null $start Start timestamp.
     * @param int|null $end End timestamp.
     * @return int|null
     */
    private static function nullable_duration_minutes(?int $start, ?int $end): ?int {
        if (!$start || !$end) {
            return null;
        }
        return self::duration_minutes($start, $end);
    }

    /**
     * Normalise and validate scalar session input.
     *
     * @param stdClass $data Submitted values.
     * @param stdClass|null $existing Existing record.
     * @return stdClass
     */
    private static function normalise_session(stdClass $data, ?stdClass $existing): stdClass {
        $record = (object) [
            'courseid' => (int) $data->courseid,
            'sectionnum' => (int) $data->sectionnum,
            'groupid' => (int) $data->groupid,
            'trainerid' => (int) $data->trainerid,
            'sessioncode' => clean_param((string) $data->sessioncode, PARAM_ALPHANUMEXT),
            'scheduledstart' => (int) $data->scheduledstart,
            'scheduledend' => (int) $data->scheduledend,
            'reportedstart' => empty($data->reportedstart) ? null : (int) $data->reportedstart,
            'reportedend' => empty($data->reportedend) ? null : (int) $data->reportedend,
            'evidenceref' => trim((string) ($data->evidenceref ?? '')),
        ];
        if ($record->sessioncode === '' || $record->sessioncode !== (string) $data->sessioncode) {
            throw new moodle_exception('invalidparameter');
        }
        if ($record->scheduledstart <= 0 || $record->scheduledend <= $record->scheduledstart) {
            throw new moodle_exception('invalidscheduledperiod', 'local_asodeltolstaff');
        }
        if (($record->reportedstart === null) !== ($record->reportedend === null)
                || ($record->reportedstart !== null && $record->reportedend <= $record->reportedstart)) {
            throw new moodle_exception('invalidreportedperiod', 'local_asodeltolstaff');
        }
        if ($record->evidenceref !== '') {
            $url = filter_var($record->evidenceref, FILTER_VALIDATE_URL);
            $parts = parse_url($record->evidenceref);
            if ($url === false
                    || !is_array($parts)
                    || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                    || empty($parts['host'])
                    || isset($parts['user'])
                    || isset($parts['pass'])) {
                throw new moodle_exception('invalidevidence', 'local_asodeltolstaff');
            }
        }
        if ($existing && (int) $existing->courseid !== $record->courseid) {
            throw new moodle_exception('invalidcourse');
        }
        return $record;
    }

    /**
     * Validate group, section and trainer relations using Moodle APIs.
     *
     * @param stdClass $course Course.
     * @param stdClass $record Session values.
     */
    private static function validate_course_relations(stdClass $course, stdClass $record): void {
        global $DB;

        $group = $DB->get_record('groups', ['id' => $record->groupid], '*', MUST_EXIST);
        if ((int) $group->courseid !== (int) $course->id) {
            throw new moodle_exception('invalidgroup');
        }
        $section = get_fast_modinfo($course)->get_section_info((int) $record->sectionnum, MUST_EXIST);
        if (!$section || (int) $section->section !== (int) $record->sectionnum) {
            throw new moodle_exception('sectionnotexist');
        }
        $trainer = core_user::get_user((int) $record->trainerid, '*', MUST_EXIST);
        if (!is_enrolled(context_course::instance((int) $course->id), $trainer, '', true)) {
            throw new moodle_exception('notenrolled', 'enrol');
        }
    }

    /**
     * Enforce trainer/group ownership on a proposed pair.
     *
     * @param context_course $context Course context.
     * @param int $groupid Group id.
     * @param int $trainerid Trainer id.
     */
    private static function require_manage_pair(context_course $context, int $groupid, int $trainerid): void {
        global $USER;

        if (self::can_view_all($context)) {
            if (!has_capability('local/asodeltolstaff:manageown', $context, $trainerid)
                    || !groups_is_member($groupid, $trainerid)) {
                throw new moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
            }
            return;
        }
        if ($trainerid !== (int) $USER->id || !groups_is_member($groupid, (int) $USER->id)) {
            throw new moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
        }
    }

    /**
     * Produce stable session values for the append-only history.
     *
     * @param stdClass $session Session record.
     * @return array<string, int|string|null>
     */
    private static function session_snapshot(stdClass $session): array {
        return [
            'sessioncode' => (string) $session->sessioncode,
            'sectionnum' => (int) $session->sectionnum,
            'groupid' => (int) $session->groupid,
            'trainerid' => (int) $session->trainerid,
            'scheduledstart' => (int) $session->scheduledstart,
            'scheduledend' => (int) $session->scheduledend,
            'reportedstart' => $session->reportedstart === null ? null : (int) $session->reportedstart,
            'reportedend' => $session->reportedend === null ? null : (int) $session->reportedend,
            'validatedminutes' => $session->validatedminutes === null ? null : (int) $session->validatedminutes,
            'validationstatus' => (string) $session->validationstatus,
            'evidenceref' => (string) $session->evidenceref,
            'validatedby' => $session->validatedby === null ? null : (int) $session->validatedby,
        ];
    }

    /**
     * Append a business history row and emit a Moodle event.
     *
     * @param stdClass $session Session record.
     * @param string $entitytype Entity type.
     * @param int $entityid Entity id.
     * @param int $subjectuserid User whose record the history entry concerns.
     * @param string $action Action.
     * @param array<string, mixed> $payload Before/after payload.
     * @param string $reason Optional reason.
     */
    private static function add_history(
        stdClass $session,
        string $entitytype,
        int $entityid,
        int $subjectuserid,
        string $action,
        array $payload,
        string $reason
    ): void {
        global $DB, $USER;

        $historyid = $DB->insert_record('local_asstaff_history', (object) [
            'sessionid' => (int) $session->id,
            'entitytype' => $entitytype,
            'entityid' => $entityid,
            'action' => $action,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'actorid' => (int) $USER->id,
            'subjectuserid' => $subjectuserid,
            'reason' => trim($reason),
            'timecreated' => time(),
        ]);

        $event = event\record_changed::create([
            'context' => self::get_context($session),
            'objectid' => $historyid,
            'relateduserid' => (int) $session->trainerid,
            'other' => [
                'sessionid' => (int) $session->id,
                'entitytype' => $entitytype,
                'action' => $action,
            ],
        ]);
        $event->trigger();
    }
}
