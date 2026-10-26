<?php
/** Independent Moodle DB/filestore read-back for QA350 content. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if($CFG->wwwroot!=='http://127.0.0.1:8054'||$CFG->dbname!=='close19'||$CFG->prefix!=='mdl_')throw new RuntimeException('close19 only');
$manifest=json_decode(file_get_contents('/tmp/close19-content350.json'),true,512,JSON_THROW_ON_ERROR);
$course=$DB->get_record('course',['id'=>$manifest['courseid']],'*',MUST_EXIST);
$category=$DB->get_record('course_categories',['id'=>$course->category],'*',MUST_EXIST);
if($course->visible||$category->visible)throw new RuntimeException('QA course/category visible');
$mapping=$manifest['mapping'];if(count($mapping)!==50||count(array_unique(array_column($mapping,'code')))!==50)throw new RuntimeException('Catalog mismatch');
$sections=[];
foreach($mapping as $item){
    $cm=$DB->get_record('course_modules',['id'=>$item['cmid']],'*',MUST_EXIST);
    if((int)$cm->course!==(int)$course->id)throw new RuntimeException('Wrong course module');
    $sections[$DB->get_field('course_sections','section',['id'=>$cm->section],MUST_EXIST)]++;
}
foreach(range(1,5) as $unit)if(($sections[$unit]??0)!==10)throw new RuntimeException("Unit $unit lacks ten items");
$feedback=$DB->get_record('course_modules',['id'=>$manifest['feedback_cmid']],'*',MUST_EXIST);
$gradecount=$DB->count_records('grade_items',['courseid'=>$course->id,'itemmodule'=>'feedback','iteminstance'=>$feedback->instance]);
if($gradecount!==0)throw new RuntimeException('Ungraded feedback appears in gradebook');
$learner=$DB->get_record('user',['username'=>'qa350_003'],'id',MUST_EXIST);
$feedbackcompleted=$DB->count_records('feedback_completed',['feedback'=>$feedback->instance,'userid'=>$learner->id]);
$glossarycount=0;
foreach($mapping as $item)if($item['type']==='glossary'){
    $instance=$DB->get_field('course_modules','instance',['id'=>$item['cmid']],MUST_EXIST);
    $glossarycount+=$DB->count_records('glossary_entries',['glossaryid'=>$instance]);
}
if($glossarycount<5)throw new RuntimeException('Glossary entries missing');
$storedfiles=0;$fileurls=[];
foreach(['material.xlsx','material.pptx','transcripcion.txt','referencias.txt'] as $name){
    $context=context_module::instance($manifest['auxiliary_resources'][$name]);
    if(!get_file_storage()->get_file($context->id,'mod_resource','content',0,'/',$name))throw new RuntimeException("Moodle file missing $name");
    $fileurls[$name]=moodle_url::make_pluginfile_url($context->id,'mod_resource','content',0,'/',$name)->out(false);
    $storedfiles++;
}
$pdfitem=current(array_filter($mapping,static fn($item)=>$item['code']==='UT1.05'));
$pdfcontext=context_module::instance($pdfitem['cmid']);
$fileurls['material.pdf']=moodle_url::make_pluginfile_url($pdfcontext->id,'mod_resource','content',0,'/','material.pdf')->out(false);
$docitem=current(array_filter($mapping,static fn($item)=>$item['code']==='UT1.06'));
$doccontext=context_module::instance($docitem['cmid']);
$fileurls['material.docx']=moodle_url::make_pluginfile_url($doccontext->id,'mod_resource','content',0,'/','material.docx')->out(false);
$result=['status'=>'PASS_REAL_LOCAL','courseid'=>(int)$course->id,'unit_component_counts'=>$sections,
    'catalog_items'=>50,'general_resources'=>count($manifest['general']),
    'glossary_entries'=>$glossarycount,'stored_auxiliary_files'=>$storedfiles,
    'fileurls'=>$fileurls,
    'feedback_grade_items'=>$gradecount,'feedback_completed_for_qa350_003'=>$feedbackcompleted,
    'course_hidden'=>true,'category_hidden'=>true,'checked_at'=>gmdate('c')];
echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
