<?php
/** Local fixture control/inspection; never a public web endpoint. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19') {
    throw new RuntimeException('Isolated close19 required.');
}
$fixture = json_decode(file_get_contents('/tmp/close19-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$action = $argv[1] ?? 'snapshot';
if ($action === 'configure-support') {
    set_config('supportuserid', get_admin()->id, 'local_asodeltolstaff');
} else if ($action === 'unconfigure-support') {
    unset_config('supportuserid', 'local_asodeltolstaff');
} else if ($action === 'allow-synthetic-trainer-messages') {
    set_user_preference('message_blocknoncontacts', 0, $fixture['trainer']['id']);
    foreach ($fixture['users'] as $user) {
        if (!\core_message\api::is_contact($user['id'], $fixture['trainer']['id'])) {
            \core_message\api::add_contact($user['id'], $fixture['trainer']['id']);
        }
    }
} else if ($action === 'configure-youtube-embed' || $action === 'configure-youtube-link') {
    \core\session\manager::set_user(get_admin());
    \core\plugininfo\media::enable_plugin('youtube', 1);
    require_once($CFG->dirroot . '/mod/url/lib.php');
    require_once($CFG->dirroot . '/course/lib.php');
    $cm = get_coursemodule_from_id('url', $fixture['youtube'], $fixture['courseid'], false, MUST_EXIST);
    $url = $DB->get_record('url', ['id' => $cm->instance], '*', MUST_EXIST);
    $url->instance = $url->id;
    $url->display = $action === 'configure-youtube-link' ? 5 : 1;
    url_update_instance($url, null);
    rebuild_course_cache($fixture['courseid'], true);
} else if ($action === 'use-rsmax' && get_config('theme_rsmax', 'version') === '2026102500') {
    set_config('theme', 'rsmax');
    theme_reset_all_caches();
} else if ($action !== 'snapshot' && $action !== 'refresh') {
    throw new RuntimeException('Unsupported fixture action.');
}
$fixture['file_url'] = moodle_url::make_pluginfile_url($fixture['modules']['C06']['contextid'], 'mod_resource', 'content',
    1, '/', 'material.pdf')->out(false);
$fixture['video_url'] = moodle_url::make_pluginfile_url($fixture['media']['contextid'], 'mod_page', 'content',
    1, '/', 'test.mp4')->out(false);
$fixture['audio_url'] = moodle_url::make_pluginfile_url($fixture['media']['contextid'], 'mod_page', 'content',
    1, '/', 'test.mp3')->out(false);
foreach (['file', 'video', 'audio'] as $kind) {
    $fixture[$kind . '_url'] = str_replace($CFG->wwwroot, '', $fixture[$kind . '_url']);
}
foreach (['file' => 'material.pdf', 'video' => 'test.mp4', 'audio' => 'test.mp3'] as $kind => $name) {
    $fixture[$kind . '_sha256'] = hash_file('sha256', '/tmp/close19-media/' . $name);
}
file_put_contents('/tmp/close19-fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));
$notifications = $DB->get_records('notifications', ['component' => 'local_asodeltolstaff'], 'id',
    'id,useridfrom,useridto,eventtype,contexturl,timecreated');
$cases = $DB->get_records('local_asstaff_case', null, 'id', 'id,requesterid,recipientid,status,timecreated,timemodified');
$sections = $DB->get_records('course_sections', ['course' => $fixture['release']['courseid']], 'section',
    'id,section,visible,availability');
$published = $DB->get_records_sql('SELECT cm.id AS cmid, m.name AS kind, cm.instance, cm.availability
    FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.course = :courseid ORDER BY cm.id',
    ['courseid' => $fixture['courseid']]);
$events = $DB->get_records('event', ['courseid' => $fixture['courseid'], 'component' => 'local_asodeltolstaff'], 'id',
    'id,groupid,name,timestart,timeduration');
$course = $DB->get_record('course', ['id' => $fixture['courseid']], '*', MUST_EXIST);
$hiddenfixtures = $DB->get_records_sql("SELECT c.id, c.visible, cc.visible AS categoryvisible
    FROM {course} c JOIN {course_categories} cc ON cc.id = c.category WHERE cc.idnumber LIKE :marker ORDER BY c.id",
    ['marker' => 'QA19-%']);
$coursefields = array_intersect_key((array)$course, array_flip(['id', 'idnumber', 'fullname', 'summary', 'visible', 'startdate', 'enddate', 'lang']));
$groupfields = $DB->get_record('groups', ['id' => $fixture['groups'][0]], 'id,courseid,name,idnumber,description', MUST_EXIST);
$custom = [];
foreach (['core_course' => ['course', $course->id], 'core_group' => ['group', $groupfields->id]] as $component => [$area, $id]) {
    foreach (\core_customfield\handler::get_handler($component, $area)->get_instance_data($id, true) as $data) {
        $custom[$data->get_field()->get('shortname')] = $data->get_value();
    }
}
$native = [];
foreach ($fixture['catalog_mapping'] as $item) {
    $cm = $DB->get_record('course_modules', ['id' => $item['cmid']], 'id,course,module,section', MUST_EXIST);
    $native[] = ['code' => $item['code'], 'template' => $item['template'], 'module' => $DB->get_field('modules', 'name', ['id' => $cm->module]),
        'course_matches' => (int)$cm->course === $fixture['courseid'],
        'section' => (int)$DB->get_field('course_sections', 'section', ['id' => $cm->section])];
}
echo json_encode(['plugin_version' => get_config('local_asodeltolstaff', 'version'), 'theme' => $CFG->theme,
    'theme_version' => get_config('theme_' . $CFG->theme, 'version'), 'moodle' => $CFG->release,
    'php' => PHP_VERSION, 'mysql' => $DB->get_server_info()['version'],
    'course_hidden' => !$course->visible, 'category_hidden' => !$DB->get_field('course_categories', 'visible', ['id' => $course->category]),
    'notifications' => array_values($notifications), 'cases' => array_values($cases), 'release_sections' => array_values($sections),
    'published_modules' => array_values($published), 'calendar_events' => array_values($events),
    'course_fields' => $coursefields, 'group_fields' => $groupfields, 'custom_fields' => $custom,
    'hidden_fixtures' => array_values($hiddenfixtures),
    'synthetic_contacts_ready' => \core_message\api::is_contact($fixture['users'][0]['id'], $fixture['trainer']['id']),
    'catalog_mapping' => $native, 'grade_items' => $DB->count_records('grade_items', ['courseid' => $course->id]),
    'support_events' => $DB->count_records('local_asstaff_caseevent')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
