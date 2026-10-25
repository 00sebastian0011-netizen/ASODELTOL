<?php
namespace local_asodeltolstaff;

/** Actual site access events, course dates and explicit consultation slots. */
final class access_report {
    /** No synthetic pairing: separate rows preserve multiple-device ambiguity. */
    public static function rows(int $courseid): array {
        global $DB, $USER;
        $context = \context_course::instance($courseid);
        require_capability('local/asodeltolstaff:export', $context);
        $groups = manager::get_available_groups($courseid);
        $all = manager::can_view_all($context);
        if ($all) {
            require_capability('report/log:view', \context_system::instance());
        } else {
            require_capability('local/asodeltolstaff:manageown', $context);
            if (!$groups) {
                return [];
            }
        }
        $fields = 'u.id,' . implode(',', array_map(static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()));
        $trainers = get_enrolled_users($context, 'local/asodeltolstaff:manageown', $all ? 0 : array_keys($groups), $fields);
        if (!$all) {
            $trainers = isset($trainers[$USER->id]) ? [$USER->id => $trainers[$USER->id]] : [];
        }
        $course = get_course($courseid);
        $base = ['trainingactioncode' => (string) ($course->idnumber ?: $course->shortname),
            'trainingaction' => (string) $course->fullname, 'trainer' => '', 'group' => '', 'rowtype' => '',
            'loginutc' => '', 'logoututc' => '', 'coursestartutc' => self::utc((int) $course->startdate),
            'courseendutc' => self::utc((int) $course->enddate), 'officehoursstartutc' => '', 'officehoursendutc' => '',
            'sourcerecord' => ''];
        $rows = [array_replace($base, ['rowtype' => 'course_period', 'sourcerecord' => 'course:' . $courseid])];
        if (!$trainers) {
            return $rows;
        }
        // Logs belong to core logstore; no IP, session identifiers or passwords are exported.
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            throw new \invalid_parameter_exception('Standard log store unavailable');
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($trainers), SQL_PARAMS_NAMED, 'trainer');
        $params['login'] = '\\core\\event\\user_loggedin';
        $params['logout'] = '\\core\\event\\user_loggedout';
        $logs = $DB->get_records_select('logstore_standard_log',
            "userid $insql AND (eventname = :login OR eventname = :logout)", $params,
            'timecreated ASC, id ASC', 'id,userid,eventname,timecreated', 0, 10001);
        if (count($logs) > 10000) {
            throw new \invalid_parameter_exception('More than 10000 access events; use the native log report with date filters');
        }
        foreach ($logs as $log) {
            $login = $log->eventname === $params['login'];
            $rows[] = array_replace($base, ['trainer' => fullname($trainers[$log->userid]),
                'rowtype' => $login ? 'site_login' : 'site_logout',
                ($login ? 'loginutc' : 'logoututc') => self::utc((int) $log->timecreated),
                'sourcerecord' => 'logstore_standard_log:' . $log->id]);
        }
        if ($groups) {
            [$groupsql, $groupparams] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'grp');
            [$usersql, $userparams] = $DB->get_in_or_equal(array_keys($trainers), SQL_PARAMS_NAMED, 'usr');
            $params = $groupparams + $userparams + ['course' => $courseid, 'component' => 'local_asodeltolstaff',
                'type' => office_hours::EVENT_TYPE];
            $events = $DB->get_records_select('event',
                "courseid = :course AND component = :component AND type = :type AND groupid $groupsql AND userid $usersql",
                $params, 'timestart ASC, id ASC');
            foreach ($events as $event) {
                $rows[] = array_replace($base, ['trainer' => fullname($trainers[$event->userid]),
                    'group' => $groups[$event->groupid]->name, 'rowtype' => 'consultation_slot',
                    'officehoursstartutc' => self::utc((int) $event->timestart),
                    'officehoursendutc' => self::utc((int) $event->timestart + (int) $event->timeduration),
                    'sourcerecord' => 'event:' . $event->id]);
            }
        }
        return $rows;
    }

    private static function utc(int $timestamp): string {
        return $timestamp > 0 ? gmdate('Y-m-d\TH:i:s\Z', $timestamp) : '';
    }
}
