<?php
namespace local_asodeltolstaff;
defined('MOODLE_INTERNAL') || die();

final class profile_privacy {
    private const PATHS = ['user/view.php', 'user/profile.php', 'user/edit.php', 'user/editadvanced.php'];

    public static function control_view_profile(\stdClass $target, ?\stdClass $course = null,
            ?\context $usercontext = null): int {
        global $USER;
        if (!isloggedin() || isguestuser() || is_siteadmin()) {
            return \core\user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        if ((int)$target->id === (int)$USER->id) {
            return \core\user::VIEWPROFILE_FORCE_ALLOW;
        }
        $courses = $course ? [$course] : self::shared_courses((int)$USER->id, (int)$target->id);
        if (self::is_minimal_viewer((int)$USER->id)) {
            return \core\user::VIEWPROFILE_PREVENT;
        }
        foreach ($courses as $candidate) {
            if (!self::is_enrolled((int)$target->id, (int)$candidate->id)) {
                continue;
            }
            $context = \context_course::instance((int)$candidate->id);
            if (has_capability('local/asodeltolstaff:viewminimalprofile', $context, $USER->id)) {
                return \core\user::VIEWPROFILE_PREVENT;
            }
            $roles = get_user_roles($context, $USER->id, true);
            $shortnames = array_map(static fn($role) => $role->shortname, $roles);
            if (array_intersect($shortnames, ['asodeltol_auditor', 'sena'])) {
                return \core\user::VIEWPROFILE_FORCE_ALLOW;
            }
            if (array_intersect($shortnames, ['asodeltol_capacitador', 'capacitador', 'asodeltol_trainer'])) {
                $viewer = groups_get_all_groups($candidate->id, $USER->id);
                $subject = groups_get_all_groups($candidate->id, $target->id);
                return ($viewer && $subject && array_intersect_key($viewer, $subject))
                    ? \core\user::VIEWPROFILE_FORCE_ALLOW : \core\user::VIEWPROFILE_PREVENT;
            }
        }
        return \core\user::VIEWPROFILE_DO_NOT_PREVENT;
    }

    public static function enforce_own_profile(): void {
        global $USER;
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || !isloggedin() || isguestuser()
                || is_siteadmin() || !self::is_profile_script()) return;
        foreach (enrol_get_users_courses($USER->id, true, 'id') as $course) {
            if (has_capability('local/asodeltolstaff:viewminimalprofile',
                    \context_course::instance($course->id), $USER->id)
                    && optional_param('id', $USER->id, PARAM_INT) === (int)$USER->id) {
                redirect(new \moodle_url('/local/asodeltolstaff/profile.php'));
            }
        }
    }

    private static function is_profile_script(): bool {
        global $SCRIPT;
        $script = $SCRIPT ?? ($_SERVER['SCRIPT_NAME'] ?? '');
        return in_array(trim(parse_url((string)$script, PHP_URL_PATH), '/'), self::PATHS, true);
    }

    private static function shared_courses(int $viewerid, int $targetid): array {
        $viewer = array_map(static fn($course) => (int)$course->id,
            enrol_get_users_courses($viewerid, true, 'id'));
        return array_values(array_filter(enrol_get_users_courses($targetid, true, 'id'),
            static fn($course) => in_array((int)$course->id, $viewer, true)));
    }

    private static function is_minimal_viewer(int $userid): bool {
        foreach (enrol_get_users_courses($userid, true, 'id') as $course) {
            if (has_capability('local/asodeltolstaff:viewminimalprofile',
                    \context_course::instance($course->id), $userid)) {
                return true;
            }
        }
        return false;
    }

    private static function is_enrolled(int $userid, int $courseid): bool {
        foreach (enrol_get_users_courses($userid, true, 'id') as $course) {
            if ((int)$course->id === $courseid) {
                return true;
            }
        }
        return false;
    }
}
