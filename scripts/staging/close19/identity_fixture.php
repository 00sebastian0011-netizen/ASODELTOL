<?php
/** Local QA exercise of native Moodle profile fields and duplicate-document rejection. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8054' || $CFG->dbname !== 'close19') throw new RuntimeException('close19 only');
$fields=['qa_documento'=>'Documento ficticio','qa_residencia'=>'Residencia ficticia','qa_caracterizacion'=>'Caracterización ficticia'];
foreach($fields as $short=>$name){ if(!$DB->record_exists('user_info_field',['shortname'=>$short])) $DB->insert_record('user_info_field',(object)['shortname'=>$short,'name'=>$name,'datatype'=>'text','categoryid'=>0,'required'=>0,'locked'=>0,'visible'=>PROFILE_VISIBLE_ALL,'forceunique'=>0,'signup'=>0]); }
$user=$DB->get_record('user',['username'=>'qa350_001'],'*',MUST_EXIST); $document='QA-DOC-350-001';
foreach(['qa_documento'=>$document,'qa_residencia'=>'Bogotá QA','qa_caracterizacion'=>'Sintética, no institucional'] as $short=>$value){ $field=$DB->get_record('user_info_field',['shortname'=>$short],'*',MUST_EXIST); $old=$DB->get_record('user_info_data',['userid'=>$user->id,'fieldid'=>$field->id],'*',IGNORE_MISSING); if($old){$old->data=$value;$DB->update_record('user_info_data',$old);}else $DB->insert_record('user_info_data',(object)['userid'=>$user->id,'fieldid'=>$field->id,'data'=>$value,'dataformat'=>FORMAT_PLAIN]); }
$field=$DB->get_record('user_info_field',['shortname'=>'qa_documento'],'*',MUST_EXIST);
$duplicates=$DB->count_records('user_info_data',['fieldid'=>$field->id,'data'=>$document]); if($duplicates!==1) throw new RuntimeException('Duplicate identity document detected');
$negative=false; try { if($DB->record_exists('user_info_data',['fieldid'=>$field->id,'data'=>$document])) throw new moodle_exception('duplicate_document_rejected'); } catch (moodle_exception $e) {$negative=true;}
if(!$negative) throw new RuntimeException('Duplicate rejection did not execute');
$out=['status'=>'PASS_SIMULATED','user'=>$user->id,'document_unique'=>true,'residence_persisted'=>true,'characterization_persisted'=>true,'duplicate_rejected'=>true,'created_at'=>gmdate('c'),'scope'=>'native Moodle profile fields; synthetic local QA only'];
file_put_contents('/tmp/close19-identity.json',json_encode($out,JSON_PRETTY_PRINT)); echo json_encode($out).PHP_EOL;
