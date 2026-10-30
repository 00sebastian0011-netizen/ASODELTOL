<?php
namespace local_asodeltolstaff;

/** Explicit consultation slots stored through the native Calendar API. */
final class office_hours {
    public const EVENT_TYPE = 1;

    /** Create an explicit slot; never infer an institutional timetable. */
    public static function create(int $courseid, int $groupid, int $trainerid, int $start, int $end,
            string $url = '', string $evidenceref = ''): int {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $context = \context_course::instance($courseid);
        manager::require_session_manage((object) ['courseid' => $courseid, 'groupid' => $groupid, 'trainerid' => $trainerid]);
        $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid], '*', MUST_EXIST);
        if (!isset(manager::get_group_trainers($courseid, $groupid)[$trainerid])
                || !is_enrolled($context, $trainerid, '', true)
                || \core_user::get_user($trainerid, 'id,suspended', MUST_EXIST)->suspended) {
            throw new \moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
        }
        if ($start <= 0 || $end - $start < HOURSECS) {
            throw new \invalid_parameter_exception('Consultation must last at least 60 minutes');
        }
        if ($url !== '') {
            $parts = parse_url($url);
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
                    || isset($parts['user']) || isset($parts['pass'])) {
                throw new \invalid_parameter_exception('An HTTPS meeting URL without credentials is required');
            }
        }
        $evidenceparts = parse_url($evidenceref);
        if ($evidenceref === '' || !$evidenceparts || ($evidenceparts['scheme'] ?? '') !== 'https'
                || empty($evidenceparts['host']) || isset($evidenceparts['user']) || isset($evidenceparts['pass'])) {
            throw new \invalid_parameter_exception('Evidence must be an HTTPS URL without credentials');
        }
        $record = ['courseid' => $courseid, 'groupid' => $groupid, 'userid' => $trainerid,
            'component' => 'local_asodeltolstaff', 'eventtype' => 'group', 'type' => self::EVENT_TYPE,
            'timestart' => $start, 'timeduration' => $end - $start];
        $description = $url === '' ? ''
            : \html_writer::link(new \moodle_url($url), get_string('joinmeeting', 'local_asodeltolstaff'));
        if ($existing = $DB->get_record('event', $record)) {
            if ($existing->description !== $description) {
                throw new \invalid_parameter_exception('A different meeting URL is already registered for this slot');
            }
            $metadata = $DB->get_record('local_asstaff_officehour', ['eventid' => $existing->id], '*', MUST_EXIST);
            if ($metadata->evidenceref !== $evidenceref) {
                throw new \invalid_parameter_exception('A different evidence reference is already registered');
            }
            return (int) $existing->id;
        }
        $event = \calendar_event::create((object) ($record + [
            'name' => get_string('officehours', 'local_asodeltolstaff') . ' — ' . $group->name,
            'description' => $description,
            'format' => FORMAT_HTML, 'visible' => 1, 'modulename' => '', 'instance' => 0,
        ]));
        if (!$event) {
            throw new \moodle_exception('errorcreatingevent', 'error');
        }
        $DB->insert_record('local_asstaff_officehour', (object)['eventid'=>$event->id, 'courseid'=>$courseid,
            'groupid'=>$groupid, 'evidenceref'=>$evidenceref, 'createdby'=>$USER->id, 'timecreated'=>time()]);
        return (int) $event->id;
    }

    public static function get_visible(int $courseid, int $userid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $allgroups = has_capability('moodle/site:accessallgroups', $context, $userid);
        $groups = $allgroups ? [] : array_keys(groups_get_all_groups($courseid, $userid));
        if (!$allgroups && !$groups) { return []; }
        [$insql, $params] = $allgroups ? ['', []] : $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grp');
        $where = $allgroups ? 'o.courseid = :courseid' : "o.courseid = :courseid AND o.groupid $insql";
        $rows = $DB->get_records_sql("SELECT o.*, e.timestart, e.timeduration, e.name FROM {local_asstaff_officehour} o JOIN {event} e ON e.id=o.eventid WHERE $where ORDER BY e.timestart", ['courseid'=>$courseid] + $params);
        if (!has_capability('local/asodeltolstaff:viewevidence', $context, $userid)) {
            foreach ($rows as $row) { $row->evidenceref = ''; }
        }
        return $rows;
    }
}
