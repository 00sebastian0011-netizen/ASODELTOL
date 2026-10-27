<?php
require_once('../../config.php');
require_login();
$PAGE->set_url(new moodle_url('/local/asodeltolstaff/help.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('platformhelp', 'local_asodeltolstaff'));
$PAGE->set_heading(get_string('platformhelp', 'local_asodeltolstaff'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('platformhelp', 'local_asodeltolstaff'));
// A direct CAU link remains discoverable when the primary nav collapses on mobile.
if (has_capability('moodle/site:sendmessage', context_system::instance())
        && \local_asodeltolstaff\support::recipient() !== null) {
    echo html_writer::link(new moodle_url('/local/asodeltolstaff/support.php'),
        get_string('supportchannel', 'local_asodeltolstaff'),
        ['class' => 'btn btn-primary mb-3']);
}
echo html_writer::start_div('container-fluid', ['id' => 'asodeltol-user-guide']);
foreach (['access', 'submission', 'grades', 'communication', 'account'] as $section) {
    echo $OUTPUT->heading(get_string('guide_' . $section . '_title', 'local_asodeltolstaff'), 3);
    echo html_writer::tag('p', get_string('guide_' . $section, 'local_asodeltolstaff'));
}
foreach (['/my/courses.php' => 'mycourses', '/grade/report/overview/index.php' => 'grades',
        '/message/index.php' => 'messages', '/calendar/view.php' => 'calendar'] as $path => $label) {
    echo html_writer::link(new moodle_url($path), get_string($label), ['class' => 'btn btn-outline-primary me-2 mb-2']);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
