<?php
/**
 * QA ONLY: generate synthetic users, roles, course and two groups in a clean
 * disposable Moodle instance, never against a production database.
 */
define('CLI_SCRIPT', true);
require dirname(__DIR__, 2) . '/qa-moodle/config.php';

global $CFG, $DB;
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/group/lib.php';
require_once $CFG->libdir . '/enrollib.php';
require_once $CFG->libdir . '/accesslib.php';
require_once $CFG->libdir . '/gradelib.php';

if (getenv('ASODELTOL_QA_ONLY') !== 'isolated-gh-runner' ||
        $CFG->wwwroot !== 'http://127.0.0.1:8053' ||
        $CFG->dbname !== 'asodeltolqa') {
    throw new RuntimeException('Refusing fixture outside isolated Moodle QA');
}

$password = getenv('QA_TEST_PASSWORD');
if (!$password) {
    throw new RuntimeException('Missing synthetic QA_TEST_PASSWORD');
}

set_config('forceloginforprofiles', 1);
set_config('forcelogin', 1);
set_config('debug', 0);
set_config('debugdisplay', 0);
set_config('hiddenuserfields', 'city,country');

function qa_course(string $shortname): stdClass {
    return create_course((object)[
        'shortname' => $shortname,
        'fullname' => 'ASODELTOL synthetic privacy QA ' . $shortname,
        'category' => 1,
        'format' => 'topics',
        'visible' => 1,
        'groupmode' => SEPARATEGROUPS,
        'groupmodeforce' => 1,
    ]);
}

function qa_role(string $shortname): int {
    $name = strtoupper(str_replace('_', ' ', $shortname));
    $roleid = create_role($name, $shortname, 'Synthetic role for isolated privacy QA');
    $system = context_system::instance();
    foreach (['moodle/course:view', 'moodle/course:viewparticipants',
              'moodle/user:viewdetails'] as $capability) {
        assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
    }
    if (in_array($shortname, ['asodeltol_auditor', 'sena'], true)) {
        foreach (['moodle/user:viewhiddendetails', 'moodle/site:accessallgroups',
                  'moodle/grade:viewall', 'local/asodeltolstaff:viewsessions',
                  'local/asodeltolstaff:viewall', 'local/asodeltolstaff:viewevidence'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
    }
    if ($shortname === 'asodeltol_capacitador') {
        foreach (['moodle/grade:viewall', 'moodle/grade:manage',
                  'local/asodeltolstaff:viewsessions', 'local/asodeltolstaff:manageown',
                  'local/asodeltolstaff:viewevidence'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
    }
    // Supervisory roles are read-only in this QA contract. Explicit prohibitions
    // make the negative HTTP cases independent of Moodle archetype defaults.
    if (in_array($shortname, ['asodeltol_auditor', 'sena'], true)) {
        foreach (['moodle/grade:manage', 'moodle/grade:edit',
                  'local/asodeltolstaff:manageown', 'local/asodeltolstaff:export'] as $capability) {
            assign_capability($capability, CAP_PROHIBIT, $roleid, $system->id, true);
        }
    }
    return $roleid;
}

function qa_user(string $username, string $firstname, string $city): int {
    global $password, $CFG;
    return \core\user::create_user((object)[
        'username' => $username,
        'password' => $password,
        'firstname' => $firstname,
        'lastname' => 'SyntheticQA',
        'email' => $username . '@example.test',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'city' => $city,
        'country' => 'CO',
        'maildisplay' => 1,
        'description' => 'QA_ONLY_PRIVACY_MARKER_' . $username,
    ], true, true);
}

function qa_enrol(stdClass $course, int $userid, int $roleid): void {
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
        $instance = $GLOBALS['DB']->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }
    $manual->enrol_user($instance, $userid, $roleid);
}

$course = qa_course('QA_PROFILE_G1_G2');
$outside = qa_course('QA_PROFILE_OUTSIDE');
$g1 = groups_create_group((object)['courseid' => $course->id, 'name' => 'G01']);
$g2 = groups_create_group((object)['courseid' => $course->id, 'name' => 'G02']);

$student = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$trainerrole = qa_role('asodeltol_capacitador');
$auditorrole = qa_role('asodeltol_auditor');
$senarole = qa_role('sena');

$users = [
    'benefg1' => qa_user('qa_benef_g1', 'BeneficiarioPrimero', 'CitySecretG01'),
    'benefg2' => qa_user('qa_benef_g2', 'BeneficiarioSegundo', 'CitySecretG02'),
    'benefoutside' => qa_user('qa_benef_outside', 'BeneficiarioExterior', 'CitySecretExterior'),
    'trainer' => qa_user('qa_trainer', 'CapacitadorPrimero', 'CitySecretTrainer'),
    'auditor' => qa_user('qa_auditor', 'InterventoriaPrimera', 'CitySecretAuditor'),
    'sena' => qa_user('qa_sena', 'SenaPrimera', 'CitySecretSena'),
];

qa_enrol($course, $users['benefg1'], (int)$student->id);
qa_enrol($course, $users['benefg2'], (int)$student->id);
qa_enrol($outside, $users['benefoutside'], (int)$student->id);
qa_enrol($course, $users['trainer'], $trainerrole);
qa_enrol($course, $users['auditor'], $auditorrole);
qa_enrol($course, $users['sena'], $senarole);
groups_add_member($g1, $users['benefg1']);
groups_add_member($g2, $users['benefg2']);
groups_add_member($g1, $users['trainer']);

// Synthetic grade values exercise Moodle's real gradebook without asserting
// any institutional evaluation rule or using real learner data.
$gradeitem = [
    'itemname' => 'QA_ONLY_SYNTHETIC_GRADE',
    'gradetype' => GRADE_TYPE_VALUE,
    'grademin' => 0,
    'grademax' => 100,
];
$graderesult = grade_update('local_asodeltolstaff', (int) $course->id, 'local',
    'asodeltolstaff', 0, 0, [$users['benefg1'] => (object) [
        'userid' => (int) $users['benefg1'],
        'rawgrade' => 87,
    ]], $gradeitem);
if ($graderesult !== GRADE_UPDATE_OK) {
    throw new RuntimeException('Unable to seed synthetic Moodle grade');
}

// A plugin session with a harmless example.test reference is the synthetic
// evidence. Capacitador can only see G01; oversight roles can read all groups.
$now = time();
$sessionids = [];
foreach ([[$g1, 'QA_EVIDENCE_G01'], [$g2, 'QA_EVIDENCE_G02']] as [$groupid, $marker]) {
    $sessionids[] = $DB->insert_record('local_asstaff_session', (object) [
        'courseid' => (int) $course->id,
        'sectionnum' => 1,
        'groupid' => (int) $groupid,
        'trainerid' => (int) $users['trainer'],
        'sessioncode' => $marker,
        'scheduledstart' => $now + 3600,
        'scheduledend' => $now + 7200,
        'reportedstart' => $now + 3600,
        'reportedend' => $now + 6900,
        'validationstatus' => 'pending',
        'evidenceref' => 'https://example.test/' . strtolower($marker),
        'createdby' => (int) $users['trainer'],
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

// Confirm synthetic credentials are valid before opening the HTTP session.
$manualauth = get_auth_plugin('manual');
if (!$manualauth->user_login('qa_benef_g1', $password)) {
    throw new RuntimeException('QA manual-auth password validation failed for synthetic user');
}
echo 'PASS: synthetic manual-auth password validated locally.\\n';

// Do not print email addresses, password hashes or personal records.
// This file contains only synthetic IDs in an ephemeral GitHub runner.
file_put_contents(dirname(__DIR__, 2) . '/qa-ids.json', json_encode([
    'course' => $course->id, 'outsidecourse' => $outside->id,
    'users' => $users, 'sessions' => $sessionids,
], JSON_PRETTY_PRINT));
echo 'PASS: isolated synthetic Moodle roles, enrolments and groups created.\n';
