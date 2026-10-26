<?php
/** Synthetic-only native Moodle generators on the independent close19 server. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19') {
    throw new RuntimeException('Independent close19 runtime required.');
}
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
\core\session\manager::set_user(get_admin());
if (is_file('/tmp/close19-fixture.json') && in_array('--new', $argv, true)) {
    copy('/tmp/close19-fixture.json', '/tmp/close19-fixture-' . time() . '.json');
} else if (is_file('/tmp/close19-fixture.json')) {
    echo 'Existing fixture preserved; use /tmp/close19-fixture.json' . PHP_EOL;
    exit(0);
}
foreach (['enablecompletion', 'enableavailability', 'messaging', 'enablemyhome', 'enabledashboard', 'enablemycourses'] as $name) {
    set_config($name, 1);
}
set_config('supportuserid', get_admin()->id, 'local_asodeltolstaff');
$g = new testing_data_generator();
$run = (string)time();
$category = $g->create_category(['name' => 'QA19 synthetic only ' . $run, 'visible' => 0, 'idnumber' => 'QA19-' . $run]);
$course = $g->create_course(['fullname' => 'QA19 synthetic representative course', 'shortname' => 'QA19-' . $run,
    'idnumber' => 'QA19-' . $run, 'category' => $category->id, 'visible' => 0, 'numsections' => 5,
    'summary' => 'Synthetic performance and functional fixture. No official learning content or approval.',
    'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1, 'enablecompletion' => 1, 'lang' => 'en',
    'startdate' => time() - DAYSECS, 'enddate' => time() + 30 * DAYSECS]);
$context = context_course::instance($course->id);
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $studentrole, $context->id);
foreach (['moodle/course:viewhiddencourses', 'moodle/course:sectionvisibility',
    'local/asodeltolstaff:publishown', 'local/asodeltolstaff:viewsessions', 'local/asodeltolstaff:manageown'] as $cap) {
    assign_capability($cap, CAP_ALLOW, $teacherrole, $context->id);
}
$groups = [];
for ($i=1; $i<=7; $i++) {
    $groups[] = $g->create_group(['courseid' => $course->id, 'name' => sprintf('QA19 G%02d', $i),
        'idnumber' => 'QA19-G' . $i, 'description' => 'Synthetic group, not institutional assignment']);
}
$password = trim(file_get_contents('/run/secrets/synthetic_password'));
$users = [];
$makeuser = static function(string $suffix) use ($g, $password, $run): stdClass {
    return $g->create_user(['username' => 'qa19_' . $run . '_' . $suffix, 'password' => $password,
        'firstname' => 'Synthetic', 'lastname' => $suffix, 'email' => 'qa19-' . $run . '-' . $suffix . '@example.invalid']);
};
for ($i=1; $i<=50; $i++) {
    $user = $makeuser(sprintf('learner%02d', $i));
    $g->enrol_user($user->id, $course->id, $studentrole);
    groups_add_member($groups[0], $user);
    $users[] = ['id' => (int)$user->id, 'username' => $user->username];
}
$other = $makeuser('othergroup');
$g->enrol_user($other->id, $course->id, $studentrole);
groups_add_member($groups[1], $other);
$trainer = $makeuser('trainer');
$g->enrol_user($trainer->id, $course->id, $teacherrole);
groups_add_member($groups[0], $trainer);
$none = $makeuser('unenrolled');
$fields = \local_asodeltolstaff\metadata_fields::configure();
$handler = \core_customfield\handler::get_handler('core_course', 'course');
$handler->instance_form_save((object)['id' => $course->id, 'customfield_asodeltol_adminroleid' => 'qa19-admin-role',
    'customfield_asodeltol_auditorroleid' => 'qa19-auditor-role']);
$ghandler = \core_customfield\handler::get_handler('core_group', 'group');
$ghandler->instance_form_save((object)['id' => $groups[0]->id, 'customfield_asodeltol_available' => 1]);

// Generated locally by FFmpeg; no third-party course material is copied.
$media = '/tmp/close19-media';
if (!is_file($media . '/test.mp4') || !is_file($media . '/test.mp3')) {
    throw new RuntimeException('Generate the synthetic media first. Partial fixture remains hidden.');
}
$pdfpath = $media . '/material.pdf';
if (!is_file($pdfpath)) {
    require_once($CFG->libdir . '/pdflib.php');
    $pdf = new pdf();
    for ($i=0; $i<30; $i++) {
        $pdf->AddPage();
        $pdf->writeHTML('<h1>Synthetic QA fixture - not academic material</h1><p>' . str_repeat('Generated content for technical load only. ', 120) . '</p>');
    }
    $pdf->Output($pdfpath, 'F');
}
$file = static function(int $cmid, string $component, string $area, string $path): void {
    get_file_storage()->create_file_from_pathname(['contextid' => context_module::instance($cmid)->id,
        'component' => $component, 'filearea' => $area, 'itemid' => 0, 'filepath' => '/',
        'filename' => basename($path)], $path);
};
$catalog = json_decode(file_get_contents('/tmp/close19-catalog.json'), true, 512, JSON_THROW_ON_ERROR);
$templates = array_column($catalog['component_templates'], null, 'template_id');
$mapping = [];
$modules = [];
foreach ($catalog['units'] as $unit) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $unit['position']], '*', MUST_EXIST);
    course_update_section($course, $section, ['name' => $unit['code'] . ' synthetic QA', 'summary' => 'QA fixture, not official academic content']);
    foreach ($unit['components'] as $item) {
        $template = $templates[$item['template_id']];
        $kind = $template['selected_moodle_type'] ?? ($item['template_id'] === 'C06' ? 'resource' : 'url');
        $record = ['course' => $course->id, 'name' => 'QA ' . $item['code'] . ' ' . $template['title'],
            'intro' => 'Synthetic only; not approved learning material', 'section' => $unit['position'],
            'groupmode' => SEPARATEGROUPS];
        if ($kind === 'page') {
            $record['content'] = '<p>' . str_repeat('Synthetic fixture; no approved learning outcomes. ', 400) . '</p>';
            $record['contentformat'] = FORMAT_HTML;
        }
        if ($kind === 'resource') {
            $record['display'] = 6;
        }
        if ($kind === 'url') {
            $record['externalurl'] = 'https://www.youtube.com/watch?v=M7lc1UVf-VE';
        }
        $module = $g->create_module($kind, $record);
        if ($kind === 'resource') {
            $file($module->cmid, 'mod_resource', 'content', $pdfpath);
        }
        if ($kind === 'quiz') {
            $qg = $g->get_plugin_generator('core_question');
            $qcategory = $qg->create_question_category(['contextid' => $context->id]);
            $question = $qg->create_question('truefalse', null, ['category' => $qcategory->id, 'name' => 'Synthetic question only']);
            quiz_add_quiz_question($question->id, $module);
        }
        $mapping[] = ['code' => $item['code'], 'template' => $item['template_id'], 'cmid' => (int)$module->cmid,
            'kind' => $kind, 'synthetic' => true];
        if ($unit['position'] === 1) {
            $modules[$item['template_id']] = ['cmid' => (int)$module->cmid, 'instance' => (int)$module->id,
                'contextid' => context_module::instance($module->cmid)->id];
        }
    }
}
$mediapage = $g->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'QA auxiliary media fixture',
    'content' => '<p>Synthetic MP4 and MP3; auxiliary fixture outside the canonical 50 items.</p>'
        . '<video id="qa19-video" controls playsinline width="640" height="360"><source src="@@PLUGINFILE@@/test.mp4" type="video/mp4"></video>'
        . '<audio id="qa19-audio" controls src="@@PLUGINFILE@@/test.mp3"></audio>']);
$file($mediapage->cmid, 'mod_page', 'content', $media . '/test.mp4');
$file($mediapage->cmid, 'mod_page', 'content', $media . '/test.mp3');
$assignment = $g->create_module('assign', ['course' => $course->id, 'section' => 1, 'name' => 'QA submission fixture',
    'assignsubmission_file_enabled' => 1, 'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_maxfiles' => 1]);
$forum = $g->create_module('forum', ['course' => $course->id, 'section' => 1, 'name' => 'QA group discussion', 'groupmode' => SEPARATEGROUPS]);
$feedback = $g->create_module('feedback', ['course' => $course->id, 'section' => 1, 'name' => 'QA survey fixture', 'anonymous' => 2]);
$g->get_plugin_generator('mod_feedback')->create_item_textfield($feedback, ['name' => 'Synthetic QA comment', 'label' => 'QA19']);
$youtube = $g->create_module('url', ['course' => $course->id, 'section' => 1, 'name' => 'QA YouTube official API sample',
    'externalurl' => 'https://www.youtube.com/watch?v=M7lc1UVf-VE', 'display' => 5]);
$release = $g->create_course(['fullname' => 'QA19 unit release fixture', 'shortname' => 'QA19-release-' . $run,
    'category' => $category->id, 'visible' => 0, 'numsections' => 2, 'groupmode' => SEPARATEGROUPS]);
$releasecontext = context_course::instance($release->id);
assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $studentrole, $releasecontext->id);
foreach (['moodle/course:viewhiddencourses', 'moodle/course:sectionvisibility', 'local/asodeltolstaff:publishown'] as $cap) {
    assign_capability($cap, CAP_ALLOW, $teacherrole, $releasecontext->id);
}
$rgroups = [];
foreach ([[$trainer, $users[0]['id']], [$other]] as $members) {
    $group = $g->create_group(['courseid' => $release->id]);
    $rgroups[] = $group;
    foreach ($members as $member) {
        $userid = is_object($member) ? $member->id : $member;
        $g->enrol_user($userid, $release->id, $userid == $trainer->id ? $teacherrole : $studentrole);
        groups_add_member($group, $userid);
    }
}
$sections = [];
foreach ([1,2] as $number) {
    $section = $DB->get_record('course_sections', ['course' => $release->id, 'section' => $number], '*', MUST_EXIST);
    $availability = $number === 2 ? json_encode(['op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => time()+DAYSECS]], 'showc' => [false]]) : null;
    course_update_section($release, $section, ['visible' => 0, 'availability' => $availability]);
    $sections[] = ['id' => (int)$section->id, 'original_availability' => $availability];
}
$g->create_module('page', ['course' => $release->id, 'section' => 1, 'name' => 'QA release sentinel', 'content' => 'Synthetic restricted unit sentinel']);
grade_category::fetch_course_category($course->id)->get_grade_item();
grade_regrade_final_grades($course->id);
rebuild_course_cache($course->id, true);
$firstsection = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1], MUST_EXIST);
$manifest = ['run' => $run, 'courseid' => (int)$course->id, 'categoryid' => (int)$category->id,
    'sectionid' => (int)$firstsection, 'groups' => array_map(static fn($v) => (int)$v->id, $groups),
    'users' => $users, 'trainer' => ['id' => (int)$trainer->id, 'username' => $trainer->username],
    'other' => ['id' => (int)$other->id, 'username' => $other->username],
    'none' => ['id' => (int)$none->id, 'username' => $none->username], 'fields' => $fields, 'modules' => $modules,
    'assignment' => (int)$assignment->cmid, 'forum' => (int)$forum->cmid, 'feedback' => (int)$feedback->cmid,
    'youtube' => (int)$youtube->cmid, 'catalog_mapping' => $mapping,
    'media' => ['cmid' => (int)$mediapage->cmid, 'contextid' => context_module::instance($mediapage->cmid)->id],
    'release' => ['courseid' => (int)$release->id, 'groups' => array_map(static fn($v) => (int)$v->id, $rgroups), 'sections' => $sections],
    'plugin_version' => get_config('local_asodeltolstaff','version'), 'course_hidden' => true, 'category_hidden' => true];
file_put_contents('/tmp/close19-fixture.json', json_encode($manifest, JSON_THROW_ON_ERROR));
chmod('/tmp/close19-fixture.json', 0600);
echo json_encode(['courseid' => $course->id, 'synthetic_users' => 53, 'catalog_items' => count($mapping),
    'plugin_version' => $manifest['plugin_version'], 'course_hidden' => true, 'category_hidden' => true]) . PHP_EOL;
