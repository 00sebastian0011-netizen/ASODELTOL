<?php
namespace local_asodeltolstaff;

/** Add a discoverable, native navigation link for authenticated learners. */
final class hook_callbacks {
    public static function extend_primary(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }
        $hook->get_primaryview()->add(get_string('platformhelp', 'local_asodeltolstaff'),
            new \moodle_url('/local/asodeltolstaff/help.php'), \navigation_node::TYPE_CUSTOM,
            null, 'asodeltol_platform_help');
        if (has_capability('moodle/site:sendmessage', \context_system::instance())) {
            $hook->get_primaryview()->add(get_string('supportchannel', 'local_asodeltolstaff'),
                new \moodle_url('/local/asodeltolstaff/support.php'), \navigation_node::TYPE_CUSTOM,
                null, 'asodeltol_support');
        }
    }
}
