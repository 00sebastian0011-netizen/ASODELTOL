<?php
namespace local_asodeltolstaff;

/** Native group calendar, page and notification workflows. */
final class group_communication {
    public static function require_group(int $courseid, int $groupid): \stdClass {
        global $DB, $USER;
        $context = \context_course::instance($courseid);
        require_capability('local/asodeltolstaff:publishown', $context);
        \core_user::require_active_user($USER);
        $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid], '*', MUST_EXIST);
        if (!is_siteadmin() && (!is_enrolled($context, $USER, '', true) || !groups_is_member($groupid, $USER->id))) {
            throw new \moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
        }
        return $group;
    }

    public static function announce(int $courseid, int $groupid, int $section, string $title, string $body): int {
        global $USER;
        self::require_group($courseid, $groupid);
        $id = group_publisher::create($courseid, $groupid, $section, 'page', $title,
            get_string('groupannouncement', 'local_asodeltolstaff'), $body);
        $text = content_to_text(clean_text($body, FORMAT_HTML), FORMAT_HTML);
        $context = \context_course::instance($courseid);
        $users = get_enrolled_users($context, 'local/asodeltolstaff:beattended', $groupid, 'u.*', 'u.id', 0, 0, true);
        foreach ($users as $user) {
            if ($user->suspended || $user->deleted) {
                continue;
            }
            $message = new \core\message\message();
            $message->component = 'local_asodeltolstaff';
            $message->name = 'groupannouncement';
            $message->userfrom = $USER;
            $message->userto = $user;
            $message->subject = clean_param($title, PARAM_TEXT);
            $message->fullmessage = $text;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->courseid = $courseid;
            $message->contexturl = (new \moodle_url('/mod/page/view.php', ['id' => $id]))->out(false);
            $message->contexturlname = $message->subject;
            if (!message_send($message)) {
                // The page remains available. Do not conceal failed delivery.
                throw new \moodle_exception('supportfailed', 'local_asodeltolstaff');
            }
        }
        return $id;
    }

    public static function schedule(int $courseid, int $groupid, string $title, string $body, int $start, int $end): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/lib.php');
        self::require_group($courseid, $groupid);
        if ($start <= 0 || $end <= $start || trim($title) === '') {
            throw new \invalid_parameter_exception('An explicit title and chronological interval are required');
        }
        $event = \calendar_event::create((object) [
            'courseid' => $courseid, 'groupid' => $groupid, 'userid' => $USER->id,
            'name' => clean_param($title, PARAM_TEXT), 'description' => clean_text($body, FORMAT_HTML),
            'format' => FORMAT_HTML, 'eventtype' => 'group', 'component' => 'local_asodeltolstaff', 'type' => 2,
            'timestart' => $start, 'timeduration' => $end - $start,
            'visible' => 1, 'modulename' => '', 'instance' => 0,
        ]);
        if (!$event) {
            throw new \moodle_exception('errorcreatingevent', 'error');
        }
        return (int) $event->id;
    }

    /** Explicitly open a currently hidden section; preserve every prior condition. */
    public static function open_section(int $courseid, int $groupid, int $sectionid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        self::require_group($courseid, $groupid);
        require_capability('moodle/course:sectionvisibility', \context_course::instance($courseid));
        $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid], '*', MUST_EXIST);
        if ($section->section <= 0 || $section->visible || empty($CFG->enableavailability)) {
            throw new \invalid_parameter_exception('Only a currently hidden unit can be opened explicitly');
        }
        $conditions = [['type' => 'group', 'id' => $groupid]];
        if ($section->availability) {
            // Preserve dates, completion and other academic restrictions.
            $conditions[] = json_decode($section->availability, true, 32, JSON_THROW_ON_ERROR);
        }
        $availability = json_encode(['op' => '&', 'c' => $conditions, 'showc' => array_fill(0, count($conditions), false)]);
        course_update_section($courseid, $section->section, (object) ['availability' => $availability, 'visible' => 1]);
    }
}
