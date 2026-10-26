<?php
/** Permit only two synthetic QA350 accounts to exchange a test message. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19') {
    throw new RuntimeException('Isolated staging required');
}
$learner = $DB->get_record('user', ['username' => 'qa350_003'], 'id,username', MUST_EXIST);
$sena = $DB->get_record('user', ['username' => 'qa350_sena'], 'id,username', MUST_EXIST);
set_user_preference('message_blocknoncontacts', 0, $learner->id);
set_user_preference('message_blocknoncontacts', 0, $sena->id);
if (!\core_message\api::is_contact($learner->id, $sena->id)) {
    \core_message\api::add_contact($learner->id, $sena->id);
}
echo json_encode(['status' => 'READY_LOCAL_SYNTHETIC', 'learnerid' => (int)$learner->id,
    'senaid' => (int)$sena->id, 'contact' => \core_message\api::is_contact($learner->id, $sena->id)],
    JSON_THROW_ON_ERROR) . PHP_EOL;
