<?php
// Apply the project's declared local time base, without modifying saved instants.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8053') {
    throw new RuntimeException('Local staging required');
}
$before = (string) $CFG->timezone;
if (!in_array($before, ['Europe/London', 'America/Bogota'], true)) {
    throw new RuntimeException('Unexpected site time zone; preserve existing configuration');
}
$timesbefore = $DB->get_records_menu('local_asstaff_session', null, 'id', 'id,scheduledstart');
set_config('timezone', 'America/Bogota');
$timesafter = $DB->get_records_menu('local_asstaff_session', null, 'id', 'id,scheduledstart');
if ($timesbefore !== $timesafter) {
    throw new RuntimeException('Saved instants changed unexpectedly');
}
echo json_encode(['status' => 'PASS', 'before' => $before, 'after' => get_config('core', 'timezone'),
    'admin_profile_timezone' => get_admin()->timezone, 'saved_instants_unchanged' => true,
    'reason' => 'Project time base America/Bogota; avoids Europe/London DST fold in local staging'], JSON_THROW_ON_ERROR);
