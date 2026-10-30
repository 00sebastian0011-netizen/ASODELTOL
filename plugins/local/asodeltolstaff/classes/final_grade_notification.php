<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff;

/**
 * Native course-completion notification enriched with the visible total grade.
 *
 * This service never defines a pass grade or completion criterion. It only
 * reports the total already calculated and exposed by Moodle after the native
 * course_completed event has been recorded.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class final_grade_notification {
    /** @var array<string, bool> Same-request guard, including PHPUnit message redirection. */
    private static array $sentthisrequest = [];

    /**
     * Send one final-grade notification for a verified completion record.
     *
     * @param \core\event\course_completed $event Native completion event.
     * @return bool True only when a notification was sent.
     */
    public static function send(\core\event\course_completed $event): bool {
        global $CFG, $DB;

        $courseid = (int) $event->courseid;
        $userid = (int) $event->relateduserid;
        $completionid = (int) $event->objectid;
        if ($courseid <= 0 || $userid <= 0 || $completionid <= 0) {
            return false;
        }

        // Never trust IDs carried only by an event: reconcile them with the
        // completed native record before reading or sending a grade.
        $completion = $DB->get_record('course_completions', ['id' => $completionid],
            'id, course, userid, timecompleted', IGNORE_MISSING);
        if (!$completion
                || (int) $completion->course !== $courseid
                || (int) $completion->userid !== $userid
                || empty($completion->timecompleted)) {
            return false;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_asodeltolstaff_final_grade');
        $lock = $lockfactory->get_lock("completion-{$completionid}", 5);
        if (!$lock) {
            // Another worker is processing this exact completion.
            return false;
        }

        try {
            $runtimekey = "{$courseid}:{$userid}:{$completionid}";
            if (isset(self::$sentthisrequest[$runtimekey])
                    || self::already_notified($courseid, $userid, $completionid)) {
                return false;
            }

            $course = $DB->get_record('course', ['id' => $courseid],
                'id, fullname, shortname, showgrades', IGNORE_MISSING);
            $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
            if (!$course || !$user || !empty($user->deleted) || !empty($user->suspended)
                    || empty($course->showgrades)) {
                return false;
            }

            $context = \context_course::instance($courseid);
            if (!is_enrolled($context, $userid, '', true)
                    || !has_capability('gradereport/user:view', $context, $userid)
                    || !has_capability('moodle/grade:view', $context, $userid)) {
                return false;
            }

            require_once($CFG->libdir . '/gradelib.php');
            require_once($CFG->dirroot . '/grade/querylib.php');
            $grade = grade_get_course_grade($userid, $courseid);
            if (!$grade || $grade->grade === false || $grade->grade === null
                    || !is_numeric($grade->grade)
                    || !isset($grade->item)
                    || !empty($grade->hidden)
                    || !empty($grade->item->hidden)
                    || !empty($grade->item->scaleid)
                    || !is_numeric($grade->item->grademin)
                    || !is_numeric($grade->item->grademax)) {
                return false;
            }

            $minimum = (float) $grade->item->grademin;
            $maximum = (float) $grade->item->grademax;
            if ($maximum <= $minimum) {
                return false;
            }
            $percentage = (((float) $grade->grade - $minimum) / ($maximum - $minimum)) * 100;

            $url = new \moodle_url('/grade/report/user/index.php', [
                'id' => $courseid,
                'userid' => $userid,
            ]);
            $previouslanguage = force_current_language($user->lang ?? '');
            try {
                $courseargs = ['context' => $context];
                $data = (object) [
                    'course' => format_string($course->fullname, true, $courseargs),
                    'grade' => $grade->str_long_grade,
                    'percentage' => format_float($percentage, 2),
                    'url' => $url->out(false),
                ];
                $subject = get_string('coursegradefinalizedsubject', 'local_asodeltolstaff', $data->course);
                $body = get_string('coursegradefinalizedbody', 'local_asodeltolstaff', $data);

                $message = new \core\message\message();
                $message->component = 'local_asodeltolstaff';
                $message->name = 'coursegradefinalized';
                $message->userfrom = \core_user::get_noreply_user();
                $message->userto = $user;
                $message->subject = $subject;
                $message->fullmessage = $body;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = nl2br(s($body));
                $message->smallmessage = get_string('coursegradefinalizedsmall',
                    'local_asodeltolstaff', $data->percentage);
                $message->notification = 1;
                $message->courseid = $courseid;
                $message->contexturl = $url->out(false);
                $message->contexturlname = get_string('viewgradesandprogress', 'local_asodeltolstaff');
                $message->customdata = ['completionid' => $completionid];

                if (!message_send($message)) {
                    return false;
                }
                self::$sentthisrequest[$runtimekey] = true;
                return true;
            } finally {
                force_current_language($previouslanguage);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Check the native notification store so retried events remain idempotent.
     *
     * @param int $courseid Course id.
     * @param int $userid Recipient id.
     * @param int $completionid Completion record id.
     * @return bool Whether this completion was already notified.
     */
    private static function already_notified(int $courseid, int $userid, int $completionid): bool {
        global $DB;

        $records = $DB->get_records('notifications', [
            'component' => 'local_asodeltolstaff',
            'eventtype' => 'coursegradefinalized',
            'useridto' => $userid,
        ], 'id DESC', 'id, customdata');
        foreach ($records as $record) {
            if (empty($record->customdata)) {
                continue;
            }
            $customdata = json_decode($record->customdata, true);
            if (is_array($customdata)
                    && (int) ($customdata['courseid'] ?? 0) === $courseid
                    && (int) ($customdata['completionid'] ?? 0) === $completionid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reset the request guard between isolated PHPUnit fixtures.
     */
    public static function reset_runtime_state_for_testing(): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('Runtime notification state can only be reset during PHPUnit.');
        }
        self::$sentthisrequest = [];
    }
}
