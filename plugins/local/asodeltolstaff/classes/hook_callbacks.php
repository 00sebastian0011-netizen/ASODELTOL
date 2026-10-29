<?php
namespace local_asodeltolstaff;

/** Add a discoverable, native navigation link for authenticated learners. */
final class hook_callbacks {
    /**
     * Enforce the scoped Staff profile before Moodle emits any page output.
     *
     * Moodle's user/profile.php does not call require_login() when neither
     * forceloginforprofiles nor forcelogin is enabled, so after_require_login
     * alone cannot protect that route.
     */
    public static function enforce_profile_privacy(
            \core\hook\output\before_standard_head_html_generation $hook): void {
        profile_privacy::enforce_own_profile();

        // If navigating to feedback print preview (e.g. from the Moodle "Vista previa" button),
        // redirect to the official interactive complete form so beneficiaries and staff can test and submit.
        global $PAGE;
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/mod/feedback/print.php') !== false || ($PAGE->has_set_url() && strpos($PAGE->url->get_path(), '/mod/feedback/print.php') !== false)) {
            $cmid = optional_param('id', 0, PARAM_INT);
            if ($cmid > 0) {
                $courseid = optional_param('courseid', 0, PARAM_INT);
                $redirectparams = ['id' => $cmid];
                if ($courseid > 0) {
                    $redirectparams['courseid'] = $courseid;
                }
                redirect(new \moodle_url('/mod/feedback/complete.php', $redirectparams));
            }
        }
    }

    public static function extend_primary(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }
        // One discoverable native dropdown: the guide explains Moodle; CAU records cases.
        // The reports link stays separate, and unrelated IA tools are not touched.
        $guideurl = new \moodle_url('/local/asodeltolstaff/help.php');
        $help = $hook->get_primaryview()->add(get_string('helpandsupport', 'local_asodeltolstaff'),
            $guideurl, \navigation_node::TYPE_CUSTOM, null, 'asodeltol_help_support');
        $help->add(get_string('platformhelp', 'local_asodeltolstaff'),
            $guideurl, \navigation_node::TYPE_CUSTOM, null, 'asodeltol_platform_help');
        if (has_capability('moodle/site:sendmessage', \context_system::instance())
                && support::recipient() !== null) {
            $help->add(get_string('supportchannel', 'local_asodeltolstaff'),
                new \moodle_url('/local/asodeltolstaff/support.php'), \navigation_node::TYPE_CUSTOM,
                null, 'asodeltol_support');
        }
        if (course_access::exportable_courses()) {
            $hook->get_primaryview()->add(get_string('reportsdownloads', 'local_asodeltolstaff'),
                new \moodle_url('/local/asodeltolstaff/reports.php'), \navigation_node::TYPE_CUSTOM,
                null, 'asodeltol_reports_downloads');
        }
    }
}
