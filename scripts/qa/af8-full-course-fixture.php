<?php
/**
 * AF8 FULL COURSE — DISPOSABLE QA RUNNER ONLY.
 *
 * Creates a separate synthetic course with 5 UT, 50 Moodle Page activities,
 * two interactive communication/evaluation activities, seven groups,
 * fictional learners, synthetic grades and a group-restricted Page.
 *
 * It must never run against a production or persistent database.
 * Examples DO NOT certify academic originality, licenses, subtitles,
 * approved grade thresholds, eight-business-day calendars or Anexo 12.
 */
declare(strict_types=1);
define('CLI_SCRIPT', true);
require dirname(__DIR__, 2) . '/qa-moodle/config.php';

global $CFG, $DB;
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');

if (getenv('ASODELTOL_QA_ONLY') !== 'isolated-gh-runner' ||
        $CFG->wwwroot !== 'http://127.0.0.1:8053' ||
        $CFG->dbname !== 'asodeltolqa' ||
        !str_contains($CFG->dataroot, 'asodeltol-qa-data')) {
    throw new RuntimeException('REFUSED: QA script requires disposable localhost Moodle and exclusive QA database.');
}
$password = getenv('QA_TEST_PASSWORD');
if (!$password) {
    throw new RuntimeException('Missing ephemeral QA_TEST_PASSWORD.');
}
$catalog = json_decode(file_get_contents(dirname(__DIR__, 2) . '/catalog/af8/catalog.json'),
    true, 512, JSON_THROW_ON_ERROR);
if (count($catalog['units'] ?? []) !== 5 || count($catalog['component_templates'] ?? []) !== 10) {
    throw new RuntimeException('Refusing to generate a course with noncanonical unit/component counts.');
}
if ($DB->record_exists('course', ['shortname' => 'QA_AF8_FIVE_UT_ISOLATED'])) {
    throw new RuntimeException('Synthetic course already exists, refusing to overwrite.');
}
$admin = get_admin();
if (!$admin) {
    throw new RuntimeException('Isolated Moodle administrator missing.');
}
\core\session\manager::set_user($admin);

$course = create_course((object)[
    'shortname' => 'QA_AF8_FIVE_UT_ISOLATED',
    'fullname' => 'QA ONLY — AF8 five-unit synthetic completion simulation',
    'category' => 1,
    'format' => 'topics',
    'visible' => 1,
    'enablecompletion' => 1,
    'groupmode' => SEPARATEGROUPS,
    'groupmodeforce' => 1,
]);

course_create_sections_if_missing($course, range(1, 5));
$cmids = [];
foreach ($catalog['units'] as $i => $unit) {
    $unitnumber = $i + 1;
    $section = $DB->get_record('course_sections', [
        'course' => $course->id,
        'section' => $unitnumber,
    ], '*', MUST_EXIST);
    course_update_section($course, $section, [
        'name' => $unit['title'],
        'visible' => 1,
    ]);

    foreach ($unit['components'] as $component) {
        $code = $component['code'];
        $template = null;
        foreach ($catalog['component_templates'] as $candidate) {
            if ($candidate['template_id'] === $component['template_id']) {
                $template = $candidate;
                break;
            }
        }
        if (!$template) {
            throw new RuntimeException('Unknown component template: ' . $code);
        }

        [, , , , $data] = prepare_new_moduleinfo_data($course, 'page', $unitnumber);
        $data->name = $code . ' · ' . $template['title'] . ' [EJEMPLO QA]';
        $data->cmidnumber = 'QA_ONLY_' . str_replace('.', '_', $code);
        $data->introeditor = [
            'text' => 'Ejemplo técnico de simulación; no es material aprobado para beneficiarios.',
            'format' => FORMAT_PLAIN,
            'itemid' => 0,
        ];
        $marker = 'QA_ONLY_AF8_UT' . $unitnumber . '_C' . sprintf('%02d', $component['position']);
        $data->page = [
            'text' => '<h2>' . s($template['title']) . '</h2>'
                . '<p><strong>SIMULACIÓN TÉCNICA — NO PUBLICAR EN AF8 REAL.</strong></p>'
                . '<p>Unidad de prueba: ' . s($unit['title']) . '.</p>'
                . '<p>Ejemplo ficticio: diseñar una actividad de turismo rural sostenible, '
                . 'comprobar su objetivo y comentar el impacto territorial.</p>'
                . '<p>Marcador de prueba: ' . s($marker) . '</p>',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
        $data->display = 0;
        $data->popupwidth = 620;
        $data->popupheight = 450;
        $data->printintro = 0;
        $data->printlastmodified = 0;
        $data->revision = 1;
        $data->showdescription = 0;
        $data->completion = COMPLETION_TRACKING_NONE;
        $cm = add_moduleinfo($data, $course);
        $cmids[$code] = ['cmid' => (int)$cm->coursemodule, 'marker' => $marker];
    }
}

// Seven isolated groups and one fictional learner per group.
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$trainerrole = $DB->get_record('role', ['shortname' => 'asodeltol_capacitador'], '*', MUST_EXIST);
$auditorrole = $DB->get_record('role', ['shortname' => 'asodeltol_auditor'], '*', MUST_EXIST);
$senarole = $DB->get_record('role', ['shortname' => 'sena'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
$instance = null;
foreach (enrol_get_instances($course->id, true) as $item) {
    if ($item->enrol === 'manual') {
        $instance = $item;
        break;
    }
}
if (!$instance) {
    $id = $manual->add_instance($course);
    $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
}
$users = [];
$groups = [];
for ($n = 1; $n <= 7; $n++) {
    $key = sprintf('G%02d', $n);
    $groups[$key] = groups_create_group((object)[
        'courseid' => $course->id,
        'name' => 'QA-' . $key,
        'idnumber' => 'QA-AF8-' . $key,
    ]);
    $userid = \core\user::create_user((object)[
        'username' => 'qa_af8_' . strtolower($key),
        'password' => $password,
        'firstname' => 'Beneficiario',
        'lastname' => 'SoloQA' . $key,
        'email' => 'qa-af8-' . strtolower($key) . '@example.test',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'city' => 'QA Secret City ' . $key,
        'country' => 'CO',
    ], true, true);
    $manual->enrol_user($instance, $userid, $studentrole->id);
    groups_add_member($groups[$key], $userid);
    $users[$key] = $userid;
}
foreach (['trainer' => $trainerrole, 'auditor' => $auditorrole, 'sena' => $senarole] as $key => $role) {
    $userid = \core\user::create_user((object)[
        'username' => 'qa_af8_' . $key,
        'password' => $password,
        'firstname' => 'Rol',
        'lastname' => 'SoloQA' . ucfirst($key),
        'email' => 'qa-af8-' . $key . '@example.test',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'city' => 'QA Hidden Role City',
        'country' => 'CO',
    ], true, true);
    $manual->enrol_user($instance, $userid, $role->id);
    if ($key === 'trainer') {
        groups_add_member($groups['G01'], $userid);
    }
    $users[$key] = $userid;
}

// Native group availability: one example page is G01-only. Keep the synthetic
// course visible, while Moodle group availability filters this single Page.
$g1cm = (int)$cmids['UT1.07']['cmid'];
$cmrecord = $DB->get_record('course_modules', ['id' => $g1cm], '*', MUST_EXIST);
$cmrecord->availability = json_encode([
    'op' => '&', 'c' => [['type' => 'group', 'id' => (int)$groups['G01']]],
    'showc' => [false],
]);
$DB->update_record('course_modules', $cmrecord);

// A native Moodle forum represents communication; assignments are genuinely
// graded Moodle activities, not fake gradebook HTML content.
[, , , , $forum] = prepare_new_moduleinfo_data($course, 'forum', 1);
$forum->name = 'QA ONLY — foro de comunicación G01–G07';
$forum->cmidnumber = 'QA_AF8_FORUM';
$forum->introeditor = ['text' => 'Comunicación ficticia en staging efímero.', 'format' => FORMAT_PLAIN, 'itemid' => 0];
$forum->type = 'general';
$forum->assessed = 0;
$forum->scale = 0;
$forum->forcesubscribe = 0;
$forum->trackingtype = 1;
$forum->maxattachments = 1;
$forum->maxbytes = 0;
$forum->displaywordcount = 0;
$forum->warnafter = 0;
$forum->blockafter = 0;
$forum->blockperiod = 0;
$forum->rssarticles = 0;
$forum->rsstype = 0;
$forum->completion = COMPLETION_TRACKING_NONE;
$forum->visible = 1;
$forum->groupmode = SEPARATEGROUPS;
$forumcm = add_moduleinfo($forum, $course);

$assignmentids = [];
for ($n = 1; $n <= 5; $n++) {
    [, , , , $assign] = prepare_new_moduleinfo_data($course, 'assign', $n);
    $assign->name = 'QA ONLY — evaluación ficticia UT' . $n;
    $assign->cmidnumber = 'QA_AF8_ASSIGN_UT' . $n;
    $assign->introeditor = ['text' => 'Entrega ficticia sin validez académica.', 'format' => FORMAT_PLAIN, 'itemid' => 0];
    $assign->showdescription = 0;
    $assign->alwaysshowdescription = 1;
    $assign->allowsubmissionsfromdate = 0;
    $assign->duedate = 0;
    $assign->cutoffdate = 0;
    $assign->gradingduedate = 0;
    $assign->timelimit = 0;
    $assign->grade = 100;
    $assign->submissiondrafts = 0;
    $assign->requiresubmissionstatement = 0;
    $assign->sendnotifications = 0;
    $assign->sendlatenotifications = 0;
    $assign->sendstudentnotifications = 0;
    $assign->teamsubmission = 0;
    $assign->requireallteammemberssubmit = 0;
    $assign->blindmarking = 0;
    $assign->hidegrader = 0;
    $assign->markingworkflow = 0;
    $assign->markingallocation = 0;
    $assign->attemptreopenmethod = 'none';
    $assign->maxattempts = -1;
    $assign->preventsubmissionnotingroup = 1;
    $assign->assignsubmission_file_enabled = 0;
    $assign->assignsubmission_onlinetext_enabled = 1;
    $assign->assignsubmission_onlinetext_wordlimit_enabled = 0;
    $assign->assignfeedback_comments_enabled = 1;
    $assign->assignfeedback_editpdf_enabled = 0;
    $assign->assignfeedback_file_enabled = 0;
    $assign->visible = 1;
    $assign->groupmode = SEPARATEGROUPS;
    $assign->completion = COMPLETION_TRACKING_AUTOMATIC;
    $assign->completionusegrade = 1;
    $assign->completionpassgrade = 0;
    $module = add_moduleinfo($assign, $course);
    $assignmentids[] = (int)$module->coursemodule;
}

$grade = new grade_item([
    'courseid' => $course->id,
    'itemname' => 'QA_ONLY_AF8_FICTITIOUS_GRADE',
    'itemtype' => 'manual',
    'gradetype' => GRADE_TYPE_VALUE,
    'grademin' => 0,
    'grademax' => 100,
], false);
if (!$grade->insert('local_asodeltolstaff') ||
        !$grade->update_final_grade($users['G01'], 87, 'local_asodeltolstaff')) {
    throw new RuntimeException('Synthetic grade could not be stored.');
}
rebuild_course_cache($course->id, true);

$sections = $DB->get_records('course_sections', ['course' => $course->id]);
if (count($sections) !== 6) {
    throw new RuntimeException('Expected general section and five UT.');
}
foreach ($catalog['units'] as $n => $unit) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $n+1], '*', MUST_EXIST);
    if ($section->name !== $unit['title']) {
        throw new RuntimeException('Synthetic unit title mismatch.');
    }
}
if (count($cmids) !== 50 || count($groups) !== 7 || count($users) !== 10 || count($assignmentids) !== 5) {
    throw new RuntimeException('Fixture count mismatch.');
}
$modules = $DB->count_records('course_modules', ['course' => $course->id]);
if ($modules !== 56) {
    throw new RuntimeException('Expected 50 pages, 1 forum, 5 native assignments; got ' . $modules);
}
file_put_contents(dirname(__DIR__, 2) . '/qa-af8-full-ids.json', json_encode([
    'course' => (int)$course->id,
    'pages' => $cmids,
    'groups' => $groups,
    'users' => $users,
    'assignments' => $assignmentids,
    'forum' => (int)$forumcm->coursemodule,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'PASS: disposable AF8 synthetic course created: 5 UT / 50 Pages / 7 groups / 10 users / 5 graded assignments / forum / grade / G01 restriction.' . PHP_EOL;
