<?php
// Native enrollment and completion report selection.
require_once('../../config.php');
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_course_login($course);
require_capability('local/asodeltolstaff:export', $context);
$PAGE->set_url(new moodle_url('/local/asodeltolstaff/reports.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('additionalreports', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('additionalreports', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('reportmeaning', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('accessreportmeaning', 'local_asodeltolstaff'));
echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/local/asodeltolstaff/export.php')]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::label(get_string('reportdataset', 'local_asodeltolstaff'), 'id_dataset');
echo html_writer::select([
    'participants' => get_string('reportparticipants', 'local_asodeltolstaff'),
    'consolidated' => get_string('reportconsolidated', 'local_asodeltolstaff'),
    'access' => get_string('reportaccess', 'local_asodeltolstaff'),
], 'dataset', 'participants', false, ['id' => 'id_dataset', 'class' => 'form-select mb-3']);
echo html_writer::label(get_string('format'), 'id_format');
echo html_writer::select(['excel' => 'XLSX', 'csv' => 'CSV'], 'format', 'excel', false,
    ['id' => 'id_format', 'class' => 'form-select mb-3']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('download'), 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
