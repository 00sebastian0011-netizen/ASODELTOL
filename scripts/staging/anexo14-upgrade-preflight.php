<?php
// Local-only upgrade scope and data preservation snapshot, no secrets.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8053') {
    throw new RuntimeException('Local staging required');
}
$pending = [];
\core_plugin_manager::reset_caches();
foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
    foreach ($plugins as $info) {
        if (in_array($info->get_status(), [\core_plugin_manager::PLUGIN_STATUS_NEW,
                \core_plugin_manager::PLUGIN_STATUS_UPGRADE, \core_plugin_manager::PLUGIN_STATUS_DOWNGRADE], true)) {
            $pending[$info->component] = $info->get_status();
        }
    }
}
$version = new stdClass();
require($CFG->dirroot . '/version.php');
$course = $DB->get_record('course', ['idnumber' => 'ASODELTOL-AF8'], '*', MUST_EXIST);
$category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
echo json_encode(['pending_plugins' => $pending, 'core_upgrade_required' => (float) $version > (float) $CFG->version,
    'plugin_version' => get_config('local_asodeltolstaff', 'version'),
    'publishown_registered' => $DB->record_exists('capabilities', ['name' => 'local/asodeltolstaff:publishown']),
    'publishown_grants' => $DB->count_records('role_capabilities', ['capability' => 'local/asodeltolstaff:publishown']),
    'roles' => $DB->count_records('role'), 'role_assignments' => $DB->count_records('role_assignments'),
    'course_hidden' => !$course->visible, 'category_hidden' => !$category->visible,
    'courses' => $DB->count_records('course'), 'users' => $DB->count_records('user'),
    'groups' => $DB->count_records('groups'),
    'sessions' => $DB->count_records('local_asstaff_session'),
    'attendance' => $DB->count_records('local_asstaff_attendance'),
    'history' => $DB->count_records('local_asstaff_history'),
    'office_hours' => $DB->count_records('event', ['component' => 'local_asodeltolstaff'])], JSON_THROW_ON_ERROR);
