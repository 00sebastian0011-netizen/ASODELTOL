<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Privacy API provider for session, attendance and append-only history data.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_asstaff_session', [
            'trainerid' => 'privacy:metadata:session:trainerid',
            'createdby' => 'privacy:metadata:session:createdby',
            'validatedby' => 'privacy:metadata:session:validatedby',
            'evidenceref' => 'privacy:metadata:session:evidenceref',
        ], 'privacy:metadata:session');
        $collection->add_database_table('local_asstaff_attendance', [
            'studentid' => 'privacy:metadata:attendance:studentid',
            'reportedby' => 'privacy:metadata:attendance:reportedby',
            'status' => 'privacy:metadata:attendance:status',
        ], 'privacy:metadata:attendance');
        $collection->add_database_table('local_asstaff_history', [
            'actorid' => 'privacy:metadata:history:actorid',
            'subjectuserid' => 'privacy:metadata:history:subjectuserid',
            'payload' => 'privacy:metadata:history:payload',
        ], 'privacy:metadata:history');
        return $collection;
    }

    /**
     * Get course contexts containing data associated with a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {local_asstaff_session} s ON s.courseid = ctx.instanceid
             LEFT JOIN {local_asstaff_attendance} a ON a.sessionid = s.id
             LEFT JOIN {local_asstaff_history} h ON h.sessionid = s.id
                 WHERE ctx.contextlevel = :contextlevel
                   AND (s.trainerid = :trainerid
                    OR s.createdby = :createdby
                    OR s.validatedby = :validatedby
                    OR a.studentid = :studentid
                    OR a.reportedby = :reportedby
                    OR h.actorid = :actorid
                    OR h.subjectuserid = :subjectuserid)";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'trainerid' => $userid,
            'createdby' => $userid,
            'validatedby' => $userid,
            'studentid' => $userid,
            'reportedby' => $userid,
            'actorid' => $userid,
            'subjectuserid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export data associated with the approved user and contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $sessions = $DB->get_records_select('local_asstaff_session',
                'courseid = :courseid AND (trainerid = :userid1 OR createdby = :userid2 OR validatedby = :userid3)',
                [
                    'courseid' => $context->instanceid,
                    'userid1' => $userid,
                    'userid2' => $userid,
                    'userid3' => $userid,
                ],
                'id ASC'
            );
            $attendance = $DB->get_records_sql(
                "SELECT a.*
                   FROM {local_asstaff_attendance} a
                   JOIN {local_asstaff_session} s ON s.id = a.sessionid
                  WHERE s.courseid = :courseid
                    AND (a.studentid = :userid1 OR a.reportedby = :userid2)
               ORDER BY a.id",
                ['courseid' => $context->instanceid, 'userid1' => $userid, 'userid2' => $userid]
            );
            $history = $DB->get_records_sql(
                "SELECT h.*
                   FROM {local_asstaff_history} h
                   JOIN {local_asstaff_session} s ON s.id = h.sessionid
                  WHERE s.courseid = :courseid
                    AND (h.actorid = :userid1 OR h.subjectuserid = :userid2)
               ORDER BY h.id",
                ['courseid' => $context->instanceid, 'userid1' => $userid, 'userid2' => $userid]
            );
            $export = (object) [
                'sessions' => array_values(array_map([self::class, 'prepare_export_record'], $sessions)),
                'attendance' => array_values(array_map([self::class, 'prepare_export_record'], $attendance)),
                'history' => array_values(array_map([self::class, 'prepare_export_record'], $history)),
            ];
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_asodeltolstaff')],
                $export
            );
        }
    }

    /**
     * Delete all plugin records in an approved course context.
     *
     * @param context $context Context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }
        $sessions = $DB->get_records('local_asstaff_session', ['courseid' => $context->instanceid], '', 'id');
        if ($sessions) {
            $transaction = $DB->start_delegated_transaction();
            $sessionids = array_keys($sessions);
            $DB->delete_records_list('local_asstaff_history', 'sessionid', $sessionids);
            $DB->delete_records_list('local_asstaff_attendance', 'sessionid', $sessionids);
            $DB->delete_records_list('local_asstaff_session', 'id', $sessionids);
            $transaction->allow_commit();
        }
    }

    /**
     * Delete or anonymise one approved user's data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                self::delete_user_in_course($userid, (int) $context->instanceid);
            }
        }
    }

    /**
     * Add user ids whose data exists in a course context.
     *
     * @param userlist $userlist User list being populated.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $sql = "SELECT s.trainerid AS userid FROM {local_asstaff_session} s WHERE s.courseid = :course1
                UNION SELECT s.createdby FROM {local_asstaff_session} s WHERE s.courseid = :course2
                UNION SELECT s.validatedby FROM {local_asstaff_session} s WHERE s.courseid = :course3
                UNION SELECT a.studentid FROM {local_asstaff_attendance} a
                  JOIN {local_asstaff_session} s ON s.id = a.sessionid WHERE s.courseid = :course4
                UNION SELECT a.reportedby FROM {local_asstaff_attendance} a
                  JOIN {local_asstaff_session} s ON s.id = a.sessionid WHERE s.courseid = :course5
                UNION SELECT h.actorid FROM {local_asstaff_history} h
                  JOIN {local_asstaff_session} s ON s.id = h.sessionid WHERE s.courseid = :course6
                UNION SELECT h.subjectuserid FROM {local_asstaff_history} h
                  JOIN {local_asstaff_session} s ON s.id = h.sessionid WHERE s.courseid = :course7";
        $userlist->add_from_sql('userid', $sql, [
            'course1' => $context->instanceid,
            'course2' => $context->instanceid,
            'course3' => $context->instanceid,
            'course4' => $context->instanceid,
            'course5' => $context->instanceid,
            'course6' => $context->instanceid,
            'course7' => $context->instanceid,
        ]);
    }

    /**
     * Delete or anonymise several approved users in one course.
     *
     * @param approved_userlist $userlist Approved list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_in_course((int) $userid, (int) $context->instanceid);
        }
    }

    /**
     * Prepare dates for a privacy export.
     *
     * @param stdClass $record Database record.
     * @return stdClass
     */
    private static function prepare_export_record(stdClass $record): stdClass {
        foreach (['timecreated', 'timemodified', 'timevalidated', 'scheduledstart', 'scheduledend',
                'reportedstart', 'reportedend'] as $field) {
            if (!empty($record->{$field})) {
                $record->{$field} = transform::datetime((int) $record->{$field});
            }
        }
        return $record;
    }

    /**
     * Delete subject attendance and anonymise actor references while preserving other users' audit trail.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     */
    private static function delete_user_in_course(int $userid, int $courseid): void {
        global $DB;

        $sessionids = array_keys($DB->get_records('local_asstaff_session', ['courseid' => $courseid], '', 'id'));
        if (!$sessionids) {
            return;
        }
        [$insql, $sessionparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'session');
        $params = $sessionparams;
        $params['userid'] = $userid;
        $transaction = $DB->start_delegated_transaction();

        $historyrecords = $DB->get_records_select(
            'local_asstaff_history',
            "sessionid {$insql}",
            $sessionparams,
            'id',
            'id, payload'
        );
        foreach ($historyrecords as $history) {
            $payload = json_decode($history->payload, true, 512, JSON_THROW_ON_ERROR);
            $changed = false;
            $payload = self::anonymise_history_payload($payload, $userid, $changed);
            if ($changed) {
                $DB->set_field(
                    'local_asstaff_history',
                    'payload',
                    json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ['id' => $history->id]
                );
            }
        }
        $DB->delete_records_select(
            'local_asstaff_attendance',
            "sessionid {$insql} AND studentid = :userid",
            $params
        );
        $DB->set_field_select(
            'local_asstaff_attendance',
            'reportedby',
            0,
            "sessionid {$insql} AND reportedby = :userid",
            $params
        );
        $DB->set_field_select(
            'local_asstaff_history',
            'actorid',
            0,
            "sessionid {$insql} AND actorid = :userid",
            $params
        );
        $DB->set_field_select(
            'local_asstaff_history',
            'subjectuserid',
            null,
            "sessionid {$insql} AND subjectuserid = :userid",
            $params
        );
        $DB->set_field_select(
            'local_asstaff_session',
            'trainerid',
            0,
            'courseid = :courseid AND trainerid = :userid',
            ['courseid' => $courseid, 'userid' => $userid]
        );
        $DB->set_field_select(
            'local_asstaff_session',
            'createdby',
            0,
            'courseid = :courseid AND createdby = :userid',
            ['courseid' => $courseid, 'userid' => $userid]
        );
        $DB->set_field_select(
            'local_asstaff_session',
            'validatedby',
            null,
            'courseid = :courseid AND validatedby = :userid',
            ['courseid' => $courseid, 'userid' => $userid]
        );
        $transaction->allow_commit();
    }

    /**
     * Remove a user's identifiers embedded in structured history snapshots.
     *
     * @param mixed $value Decoded JSON value.
     * @param int $userid User id to anonymise.
     * @param bool $changed Whether a value was changed.
     * @return mixed
     */
    private static function anonymise_history_payload(mixed $value, int $userid, bool &$changed): mixed {
        if (!is_array($value)) {
            return $value;
        }
        $userfields = ['trainerid', 'validatedby', 'studentid', 'reportedby'];
        foreach ($value as $key => $item) {
            if (in_array((string) $key, $userfields, true)
                    && is_int($item)
                    && $item === $userid) {
                $value[$key] = null;
                $changed = true;
                continue;
            }
            $value[$key] = self::anonymise_history_payload($item, $userid, $changed);
        }
        return $value;
    }
}
