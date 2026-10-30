<?php
/**
 * Functional QA for REQ-08-09 and REQ-16-03 in the disposable Moodle runner.
 * All identities and references are synthetic; this script refuses other DBs.
 */
define('CLI_SCRIPT', true);
require dirname(__DIR__, 2) . '/qa-moodle/config.php';

global $CFG, $DB;
require_once $CFG->dirroot . '/group/lib.php';
require_once $CFG->dirroot . '/user/lib.php';

if (getenv('ASODELTOL_QA_ONLY') !== 'isolated-gh-runner' ||
        $CFG->wwwroot !== 'http://127.0.0.1:8053' || $CFG->dbname !== 'asodeltolqa') {
    throw new RuntimeException('Refusing functional fixture outside isolated Moodle QA');
}
$ids = json_decode(file_get_contents(dirname(__DIR__, 2) . '/qa-ids.json'), true, 512, JSON_THROW_ON_ERROR);
$courseid = (int)$ids['course'];
$users = $ids['users'];
$groups = $ids['groups'];

function qa_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

function qa_synthetic_user(string $username): array {
    global $CFG;
    return [
        'username' => $username,
        'password' => 'QaIdentityOnly_2026!Pass',
        'firstname' => 'Identity',
        'lastname' => 'SyntheticQA',
        'email' => $username . '@example.test',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ];
}

// REQ-08-09: a real Moodle calendar event, exactly 60 minutes, persisted with
// group scope and an evidence reference visible only to authorised roles.
\core\session\manager::set_user(get_complete_user_data('id', (int)$users['trainer']));
$start = time() + 2 * HOURSECS;
$eventid = \local_asodeltolstaff\office_hours::create(
    $courseid, (int)$groups['g1'], (int)$users['trainer'], $start, $start + HOURSECS,
    'https://meet.example.test/qa-g01', 'https://evidence.example.test/qa-g01'
);
$event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
qa_assert((int)$event->timeduration === HOURSECS, 'REQ-08-09 persists an exact 60-minute Moodle event');
$trainerrows = \local_asodeltolstaff\office_hours::get_visible($courseid, (int)$users['trainer']);
qa_assert(count($trainerrows) === 1 && (int)reset($trainerrows)->groupid === (int)$groups['g1'],
    'REQ-08-09 trainer visibility is restricted to the assigned group');
$auditorrows = \local_asodeltolstaff\office_hours::get_visible($courseid, (int)$users['auditor']);
qa_assert(count($auditorrows) === 1 && reset($auditorrows)->evidenceref === 'https://evidence.example.test/qa-g01',
    'REQ-08-09 Interventoria can inspect group evidence');
try {
    \local_asodeltolstaff\office_hours::create(
        $courseid, (int)$groups['g1'], (int)$users['trainer'], $start + 3 * HOURSECS,
        $start + 3 * HOURSECS + 3599, '', 'https://evidence.example.test/too-short'
    );
    throw new RuntimeException('REQ-08-09 accepted a slot shorter than 60 minutes');
} catch (invalid_parameter_exception $expected) {
    qa_assert(true, 'REQ-08-09 rejects a slot shorter than 60 minutes');
}

// REQ-16-03: exercise create, import, assignment and all-or-nothing rollback
// through the production service class, with no real document values.
$created = \local_asodeltolstaff\identity_registry::create_user_with_document(
    qa_synthetic_user('qa_identity_create'), 'CC', 'QA-CREATE-0001'
);
qa_assert($DB->record_exists('local_asstaff_identity', ['userid' => $created->id, 'source' => 'create']),
    'REQ-16-03 create path reserves the synthetic document');
$imported = \local_asodeltolstaff\identity_registry::import_users([
    ['user' => qa_synthetic_user('qa_identity_import'), 'doctype' => 'CE', 'document' => 'QA-IMPORT-0001'],
]);
qa_assert(count($imported) === 1 && $DB->record_exists('local_asstaff_identity',
    ['userid' => $imported[0]->id, 'source' => 'import']), 'REQ-16-03 import path persists one identity');

$assignuserid = \core\user::create_user((object)qa_synthetic_user('qa_identity_assign'), true, true);
\local_asodeltolstaff\identity_registry::assign_group(
    (int)$assignuserid, (int)$groups['g2'], 'TI', 'QA-ASSIGN-0001'
);
qa_assert(groups_is_member((int)$groups['g2'], (int)$assignuserid) &&
    $DB->record_exists('local_asstaff_identity', ['userid' => $assignuserid, 'source' => 'assign']),
    'REQ-16-03 assignment atomically binds identity and group');

$beforeusers = $DB->count_records('user');
try {
    \local_asodeltolstaff\identity_registry::import_users([
        ['user' => qa_synthetic_user('qa_rollback_first'), 'doctype' => 'PPT', 'document' => 'QA-ROLLBACK-0001'],
        ['user' => qa_synthetic_user('qa_identity_create'), 'doctype' => 'PA', 'document' => 'QA-ROLLBACK-0002'],
    ]);
    throw new RuntimeException('REQ-16-03 import rollback case unexpectedly succeeded');
} catch (Throwable $expected) {
    qa_assert($DB->count_records('user') === $beforeusers &&
        !$DB->record_exists('user', ['username' => 'qa_rollback_first']),
        'REQ-16-03 failed import rolls back every user and document write');
}

// Prepare two independent existing users for the parallel workers.
$raceusers = [];
foreach (['a', 'b'] as $suffix) {
    $raceusers[] = \core\user::create_user((object)qa_synthetic_user('qa_identity_race_' . $suffix), true, true);
}
$ids['raceusers'] = $raceusers;
file_put_contents(dirname(__DIR__, 2) . '/qa-ids.json', json_encode($ids, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "PASS: functional fixture prepared two concurrent identity workers.\n";
