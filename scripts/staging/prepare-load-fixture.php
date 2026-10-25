<?php
// Local-only synthetic load fixture; never production.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gradelib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8053' || $CFG->dbname !== 'moodle') {
    throw new RuntimeException('Unexpected staging installation');
}
\core\session\manager::set_user(get_admin());
$idnumber = 'ASODELTOL-QA-LOAD50-V3';
if ($DB->record_exists('course', ['idnumber' => $idnumber])) {
    throw new RuntimeException('Existing fixture preserved; use its private manifest');
}
$category = \core_course_category::create(['name' => 'QA sintética concurrencia', 'visible' => 0]);
$course = create_course((object) ['category' => $category->id, 'fullname' => 'QA sintética 50 beneficiarios',
    'shortname' => $idnumber, 'idnumber' => $idnumber, 'visible' => 0, 'format' => 'topics', 'numsections' => 1]);
$context = context_course::instance($course->id);
// Initialise Moodle's lazy grade tree before concurrent first visits.
$gradecategory = grade_category::fetch_course_category($course->id);
$gradecategory->get_grade_item();
$role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
// Course-only permission lets synthetic learners test the deliberately hidden fixture.
assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $role->id, $context->id);
$manual = enrol_get_plugin('manual');
$instances = enrol_get_instances($course->id, false);
$enrolid = null;
foreach ($instances as $instance) {
    if ($instance->enrol === 'manual') {
        $enrolid = $instance->id;
        break;
    }
}
if (!$enrolid) {
    $enrolid = $manual->add_instance($course);
}
$instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
$path = '/tmp/asodeltol-load50-private.json';
$previous = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : null;
$password = $previous['password'] ?? ('Qa!7' . bin2hex(random_bytes(24)));
$users = [];
for ($i = 1; $i <= 50; $i++) {
    $username = 'qa_load50_' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
    $existing = $DB->get_record('user', ['username' => $username]);
    if ($existing) {
        $documented = $previous['users'][$i - 1] ?? [];
        if (($documented['username'] ?? '') !== $username || (int)($documented['id'] ?? 0) !== (int)$existing->id
                || $existing->email !== $username . '@example.invalid') {
            throw new RuntimeException('Undocumented identity collision');
        }
        $id = $existing->id;
    } else {
        $id = user_create_user((object) ['username' => $username, 'password' => $password, 'auth' => 'manual',
        'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'firstname' => 'QA sintética',
        'lastname' => sprintf('%02d', $i), 'email' => $username . '@example.invalid']);
    }
    $manual->enrol_user($instance, $id, $role->id);
    $users[] = ['id' => $id, 'username' => $username];
}
$private = ['courseid' => $course->id, 'categoryid' => $category->id, 'users' => $users, 'password' => $password];
if (is_file($path)) {
    $previouspath = $path . '.' . time() . '.previous';
    copy($path, $previouspath);
    chmod($previouspath, 0600);
}
file_put_contents($path, json_encode($private));
chmod($path, 0600);
echo json_encode(['courseid' => $course->id, 'synthetic_users' => count($users), 'course_hidden' => true]);
