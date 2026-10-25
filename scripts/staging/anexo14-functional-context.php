<?php
// Narrow local fixture operations, never a production importer.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8053' || empty($CFG->noemailever)) {
    throw new RuntimeException('Local staging with external email disabled required');
}
$course = $DB->get_record('course', ['idnumber' => 'ASODELTOL-AF8'], '*', MUST_EXIST);
$category = $DB->get_record('course_categories', ['id' => $course->category, 'idnumber' => 'ASODELTOL-STAGING'], '*', MUST_EXIST);
\core\session\manager::set_user(get_admin());
$phase = getopt('', ['phase:'])['phase'] ?? 'inspect';
if ($phase === 'lock') {
    course_change_visibility($course->id, false);
    \core_course_category::get($category->id)->hide();
    echo json_encode(['course_hidden' => true, 'category_hidden' => true]);
    exit(0);
}
if ($phase === 'unlock') {
    if ($course->visible || $category->visible) {
        throw new RuntimeException('Unexpected prior visibility; preserve another active operation');
    }
    \core_course_category::get($category->id)->show();
    course_change_visibility($course->id, true);
    echo json_encode(['temporary_synthetic_test_visibility' => true]);
    exit(0);
}
if (!in_array($phase, ['inspect', 'provision-files', 'verify-access'], true)) {
    throw new RuntimeException('Unsupported fixture phase');
}
$group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => 'AF8-G01'], '*', MUST_EXIST);
$manifest = json_decode(file_get_contents('/opt/asodeltol/artifacts/anexo14-files/manifest.json'), true, 32, JSON_THROW_ON_ERROR);
$resources = [];
foreach ($manifest['files'] as $item) {
    $filename = $item['filename'];
    if (!in_array($filename, ['synthetic.pdf', 'synthetic.docx', 'synthetic.xlsx', 'synthetic.pptx', 'synthetic.png'], true)) {
        throw new RuntimeException('Unknown fixture filename');
    }
    $name = 'ASODELTOL-A14-SYNTHETIC-' . $filename;
    $instance = $DB->get_record('resource', ['course' => $course->id, 'name' => $name]);
    if (!$instance && $phase === 'provision-files') {
        if ($course->visible || $category->visible || (int) get_config('local_asodeltolstaff', 'version') !== 2026102402) {
            throw new RuntimeException('Hidden staging and plugin version 2026102402 required');
        }
        $path = '/opt/asodeltol/artifacts/anexo14-files/' . $filename;
        if (hash_file('sha256', $path) !== $item['sha256']) {
            throw new RuntimeException('Synthetic fixture hash mismatch');
        }
        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_pathname(['contextid' => context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draft, 'filepath' => '/', 'filename' => $filename], $path);
        $cmid = \local_asodeltolstaff\group_publisher::create($course->id, $group->id, 1, 'resource', $name,
            'SYNTHETIC QA ONLY; NOT INSTITUTIONAL CONTENT OR APPROVAL', '', '', $draft);
        $cm = get_coursemodule_from_id('resource', $cmid, $course->id, false, MUST_EXIST);
        $instance = $DB->get_record('resource', ['id' => $cm->instance], '*', MUST_EXIST);
    }
    if ($instance) {
        $cm = get_coursemodule_from_instance('resource', $instance->id, $course->id, false, MUST_EXIST);
        $ctx = context_module::instance($cm->id);
        $files = get_file_storage()->get_area_files($ctx->id, 'mod_resource', 'content', 0, 'id', false);
        if (count($files) !== 1 || hash('sha256', reset($files)->get_content()) !== $item['sha256']) {
            throw new RuntimeException('Existing fixture differs; no overwrite performed');
        }
        $resources[] = $item + ['cmid' => (int) $cm->id, 'url' => moodle_url::make_pluginfile_url(
            $ctx->id, 'mod_resource', 'content', $instance->revision, '/', $filename, true)->out(false)];
    }
}
$actors = [];
foreach (['af8.synthetic.g01', 'af8.synthetic.g02', 'af8.synthetic.trainer.g01', 'af8.synthetic.trainer.g02', 'af8.synthetic.auditor'] as $username) {
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', MUST_EXIST);
    if (!str_ends_with($user->email, '@example.invalid')) {
        throw new RuntimeException('Only existing synthetic identities are permitted');
    }
    $actors[$username] = ['id' => (int) $user->id, 'name' => fullname($user), 'email' => $user->email];
}
$page = $DB->get_record('page', ['course' => $course->id, 'name' => 'ASODELTOL-A14-SYNTHETIC-GROUP-PAGE']);
$pagecm = $page ? get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST) : null;
$assigncm = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => 'ASODELTOL-AF8-M07-ASSIGN'], '*', MUST_EXIST);
$gradeitemid = $DB->get_field('grade_items', 'id', ['courseid' => $course->id, 'itemtype' => 'mod',
    'itemmodule' => 'assign', 'iteminstance' => $assigncm->instance], MUST_EXIST);
if ($phase === 'verify-access') {
    $own = $actors['af8.synthetic.g01']['id'];
    $peer = $actors['af8.synthetic.g02']['id'];
    $teacher = $DB->get_record('user', ['id' => $actors['af8.synthetic.trainer.g01']['id']], '*', MUST_EXIST);
    \core\session\manager::set_user($teacher);
    $data = \gradereport_user\external\user::get_grade_items($course->id, $own);
    $items = array_values(array_filter($data['usergrades'][0]['gradeitems'],
        static fn(array $item): bool => (int) $item['id'] === (int) $gradeitemid));
    $expected = $DB->get_field('grade_grades', 'finalgrade', ['itemid' => $gradeitemid, 'userid' => $own]);
    if (count($items) !== 1 || !isset($items[0]['graderaw']) || (float) $items[0]['graderaw'] !== (float) $expected) {
        throw new RuntimeException('Teacher own-group grade API did not return the recorded fixture grade');
    }
    $denied = false;
    try {
        \gradereport_user\external\user::get_grade_items($course->id, $peer);
    } catch (\moodle_exception $exception) {
        if (!in_array($exception->errorcode, ['notingroup', 'nopermissiontoviewgrades', 'nopermissions'], true)) {
            throw $exception;
        }
        $denied = true;
    }
    if (!$denied) {
        throw new RuntimeException('Native grade API exposed another group');
    }
    echo json_encode(['status' => 'PASS', 'native_teacher_own_group_grade' => true,
        'native_teacher_other_group_denied' => true, 'permissions_changed' => false], JSON_THROW_ON_ERROR);
    exit(0);
}
echo json_encode(['course_id' => (int) $course->id, 'group_id' => (int) $group->id,
    'resources' => $resources, 'actors' => $actors, 'page_cmid' => $pagecm ? (int) $pagecm->id : null,
    'grade_item_id' => (int) $gradeitemid,
    'module_count' => $DB->count_records('course_modules', ['course' => $course->id]),
    'course_hidden' => !$DB->get_field('course', 'visible', ['id' => $course->id]),
    'category_hidden' => !$DB->get_field('course_categories', 'visible', ['id' => $category->id])], JSON_THROW_ON_ERROR);
