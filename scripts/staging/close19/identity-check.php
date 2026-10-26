<?php
/** Independent read-back of profile data written by browser forms. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19' || $CFG->prefix !== 'mdl_') {
    throw new RuntimeException('close19 isolated instance required');
}
$course = $DB->get_record('course', ['shortname' => 'QA350-AF8'], '*', MUST_EXIST);
$category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
if ($course->visible || $category->visible) {
    throw new RuntimeException('QA350 course/category must remain hidden');
}
$users = [];
foreach (['qa350_003', 'qa350_004'] as $name) {
    $user = $DB->get_record('user', ['username' => $name], 'id,username,firstname,lastname,email', MUST_EXIST);
    $data = profile_user_record($user->id, false);
    if (empty($data->qa_documento)) {
        throw new RuntimeException('Document missing for ' . $name);
    }
    $users[] = ['id' => (int)$user->id, 'username' => $name,
        'document_hash' => hash('sha256', $data->qa_documento),
        'residence_present' => !empty($data->qa_residencia),
        'characterization_present' => !empty($data->qa_caracterizacion),
        'name_present' => $user->firstname !== '' && $user->lastname !== '',
        'email_present' => str_ends_with($user->email, '@invalid.test')];
}
if ($users[0]['document_hash'] === $users[1]['document_hash']) {
    throw new RuntimeException('Duplicate documents persisted');
}
$field = $DB->get_record('user_info_field', ['shortname' => 'qa_documento'], '*', MUST_EXIST);
if ((int)$field->forceunique !== 1 || (int)$field->visible !== (int)PROFILE_VISIBLE_PRIVATE) {
    throw new RuntimeException('Document field not unique and private: unique=' . $field->forceunique
        . ', visible=' . $field->visible . ', expected=' . PROFILE_VISIBLE_PRIVATE);
}
echo json_encode(['status'=>'PASS_REAL_LOCAL', 'courseid'=>(int)$course->id,
    'users'=>$users, 'unique_documents'=>true, 'field_private'=>true,
    'checked_at'=>gmdate('c')], JSON_THROW_ON_ERROR) . PHP_EOL;
