<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:sendmessage', context_system::instance());
$url = new moodle_url('/local/asodeltolstaff/support.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('supportchannel', 'local_asodeltolstaff'));
$PAGE->set_heading(get_string('supportchannel', 'local_asodeltolstaff'));
$form = null;
if (\local_asodeltolstaff\support::recipient()) {
    $form = new \local_asodeltolstaff\local\form\support_form($url);
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/my/'));
    }
    if ($data = $form->get_data()) {
        require_sesskey();
        $id = \local_asodeltolstaff\support::send($data->subject, $data->body);
        redirect($url, get_string('supportsent', 'local_asodeltolstaff', $id), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('supportchannel', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('supportmeaning', 'local_asodeltolstaff'));
if ($form) {
    $form->display();
} else {
    echo $OUTPUT->notification(get_string('supportunconfigured', 'local_asodeltolstaff'), 'warning');
}
echo $OUTPUT->footer();
