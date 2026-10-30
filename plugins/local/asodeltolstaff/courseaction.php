<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$course = get_course($id);
$context = context_course::instance($id);
require_login($course);
require_capability('moodle/course:update', $context);
$url = new moodle_url('/local/asodeltolstaff/courseaction.php', ['id' => $id]);
$PAGE->set_url($url); $PAGE->set_context($context); $PAGE->set_course($course);
$PAGE->set_title(get_string('trainingactionform', 'local_asodeltolstaff'));
$form = new \local_asodeltolstaff\local\form\course_action_form($url);
if ($form->is_cancelled()) { redirect(new moodle_url('/course/view.php', ['id' => $id])); }
if ($data = $form->get_data()) {
    $data->summary = $data->summary_editor['text'];
    \local_asodeltolstaff\course_action::save($data);
    redirect($url, get_string('changessaved'));
}
$values = (object)['id'=>$id, 'idnumber'=>$course->idnumber, 'fullname'=>$course->fullname,
    'summary_editor'=>['text'=>$course->summary, 'format'=>$course->summaryformat], 'visible'=>$course->visible,
    'startdate'=>$course->startdate, 'enddate'=>$course->enddate, 'lang'=>$course->lang];
$handler = \core_customfield\handler::get_handler('core_course', 'course');
foreach ($handler->get_instance_data($id, true) as $field) {
    if ($field->get_field()->get('shortname') === 'asodeltol_adminroleid') { $values->adminroleid = $field->get_value(); }
    if ($field->get_field()->get('shortname') === 'asodeltol_auditorroleid') { $values->auditorroleid = $field->get_value(); }
}
$form->set_data($values);
echo $OUTPUT->header(); echo $OUTPUT->heading(get_string('trainingactionform', 'local_asodeltolstaff'));
$form->display(); echo $OUTPUT->footer();
