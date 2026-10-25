<?php
namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/** Course contexts where the current user really has the export capability. */
final class course_access {
    public static function exportable_courses(?int $userid = null): array {
        global $USER;
        $userid = $userid ?? (int) $USER->id;
        if (!$userid || isguestuser($userid)) {
            return [];
        }
        $courses = get_user_capability_course('local/asodeltolstaff:export', $userid, true,
            'fullname,shortname,visible', 'sortorder ASC') ?: [];
        $allowed = [];
        foreach ($courses as $course) {
            if ((int) $course->id === (int) SITEID) {
                continue;
            }
            $context = \context_course::instance((int) $course->id);
            if (has_capability('local/asodeltolstaff:export', $context, $userid)
                    && ($course->visible || has_capability('moodle/course:viewhiddencourses', $context, $userid))
                    && (is_enrolled($context, $userid, '', true)
                        || has_capability('moodle/course:view', $context, $userid))) {
                $allowed[$course->id] = $course;
            }
        }
        return $allowed;
    }
}
