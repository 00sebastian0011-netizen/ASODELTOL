<?php
namespace local_asodeltolstaff;

/** Explicit consultation slots stored through the native Calendar API. */
final class office_hours {
    public const EVENT_TYPE = 1;

    /** Create an explicit slot; never infer an institutional timetable. */
    public static function create(int $courseid, int $groupid, int $trainerid, int $start, int $end, string $url = ''): int {
        global $CFG, $DB;
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
        $record = ['courseid' => $courseid, 'groupid' => $groupid, 'userid' => $trainerid,
            'component' => 'local_asodeltolstaff', 'eventtype' => 'group', 'type' => self::EVENT_TYPE,
            'timestart' => $start, 'timeduration' => $end - $start];
        $description = $url === '' ? ''
            : \html_writer::link(new \moodle_url($url), get_string('joinmeeting', 'local_asodeltolstaff'));
        if ($existing = $DB->get_record('event', $record)) {
            if ($existing->description !== $description) {
                throw new \invalid_parameter_exception('A different meeting URL is already registered for this slot');
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
        return (int) $event->id;
    }
}
