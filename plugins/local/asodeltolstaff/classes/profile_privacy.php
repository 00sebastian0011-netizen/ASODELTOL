<?php
// This file is part of Moodle - http://moodle.org/
namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/**
 * Restrict institutional profile visibility without granting editing rights.
 *
 * The course passed by Moodle is a scope restriction, not proof that either
 * user is enrolled there. Never FORCE_ALLOW on role assignments alone.
 */
final class profile_privacy {
    private const PATHS = ['user/view.php', 'user/profile.php', 'user/edit.php', 'user/editadvanced.php'];
    private const TRAINER_ROLES = ['asodeltol_capacitador', 'asodeltol_capacitador_m07', 'capacitador', 'asodeltol_trainer'];
    private const AUDITOR_ROLES = ['asodeltol_auditor', 'sena'];

    public static function control_view_profile(\stdClass $target, ?\stdClass $course = null,
            ?\context $usercontext = null): int {
        global $USER;

        if (!isloggedin() || isguestuser() || is_siteadmin()) {
            return \core\user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        if ((int) $target->id === (int) $USER->id) {
            // Moodle permits self-view; our require_login callback redirects
            // institutional beneficiaries to the minimal own-profile page.
            return \core\user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        if (!empty($target->deleted)) {
            return \core\user::VIEWPROFILE_PREVENT;
        }

        $viewerid = (int) $USER->id;
        $targetid = (int) $target->id;
        $viewercourses = enrol_get_users_courses($viewerid, true, 'id');
        $targetcourses = enrol_get_users_courses($targetid, true, 'id');

        $isbeneficiary = false;
        $isstaff = false;
        $rolesbycourse = [];
        foreach ($viewercourses as $viewercourse) {
            $courseid = (int) $viewercourse->id;
            $context = \context_course::instance($courseid);
            if (has_capability('local/asodeltolstaff:viewminimalprofile', $context, $viewerid)) {
                $isbeneficiary = true;
            }

            $roles = get_user_roles($context, $viewerid, true);
            $shortnames = array_map(static fn($role) => $role->shortname, $roles);
            $rolesbycourse[$courseid] = $shortnames;
            if (array_intersect($shortnames, array_merge(self::TRAINER_ROLES, self::AUDITOR_ROLES))) {
                $isstaff = true;
            }
        }

        // Evaluate the viewer's institutional beneficiary restriction BEFORE
        // checking shared courses, so URL/course tampering cannot bypass it.
        if ($isbeneficiary) {
            return \core\user::VIEWPROFILE_PREVENT;
        }
        if (!$isstaff) {
            // Do not change ordinary Moodle accounts outside institutional roles.
            return \core\user::VIEWPROFILE_DO_NOT_PREVENT;
        }

        // A provided course is authoritative for the scope only if BOTH users
        // are actively enrolled there. Never use it to expand access.
        $candidates = $course ? [$course] : $targetcourses;
        foreach ($candidates as $candidate) {
            $courseid = (int) $candidate->id;
            if (!isset($rolesbycourse[$courseid]) ||
                    !isset($targetcourses[$courseid])) {
                continue;
            }
            $context = \context_course::instance($courseid);
            if (!is_enrolled($context, $viewerid, '', true) ||
                    !is_enrolled($context, $targetid, '', true)) {
                continue;
            }

            $shortnames = $rolesbycourse[$courseid];
            if (array_intersect($shortnames, self::AUDITOR_ROLES)) {
                return \core\user::VIEWPROFILE_FORCE_ALLOW;
            }
            if (array_intersect($shortnames, self::TRAINER_ROLES)) {
                // Capacitadores may see only beneficiaries of their own groups.
                if (!has_capability('local/asodeltolstaff:viewminimalprofile', $context, $targetid)) {
                    continue;
                }
                $viewergroups = groups_get_all_groups($courseid, $viewerid);
                $targetgroups = groups_get_all_groups($courseid, $targetid);
                if ($viewergroups && $targetgroups && array_intersect_key($viewergroups, $targetgroups)) {
                    return \core\user::VIEWPROFILE_FORCE_ALLOW;
                }
            }
        }

        // A recognised institutional staff account must not accidentally fall
        // back to Moodle's broader default visibility when scope validation fails.
        return \core\user::VIEWPROFILE_PREVENT;
    }

    public static function enforce_own_profile(): void {
        global $USER;
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || !isloggedin() || isguestuser()
                || is_siteadmin() || !self::is_profile_script()) {
            return;
        }
        foreach (enrol_get_users_courses($USER->id, true, 'id') as $course) {
            if (has_capability('local/asodeltolstaff:viewminimalprofile',
                    \context_course::instance($course->id), $USER->id)
                    && optional_param('id', $USER->id, PARAM_INT) === (int) $USER->id) {
                redirect(new \moodle_url('/local/asodeltolstaff/profile.php'));
            }
        }
    }

    private static function is_profile_script(): bool {
        global $SCRIPT;
        $script = $SCRIPT ?? ($_SERVER['SCRIPT_NAME'] ?? '');
        return in_array(trim(parse_url((string) $script, PHP_URL_PATH), '/'), self::PATHS, true);
    }
}
