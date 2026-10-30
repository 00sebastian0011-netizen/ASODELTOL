<?php
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_course_login($course);
require_capability('local/asodeltolstaff:viewsessions', $context);
$userid = optional_param('userid', (int) $USER->id, PARAM_INT);
$candidates = [];
if (has_capability('moodle/grade:viewall', $context)) {
    foreach (\local_asodeltolstaff\manager::get_available_groups($courseid) as $group) {
        foreach (\local_asodeltolstaff\manager::get_group_students($courseid, $group->id) as $student) {
            $candidates[$student->id] = fullname($student);
        }
    }
    if (!optional_param('userid', 0, PARAM_INT) && $candidates) {
        $userid = (int) array_key_first($candidates);
    }
}
$data = \local_asodeltolstaff\progress_report::summary($courseid, $userid);
$PAGE->set_url(new moodle_url('/local/asodeltolstaff/progress.php', ['courseid' => $courseid, 'userid' => $userid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('academicprogress', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('academicprogress', 'local_asodeltolstaff'));
if ($candidates) {
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/local/asodeltolstaff/progress.php')]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::label(get_string('student', 'local_asodeltolstaff'), 'id_progress_user');
    echo html_writer::select($candidates, 'userid', $userid, false, ['id' => 'id_progress_user', 'class' => 'form-select mb-2']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('view'), 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}
$percent = static fn($value): string => $value === null ? '—' : format_float($value, 1) . '%';
echo html_writer::tag('p', get_string('progressmeaning', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('overallprogress', 'local_asodeltolstaff') . ': ' . $percent($data['overall_percentage']),
    ['id' => 'asodeltol-overall-progress']);
$table = new html_table();
$table->id = 'asodeltol-unit-progress';
$table->head = [get_string('section', 'local_asodeltolstaff'), get_string('completedactivities', 'local_asodeltolstaff'),
    get_string('overallprogress', 'local_asodeltolstaff')];
foreach ($data['units'] as $unit) {
    $table->data[] = [s($unit['name']), $unit['completed'] . ' / ' . $unit['total'], $percent($unit['percentage'])];
}
echo html_writer::table($table);
echo html_writer::start_div('mt-3 d-flex gap-2 flex-wrap');
echo html_writer::link(new moodle_url('/grade/report/user/index.php', ['id' => $courseid, 'userid' => $userid]),
    get_string('grades'), ['class' => 'btn btn-primary']);
if (\local_asodeltolstaff\support::recipient() !== null) {
    echo html_writer::link(new moodle_url('/local/asodeltolstaff/support.php'),
        get_string('supportchannel', 'local_asodeltolstaff'), ['class' => 'btn btn-outline-secondary']);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
