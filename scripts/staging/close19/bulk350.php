<?php
// Local-only synthetic population. Never point this script at production.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
\core\session\manager::set_user(get_admin());
$manifest = json_decode(file_get_contents('/tmp/close19-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$courseid = (int)$manifest['courseid'];
$course = get_course($courseid); $generator = testing_util::get_data_generator();
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$groups = $DB->get_records_list('groups', 'id', array_map('intval', $manifest['groups']));
$password = trim(file_get_contents('/run/secrets/synthetic_password')); $created=[];
for ($i=1; $i<=350; $i++) {
    $suffix = sprintf('bulk%03d', $i); $username = 'qa19_350_' . $suffix;
    $user = $DB->get_record('user', ['username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id]);
    if (!$user) {
        $user = $generator->create_user(['username'=>$username, 'password'=>$password,
            'firstname'=>'Beneficiario', 'lastname'=>'Sintetico '.$suffix,
            'email'=>$username.'@invalid.test', 'city'=>'Ciudad QA', 'country'=>'CO']);
    }
    if (!$DB->record_exists('user_enrolments', ['userid'=>$user->id])) {
        $generator->enrol_user($user->id, $courseid, $studentrole);
    }
    $groupindex = intdiv($i-1, 50)+1; $groupid = (int)$manifest['groups'][$groupindex-1];
    if (!groups_is_member($groupid, $user->id)) groups_add_member($groupid, $user->id);
    $created[]=['id'=>(int)$user->id,'username'=>$username,'group'=>$groupindex];
}
$out=['courseid'=>$courseid,'users'=>$created,'count'=>count($created),'groups'=>7,
    'population'=>'350 synthetic beneficiaries, 50 per hidden group','environment'=>'close19-local-only',
    'created_at'=>gmdate('c')];
file_put_contents('/tmp/close19-bulk350.json', json_encode($out, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['status'=>'PASS_REAL_LOCAL','count'=>count($created),'groups'=>7]).PHP_EOL;
