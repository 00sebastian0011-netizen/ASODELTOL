<?php
/** Local-only isolated 350-person synthetic course and read-back assertions. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19' || $CFG->prefix !== 'mdl_') {
    throw new RuntimeException('Refusing anything except isolated close19.');
}
\core\session\manager::set_user(get_admin());
$g=testing_util::get_data_generator(); $password=trim(file_get_contents('/run/secrets/synthetic_password')); $marker='QA350-AF8';
$course=$DB->get_record('course',['shortname'=>$marker],'*',IGNORE_MISSING);
if (!$course) {
    $category=$g->create_category(['name'=>'QA350 isolated synthetic','visible'=>0]);
    $course=$g->create_course(['fullname'=>'QA350 AF8 synthetic population','shortname'=>$marker,'category'=>$category->id,'visible'=>0,'groupmode'=>SEPARATEGROUPS,'groupmodeforce'=>1]);
} else $category=$DB->get_record('course_categories',['id'=>$course->category],'*',MUST_EXIST);
if ($course->visible || $category->visible) throw new RuntimeException('Fixture course/category must remain hidden.');
$studentrole=$DB->get_field('role','id',['shortname'=>'student'],MUST_EXIST); $groups=[];
for($n=1;$n<=7;$n++) { $idnumber="$marker-G$n"; $group=$DB->get_record('groups',['courseid'=>$course->id,'idnumber'=>$idnumber],'*',IGNORE_MISSING); if(!$group)$group=$g->create_group(['courseid'=>$course->id,'name'=>"QA350 group $n",'idnumber'=>$idnumber]); $groups[$n]=$group; }
for($n=1;$n<=350;$n++) { $username=sprintf('qa350_%03d',$n); $user=$DB->get_record('user',['username'=>$username,'mnethostid'=>$CFG->mnet_localhost_id],'*',IGNORE_MISSING); if(!$user)$user=$g->create_user(['username'=>$username,'password'=>$password,'firstname'=>'Beneficiario','lastname'=>'Sintetico '.sprintf('%03d',$n),'email'=>$username.'@invalid.test','country'=>'CO']); $g->enrol_user($user->id,$course->id,$studentrole); $group=$groups[intdiv($n-1,50)+1]; if(!groups_is_member($group->id,$user->id))groups_add_member($group->id,$user->id); }
$assert=[];$all=[];
foreach($groups as $number=>$group) { $sql="SELECT u.id FROM {user} u JOIN {groups_members} gm ON gm.userid=u.id JOIN {user_enrolments} ue ON ue.userid=u.id JOIN {enrol} e ON e.id=ue.enrolid JOIN {role_assignments} ra ON ra.userid=u.id AND ra.roleid=? WHERE gm.groupid=? AND e.courseid=? AND u.username LIKE 'qa350\\_%' ESCAPE '\\\\' AND u.deleted=0"; $ids=array_map('intval',array_keys($DB->get_records_sql($sql,[$studentrole,$group->id,$course->id]))); if(count($ids)!==50)throw new RuntimeException("Group $number expected 50 active students, got ".count($ids)); $assert["group$number"]=count($ids); foreach($ids as $id){if(isset($all[$id]))throw new RuntimeException('Cross-group membership found');$all[$id]=true;} }
if(count($all)!==350)throw new RuntimeException('Expected exactly 350 unique beneficiaries.');
$out=['status'=>'PASS_REAL_LOCAL','courseid'=>(int)$course->id,'categoryid'=>(int)$category->id,'course_hidden'=>true,'category_hidden'=>true,'groups'=>$assert,'unique_beneficiaries'=>count($all),'isolation'=>'separate QA350 hidden course; prior QA19 fixtures untouched','created_at'=>gmdate('c')];
file_put_contents('/tmp/close19-bulk350.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod('/tmp/close19-bulk350.json',0600);echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
