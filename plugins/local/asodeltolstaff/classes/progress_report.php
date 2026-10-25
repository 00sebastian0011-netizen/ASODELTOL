<?php
namespace local_asodeltolstaff;

/** Read-only course/unit progress using native Moodle completion semantics. */
final class progress_report {
    public static function summary(int $courseid, int $userid): array {
        global $DB, $USER;
        $context = \context_course::instance($courseid);
        require_capability('local/asodeltolstaff:viewsessions', $context);
        if (!is_enrolled($context, $userid)) {
            throw new \moodle_exception('notenrolled', 'enrol');
        }
        if ($userid !== (int) $USER->id) {
            require_capability('moodle/grade:viewall', $context);
            if (!manager::can_view_all($context)) {
                $allowed = manager::get_available_groups($courseid);
                $targetgroups = groups_get_all_groups($courseid, $userid, 0, 'g.id');
                if (!array_intersect(array_keys($allowed), array_keys($targetgroups))) {
                    throw new \moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
                }
            }
        }
        $course = get_course($courseid);
        $completion = new \completion_info($course);
        $overall = \core_completion\progress::get_course_progress_percentage($course, $userid);
        if (!$completion->is_enabled() || !$completion->is_tracked_user($userid)) {
            return ['userid' => $userid, 'overall_percentage' => null, 'units' => []];
        }
        $eligible = $completion->get_user_activities_with_completion($userid);
        $info = get_fast_modinfo($course, $userid);
        $done = [];
        $records = $DB->get_records_sql('SELECT c.id, c.coursemoduleid, c.completionstate
            FROM {course_modules_completion} c JOIN {course_modules} cm ON cm.id = c.coursemoduleid
            WHERE cm.course = :course AND c.userid = :user', ['course' => $courseid, 'user' => $userid]);
        foreach ($records as $record) {
            if ((int) $record->completionstate !== COMPLETION_INCOMPLETE) {
                $done[(int) $record->coursemoduleid] = true;
            }
        }
        $units = [];
        foreach ($info->get_section_info_all() as $section) {
            if ($section->section <= 0) {
                continue;
            }
            $units[$section->section] = ['number' => (int) $section->section,
                'name' => $section->uservisible ? get_section_name($course, $section) : 'UT' . $section->section,
                'completed' => 0, 'total' => 0, 'percentage' => null];
        }
        foreach (array_keys($eligible) as $cmid) {
            $section = $info->get_cm($cmid)->sectionnum;
            if (isset($units[$section])) {
                $units[$section]['total']++;
                $units[$section]['completed'] += isset($done[$cmid]) ? 1 : 0;
            }
        }
        foreach ($units as &$unit) {
            $unit['percentage'] = $unit['total'] > 0 ? 100 * $unit['completed'] / $unit['total'] : null;
        }
        unset($unit);
        return ['userid' => $userid, 'overall_percentage' => $overall, 'units' => array_values($units)];
    }
}
