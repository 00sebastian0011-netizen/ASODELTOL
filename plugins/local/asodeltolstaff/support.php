<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:sendmessage', context_system::instance());
$caseid = optional_param('caseid', 0, PARAM_INT);
$case = $caseid ? \local_asodeltolstaff\support::get_case($caseid) : null;
$url = new moodle_url('/local/asodeltolstaff/support.php');
if ($case) {
    $url->param('caseid', $caseid);
}
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('supportchannel', 'local_asodeltolstaff'));
$PAGE->set_heading(get_string('supportchannel', 'local_asodeltolstaff'));
$replyform = null;
if ($case && \local_asodeltolstaff\support::can_reply($case) && $case->status !== 'resolved') {
    $replyform = new \local_asodeltolstaff\local\form\support_reply_form($url);
    $replyform->set_data((object)['caseid' => $caseid]);
    if ($replyform->is_cancelled()) {
        redirect(new moodle_url('/local/asodeltolstaff/support.php'));
    }
    if ($data = $replyform->get_data()) {
        require_sesskey();
        \local_asodeltolstaff\support::respond($caseid, $data->response, !empty($data->resolved));
        redirect($url);
    }
}
$form = null;
if (!$case && \local_asodeltolstaff\support::recipient()) {
    $form = new \local_asodeltolstaff\local\form\support_form($url);
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/my/'));
    }
    if ($data = $form->get_data()) {
        require_sesskey();
        $id = \local_asodeltolstaff\support::send($data->subject, $data->body, $data->category);
        redirect(new moodle_url('/local/asodeltolstaff/support.php', ['caseid' => $id]),
            get_string('supportsent', 'local_asodeltolstaff', $id), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('supportchannel', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('supportmeaning', 'local_asodeltolstaff'));
if ($case) {
    echo $OUTPUT->heading(get_string('supportreference', 'local_asodeltolstaff', $case->id), 3);
    echo html_writer::tag('p', get_string('supportstatus_' . $case->status, 'local_asodeltolstaff'), ['id' => 'support-case-status']);
    echo html_writer::tag('h4', s($case->subject));
    echo html_writer::div(format_text($case->body, FORMAT_PLAIN));
    foreach ($DB->get_records('local_asstaff_caseevent', ['caseid' => $caseid], 'id ASC') as $event) {
        echo html_writer::div(html_writer::tag('p', s(userdate($event->timecreated)))
            . format_text($event->body, FORMAT_PLAIN), 'border p-3 my-2 support-case-event');
    }
    if ($replyform) {
        $replyform->display();
    }
    echo html_writer::link(new moodle_url('/local/asodeltolstaff/support.php'), get_string('back'));
} else if ($form) {
    $form->display();
} else {
    echo $OUTPUT->notification(get_string('supportunconfigured', 'local_asodeltolstaff'), 'warning');
}
if (!$case) {
    echo $OUTPUT->heading(get_string('supportcases', 'local_asodeltolstaff'), 3);
    foreach (\local_asodeltolstaff\support::visible_cases() as $visible) {
        echo html_writer::tag('p', html_writer::link(new moodle_url('/local/asodeltolstaff/support.php', ['caseid' => $visible->id]),
            s(get_string('supportreference', 'local_asodeltolstaff', $visible->id) . ': ' . $visible->subject)));
    }
}
echo $OUTPUT->footer();
