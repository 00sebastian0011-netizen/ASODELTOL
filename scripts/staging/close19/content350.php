<?php
/** Synthetic AF8 catalog and rich assets in the isolated QA350 course. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19' || $CFG->prefix !== 'mdl_') {
    throw new RuntimeException('Isolated close19 required');
}
\core\session\manager::set_user(get_admin());
$course=$DB->get_record('course',['shortname'=>'QA350-AF8'],'*',MUST_EXIST);
$category=$DB->get_record('course_categories',['id'=>$course->category],'*',MUST_EXIST);
if ($course->visible || $category->visible) throw new RuntimeException('Synthetic course/category must remain hidden');
$catalog=json_decode(file_get_contents('/tmp/close19-catalog350.json'),true,512,JSON_THROW_ON_ERROR);
$assets='/tmp/close19-media350';
foreach (['material.pdf','material.docx','material.xlsx','material.pptx','test-720.mp4','test-1080.mp4',
    'audio.mp3','captions-es.vtt','transcripcion.txt','infografia.png','referencias.txt'] as $name) {
    if (!is_file("$assets/$name")) throw new RuntimeException("Missing synthetic asset $name");
}
$generator=testing_util::get_data_generator();
course_create_sections_if_missing($course,[1,2,3,4,5]);
$templates=array_column($catalog['component_templates'],null,'template_id');
$module=function(string $kind,string $name,int $section,array $options=[]) use($generator,$DB,$course): stdClass {
    $old=$DB->get_record($kind,['course'=>$course->id,'name'=>$name],'*',IGNORE_MISSING);
    if($old){$cm=get_coursemodule_from_instance($kind,$old->id,$course->id,false,MUST_EXIST);return (object)['id'=>$old->id,'cmid'=>$cm->id];}
    return $generator->create_module($kind,['course'=>$course->id,'section'=>$section,'name'=>$name,
        'intro'=>'QA synthetic content only; no academic approval','groupmode'=>SEPARATEGROUPS]+$options);
};
$store=function(int $cmid,string $component,string $area,string $filename) use($assets): void {
    $context=context_module::instance($cmid);$storage=get_file_storage();
    if(!$storage->get_file($context->id,$component,$area,0,'/',$filename)) {
        $storage->create_file_from_pathname(['contextid'=>$context->id,'component'=>$component,
            'filearea'=>$area,'itemid'=>0,'filepath'=>'/','filename'=>$filename],"$assets/$filename");
    }
};
$mapping=[];
foreach($catalog['units'] as $unit){
    $section=$DB->get_record('course_sections',['course'=>$course->id,'section'=>$unit['position']],'*',MUST_EXIST);
    course_update_section($course,$section,['name'=>$unit['code'].' QA sintética',
        'summary'=>'Contenido de prueba generado; los resultados de aprendizaje institucionales siguen pendientes.']);
    foreach($unit['components'] as $item){
        $template=$templates[$item['template_id']];
        $kind=$template['selected_moodle_type']??($item['template_id']==='C06'?'resource':'url');
        $name='QA350 '.$item['code'].' '.$template['title'];
        $base=['content'=>'<p>Objetivo técnico: abrir este componente de QA.</p><p>Orden: lectura, recurso y actividad.</p>'.
            '<p>Material sintético sin resultados académicos aprobados.</p><p>Licencia del fixture: CC0.</p>',
            'contentformat'=>FORMAT_HTML];
        if($kind==='page')$options=$base;
        else if($kind==='resource')$options=['display'=>6];
        else if($kind==='url')$options=['externalurl'=>'https://www.youtube.com/watch?v=M7lc1UVf-VE','display'=>5];
        else $options=[];
        $created=$module($kind,$name,(int)$unit['position'],$options);
        if($kind==='resource')$store($created->cmid,'mod_resource','content',
            $item['template_id']==='C06'?'material.docx':'material.pdf');
        if($kind==='glossary'){
            $existing=$DB->record_exists('glossary_entries',['glossaryid'=>$created->id,'concept'=>'Término sintético']);
            if(!$existing)$generator->get_plugin_generator('mod_glossary')->create_content((object)['id'=>$created->id],
                ['concept'=>'Término sintético','definition'=>'Entrada de QA; no es un concepto académico oficial.']);
        }
        if($kind==='quiz' && !$DB->record_exists('quiz_slots',['quizid'=>$created->id])){
            $qg=$generator->get_plugin_generator('core_question');
            $qcategory=$qg->create_question_category(['contextid'=>context_course::instance($course->id)->id]);
            $question=$qg->create_question('truefalse',null,['category'=>$qcategory->id,'name'=>'Pregunta sintética QA']);
            quiz_add_quiz_question($question->id,$created);
        }
        $mapping[]=['code'=>$item['code'],'type'=>$kind,'cmid'=>(int)$created->cmid];
    }
}
$general=[];
foreach($catalog['general_resources'] as $item){
    $kind=$item['selected_moodle_type']?:'page';
    $options=$kind==='forum'?[]:['content'=>'<p>QA general sintético: '.$item['code'].'</p>'.
        '<p>Este recurso sólo valida navegación; no es material institucional.</p>','contentformat'=>FORMAT_HTML];
    $created=$module($kind,'QA350 general '.$item['code'].' (sintético)',0,$options);
    $general[]=['code'=>$item['code'],'cmid'=>(int)$created->cmid,'type'=>$kind];
}
$extra=[];
foreach(['material.xlsx','material.pptx','transcripcion.txt','referencias.txt'] as $name){
    $resource=$module('resource','QA350 auxiliar '.$name,1,['display'=>6]);
    $store($resource->cmid,'mod_resource','content',$name);
    $extra[$name]=(int)$resource->cmid;
}
$video=$module('page','QA350 video 720p y 1080p subtitulado',1,['content'=>
    '<p>Videos H.264/AAC y subtítulos sintéticos en español.</p>'.
    '<video controls playsinline width="640"><source src="@@PLUGINFILE@@/test-720.mp4" type="video/mp4">'.
    '<track src="@@PLUGINFILE@@/captions-es.vtt" kind="subtitles" srclang="es" label="Español" default></video>'.
    '<video controls playsinline width="640"><source src="@@PLUGINFILE@@/test-1080.mp4" type="video/mp4">'.
    '<track src="@@PLUGINFILE@@/captions-es.vtt" kind="subtitles" srclang="es" label="Español" default></video>'.
    '<audio controls src="@@PLUGINFILE@@/audio.mp3"></audio>', 'contentformat'=>FORMAT_HTML]);
foreach(['test-720.mp4','test-1080.mp4','captions-es.vtt','audio.mp3'] as $name)$store($video->cmid,'mod_page','content',$name);
$videorecord=$DB->get_record('page',['id'=>$video->id],'*',MUST_EXIST);
if(!str_contains($videorecord->content,'/local/asodeltolstaff/media.php?')){
    foreach(['test-720.mp4','test-1080.mp4'] as $filename){
        $link=new moodle_url('/local/asodeltolstaff/media.php',
            ['cmid'=>$video->cmid,'video'=>$filename,'caption'=>'captions-es.vtt']);
        $videorecord->content.='<p><a href="'.$link->out(false).'">Reproducir '.$filename.' con subtítulos en español</a></p>';
    }
    $DB->update_record('page',$videorecord);
}
$infographic=$module('page','QA350 infografía legible de prueba',1,['content'=>
    '<p>Objetivo QA: verificar lectura, secuencia y resolución.</p><img alt="Infografía sintética ordenada en cinco pasos" '.
    'src="@@PLUGINFILE@@/infografia.png">','contentformat'=>FORMAT_HTML]);
$store($infographic->cmid,'mod_page','content','infografia.png');
$feedback=$module('feedback','QA350 actividad interactiva sin nota',1,['anonymous'=>2]);
if(!$DB->record_exists('feedback_item',['feedback'=>$feedback->id])){
    $generator->get_plugin_generator('mod_feedback')->create_item_multichoice((object)['id'=>$feedback->id],
        ['name'=>'Pregunta QA de selección','label'=>'qa350','values'=>"Opción sintética A\nOpción sintética B",'required'=>1]);
}
$assignment=$module('assign','QA350 entrega de práctica sintética',1,['grade'=>0,
    'assignsubmission_file_enabled'=>1,'assignsubmission_onlinetext_enabled'=>1,
    'assignfeedback_comments_enabled'=>1]);
rebuild_course_cache($course->id,true);
$result=['status'=>'LOADED_LOCAL','courseid'=>(int)$course->id,'units'=>count($catalog['units']),
    'catalog_items'=>count($mapping),'general_resources'=>count($general),'mapping'=>$mapping,
    'general'=>$general,'auxiliary_resources'=>$extra,'video_cmid'=>(int)$video->cmid,
    'infographic_cmid'=>(int)$infographic->cmid,'feedback_cmid'=>(int)$feedback->cmid,
    'assignment_cmid'=>(int)$assignment->cmid,'course_hidden'=>true,'category_hidden'=>true,
    'created_at'=>gmdate('c')];
file_put_contents('/tmp/close19-content350.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['status'=>'LOADED_LOCAL','units'=>$result['units'],'catalog_items'=>$result['catalog_items'],
    'general_resources'=>$result['general_resources'],'auxiliary_resources'=>count($extra)]) . PHP_EOL;
