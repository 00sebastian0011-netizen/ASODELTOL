<?php
// This file is part of Moodle - http://moodle.org/
namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/**
 * Enforce the institutional profile boundary before Moodle renders any profile.
 *
 * Called by local_asodeltolstaff_after_require_login() after Moodle has
 * authenticated the viewer and performed the compulsory policy check.
 */
final class profile_privacy {
    private const PROFILE_PATHS = [
        'user/view.php',
        'user/profile.php',
        'user/edit.php',
        'user/editadvanced.php',
    ];

    public static function enforce_profile_access(): void {
        global $PAGE, $USER;

        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || !isloggedin() || isguestuser()
                || is_siteadmin() || empty($PAGE->url)) {
            return;
        }

        $path = trim($PAGE->url->get_path(), '/');
        if (!in_array($path, self::PROFILE_PATHS, true)) {
            return;
        }

        // The caller-supplied course parameter is deliberately ignored.
        $targetid = optional_param('id', 0, PARAM_INT) ?: (int) $USER->id;
        if (self::is_minimal_profile_user((int) $USER->id)) {
            if ($targetid !== (int) $USER->id) {
                throw new \moodle_exception('nopermissions', 'error', '', get_string('profile'));
            }

            // Never render Moodle's unrestricted profile or edit forms.
            redirect(new \moodle_url('/local/asodeltolstaff/profile.php'));
        }

        // Trainers only inspect beneficiaries belonging to their authorised AF8 groups.
        self::enforce_trainer_group_scope((int) $USER->id, $targetid);
    }

    private static function is_minimal_profile_user(int $userid): bool {
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

    private static function enforce_trainer_group_scope(int $viewerid, int $targetid): void {
        global $DB;

        if ($viewerid === $targetid) {
            return;
        }

        // Scope this control to the institutional AF8 course only.
        $courseid = (int) $DB->get_field('course', 'id', ['idnumber' => 'ASODELTOL-AF8']);
        if (!$courseid) {
            // Legacy production AF8 has course id 4. Only use this fallback
            // after verifying the course shortname, never a caller parameter.
            $shortname = $DB->get_field('course', 'shortname', ['id' => 4]);
            if ($shortname === 'AF8') {
                $courseid = 4;
            }
        }
        if (!$courseid) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('profile'));
        }
        $context = \context_course::instance($courseid);

        // Auditors/SENA are expressly allowed to inspect all groups.
        if (has_capability('moodle/site:accessallgroups', $context, $viewerid)) {
            return;
        }

        // Academic staff without unrestricted group access must share an AF8
        // group with the beneficiary. Do not use the URL's 'course' parameter.
        if (!has_capability('moodle/course:manageactivities', $context, $viewerid)) {
            return;
        }

        $viewergroups = groups_get_all_groups($courseid, $viewerid);
        $targetgroups = groups_get_all_groups($courseid, $targetid);
        if (!$viewergroups || !$targetgroups || !array_intersect_key($viewergroups, $targetgroups)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('profile'));
        }
    }
}
