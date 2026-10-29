<?php
namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/** Server-side profile boundary for Beneficiaries. */
final class profile_privacy {
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $CFG, $DB, $PAGE, $USER;

        if (!isloggedin() || isguestuser() || empty($PAGE->url)) {
            return;
        }
        $path = trim($PAGE->url->get_path(), '/');
        if (!in_array($path, ['user/profile.php', 'user/edit.php', 'user/editadvanced.php'], true)) {
            return;
        }

        $courseid = optional_param('course', 0, PARAM_INT);
        if (!self::is_minimal_profile_user($USER->id, $courseid)) {
            return;
        }

        $targetid = optional_param('id', $USER->id, PARAM_INT);
        if ($targetid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('profile'));
        }

        // Beneficiaries never enter Moodle's unrestricted profile/edit renderer.
        // The local endpoint emits only the three approved fields server-side.
        redirect(new \moodle_url('/local/asodeltolstaff/profile.php'));
    }

    private static function is_minimal_profile_user(int $userid, int $courseid): bool {
        global $DB;
        if ($courseid > 0) {
            return has_capability(
                'local/asodeltolstaff:viewminimalprofile',
                \context_course::instance($courseid),
                $userid
            );
        }
        $courses = enrol_get_users_courses($userid, true, 'id');
        foreach ($courses as $course) {
            if (has_capability(
                'local/asodeltolstaff:viewminimalprofile',
                \context_course::instance($course->id),
                $userid
            )) {
                return true;
            }
        }
        return false;
    }
}
