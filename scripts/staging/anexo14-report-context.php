<?php
// Read-only, local-only report reconciliation using existing synthetic data.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8053') {
    throw new RuntimeException('Local staging required.');
}
$course = $DB->get_record('course', ['idnumber' => 'ASODELTOL-AF8'], '*', MUST_EXIST);
$category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
if ($course->visible || $category->visible || $category->idnumber !== 'ASODELTOL-STAGING') {
    throw new RuntimeException('Hidden staging required.');
}
\core\session\manager::set_user(get_admin());
$context = context_course::instance($course->id);
foreach (['beattended', 'manageown'] as $capability) {
    foreach (get_enrolled_users($context, 'local/asodeltolstaff:' . $capability, 0, 'u.id,u.email') as $user) {
        if (!str_ends_with($user->email, '@example.invalid')) {
            throw new RuntimeException('Non-synthetic data: stop before export.');
        }
    }
}
$participants = \local_asodeltolstaff\report_manager::rows($course->id, 'participants');
$consolidated = \local_asodeltolstaff\report_manager::rows($course->id, 'consolidated');
$sample = $DB->get_record('local_asstaff_session', ['courseid' => $course->id, 'sessioncode' => 'M11-AF8-G01-01'], '*', MUST_EXIST);
$dates = [];
foreach (['scheduledstart', 'scheduledend', 'reportedstart', 'reportedend'] as $field) {
    if ($sample->$field === null) {
        $dates[$field] = null;
        continue;
    }
    $date = \core_calendar\type_factory::get_calendar_instance()->timestamp_to_date_array((int) $sample->$field,
        \core_date::get_user_timezone());
    $dates[$field] = ['year' => $date['year'], 'month' => $date['mon'], 'day' => $date['mday'],
        'hour' => $date['hours'], 'minute' => $date['minutes']];
}
echo json_encode(['course_id' => (int) $course->id, 'participants_rows' => count($participants),
    'sample_session_id' => (int) $sample->id, 'sample_session_display_dates' => $dates,
    'consolidated' => $consolidated,
    'access_rows' => count(\local_asodeltolstaff\report_manager::rows($course->id, 'access')),
    'office_hours_count' => $DB->count_records('event', ['courseid' => $course->id, 'component' => 'local_asodeltolstaff',
        'type' => \local_asodeltolstaff\office_hours::EVENT_TYPE]),
    'course_hidden' => true, 'category_hidden' => true], JSON_THROW_ON_ERROR);
