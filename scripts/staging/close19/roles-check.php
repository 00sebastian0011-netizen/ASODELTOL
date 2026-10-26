<?php
/** Independent per-user capability and group data checks using Moodle APIs. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/group/lib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19' || $CFG->prefix !== 'mdl_') {
    throw new RuntimeException('close19 only');
}
$course=$DB->get_record('course',['shortname'=>'QA350-AF8'],'*',MUST_EXIST);
$category=$DB->get_record('course_categories',['id'=>$course->category],'*',MUST_EXIST);
if ($course->visible || $category->visible) throw new RuntimeException('Course/category not hidden');
$context=context_course::instance($course->id);
$learner1=$DB->get_record('user',['username'=>'qa350_003'],'*',MUST_EXIST);
$learner2=$DB->get_record('user',['username'=>'qa350_051'],'*',MUST_EXIST);
$cases=[];
foreach (['qa350_sena'=>$learner1,'qa350_interventoria'=>$learner2,'qa350_trainer1'=>$learner1,
    'qa350_trainer2'=>$learner2] as $username=>$ingroup) {
    $actor=$DB->get_record('user',['username'=>$username],'*',MUST_EXIST);
    \core\session\manager::set_user($actor);
    $groups=\local_asodeltolstaff\manager::get_available_groups($course->id);
    if (count($groups)!==1) throw new RuntimeException("$username can see ".count($groups).' groups');
    $rows=\local_asodeltolstaff\report_manager::rows($course->id,'participants');
    if (count($rows)!==50) throw new RuntimeException("$username has ".count($rows).' participants, expected 50');
    $names=array_column($rows,'username');
    if (!in_array($ingroup->username,$names,true)) throw new RuntimeException("$username cannot see allowed learner");
    $outgroup=$ingroup->id===$learner1->id?$learner2:$learner1;
    if (in_array($outgroup->username,$names,true)) throw new RuntimeException("$username sees other group");
    $summary=\local_asodeltolstaff\progress_report::summary($course->id,$ingroup->id);
    if ((int)$summary['userid']!==(int)$ingroup->id) throw new RuntimeException('Own-group progress mismatch');
    $denied=false;
    try {\local_asodeltolstaff\progress_report::summary($course->id,$outgroup->id);}
    catch (\moodle_exception $e) {$denied=true;}
    if (!$denied) throw new RuntimeException("$username can read other-group progress");
    foreach (['moodle/grade:edit','moodle/role:assign','local/asodeltolstaff:validate'] as $writecap) {
        if (str_contains($username,'trainer')) continue;
        if (has_capability($writecap,$context)) throw new RuntimeException("$username has excessive $writecap");
    }
    $cases[$username]=['groupid'=>(int)array_key_first($groups),'participants'=>count($rows),
        'own_progress'=>true,'foreign_progress_denied'=>true,'foreign_participant_excluded'=>true];
}
\core\session\manager::set_user($learner1);
$studentdenied=false;
try {\local_asodeltolstaff\report_manager::rows($course->id,'participants');}
catch (\moodle_exception $e) {$studentdenied=true;}
if (!$studentdenied) throw new RuntimeException('Student can export administrative participant report');
$result=['status'=>'PASS_REAL_LOCAL','courseid'=>(int)$course->id,'learnerids'=>['group1'=>(int)$learner1->id,
    'group2'=>(int)$learner2->id],'cases'=>$cases,'student_export_denied'=>true,
    'checked_at'=>gmdate('c')];
file_put_contents('/tmp/close19-roles-check.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['status'=>'PASS_REAL_LOCAL','scopedActors'=>count($cases),'studentsPerGroup'=>50,'studentExportDenied'=>true]) . PHP_EOL;
