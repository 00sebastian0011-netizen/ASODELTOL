<?php
/** Five synthetic course profiles and seven group scoped trainers, close19 only. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19' || $CFG->prefix !== 'mdl_') {
    throw new RuntimeException('Isolated close19 required');
}
\core\session\manager::set_user(get_admin());
$generator=testing_util::get_data_generator();
$course=$DB->get_record('course',['shortname'=>'QA350-AF8'],'*',MUST_EXIST);
$category=$DB->get_record('course_categories',['id'=>$course->category],'*',MUST_EXIST);
if ($course->visible || $category->visible) throw new RuntimeException('Synthetic course/category must be hidden');
$context=context_course::instance($course->id);
$password=trim(file_get_contents('/run/secrets/synthetic_password'));
$makeuser=static function(string $name) use ($generator,$DB,$CFG,$password): stdClass {
    $user=$DB->get_record('user',['username'=>$name,'mnethostid'=>$CFG->mnet_localhost_id],'*',IGNORE_MISSING);
    if (!$user) $user=$generator->create_user(['username'=>$name,'password'=>$password,
        'firstname'=>'Synthetic','lastname'=>$name,'email'=>$name.'@invalid.test']);
    return $user;
};
$groups=[];$trainers=[];
for ($n=1;$n<=7;$n++) {
    $group=$DB->get_record('groups',['courseid'=>$course->id,'idnumber'=>"QA350-AF8-G$n"],'*',MUST_EXIST);
    $groups[$n]=$group;
    $trainer=$makeuser("qa350_trainer$n");
    if (!is_enrolled($context,$trainer->id)) $generator->enrol_user($trainer->id,$course->id,'teacher');
    if (!groups_is_member($group->id,$trainer->id)) groups_add_member($group->id,$trainer->id);
    $trainers[$n]=$trainer;
}
$profiles=[];
foreach (['interventoria'=>2,'sena'=>1] as $name=>$groupnumber) {
    $short='qa350_'.$name;
    $role=$DB->get_record('role',['shortname'=>$short],'*',IGNORE_MISSING);
    $roleid=$role ? $role->id : create_role('QA350 '.$name,$short,'Local synthetic observer; no institutional approval');
    foreach (['moodle/course:view','moodle/course:viewhiddencourses','moodle/grade:viewall',
        'local/asodeltolstaff:viewsessions','local/asodeltolstaff:export'] as $capability) {
        assign_capability($capability,CAP_ALLOW,$roleid,$context->id);
    }
    $observer=$makeuser('qa350_'.$name);
    if (!is_enrolled($context,$observer->id)) $generator->enrol_user($observer->id,$course->id,$roleid);
    if (!groups_is_member($groups[$groupnumber]->id,$observer->id)) groups_add_member($groups[$groupnumber]->id,$observer->id);
    $profiles[$name]=['id'=>(int)$observer->id,'roleid'=>(int)$roleid,'group'=>$groupnumber];
}
assign_capability('moodle/course:viewhiddencourses',CAP_ALLOW,$DB->get_field('role','id',['shortname'=>'student'],MUST_EXIST),$context->id);
assign_capability('moodle/course:viewhiddencourses',CAP_ALLOW,$DB->get_field('role','id',['shortname'=>'teacher'],MUST_EXIST),$context->id);
foreach (['residencefield'=>'qa_residencia','characterizationfield'=>'qa_caracterizacion'] as $setting=>$shortname) {
    $field=$DB->get_record('user_info_field',['shortname'=>$shortname],'*',MUST_EXIST);
    if ((int)$field->visible !== (int)PROFILE_VISIBLE_PRIVATE) throw new RuntimeException('QA profile field must be private');
    set_config($setting,$shortname,'local_asodeltolstaff');
}
$result=['status'=>'PASS_REAL_LOCAL','courseid'=>(int)$course->id,'profiles'=>['administrator'=>'qa_admin',
    'trainer'=>'teacher','beneficiary'=>'student','interventoria'=>'qa350_interventoria','sena'=>'qa350_sena'],
    'groups'=>array_map(static fn($g)=>(int)$g->id,$groups),'trainers'=>array_map(static fn($u)=>(int)$u->id,$trainers),
    'observers'=>$profiles,'course_hidden'=>true,'category_hidden'=>true,'created_at'=>gmdate('c')];
file_put_contents('/tmp/close19-roles350.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['status'=>'PASS_REAL_LOCAL','trainers'=>count($trainers),'observerProfiles'=>count($profiles),'courseid'=>$course->id]) . PHP_EOL;
