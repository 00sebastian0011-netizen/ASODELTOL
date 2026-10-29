<?php
require('../../config.php');
require_login();

$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_url('/local/asodeltolstaff/profile.php');
$PAGE->set_title(get_string('profile'));
$PAGE->set_heading(get_string('profile'));

$fields = $DB->get_records_menu('user_info_field', ['shortname' => 'numero_documento'], '', 'shortname,id');
$document = '';
if (!empty($fields['numero_documento'])) {
    $document = (string) ($DB->get_field('user_info_data', 'data', [
        'userid' => $USER->id,
        'fieldid' => $fields['numero_documento'],
    ]) ?: '');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('profile'));
echo html_writer::start_tag('dl', ['class' => 'local-asodeltolstaff-minimal-profile']);
foreach ([
    get_string('fullname') => fullname($USER),
    get_string('idnumber') => $document,
    get_string('email') => $USER->email,
] as $label => $value) {
    echo html_writer::tag('dt', s($label));
    echo html_writer::tag('dd', s($value));
}
echo html_writer::end_tag('dl');
echo $OUTPUT->footer();
