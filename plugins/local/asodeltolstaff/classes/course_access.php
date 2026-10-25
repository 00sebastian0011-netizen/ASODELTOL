<?php
namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/** Course contexts where the current user really has the export capability. */
final class course_access {
    public static function exportable_courses(?int $userid = null): array {
        global $DB, $USER;
        $userid = $userid ?? (int) $USER->id;
        $courses = $DB->get_records('course', null, 'sortorder ASC', 'id,fullname,shortname,visible');
        $allowed = [];
        foreach ($courses as $course) {
            $context = \context_course::instance((int) $course->id);
            if (has_capability('local/asodeltolstaff:export', $context, $userid)) {
                $allowed[$course->id] = $course;
            }
        }
        return $allowed;
    }
}
