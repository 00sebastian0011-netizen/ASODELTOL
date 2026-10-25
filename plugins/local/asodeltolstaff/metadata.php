<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
admin_externalpage_setup('asodeltolmetadata');
if (optional_param('configure', 0, PARAM_BOOL)) {
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    \local_asodeltolstaff\metadata_fields::configure();
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('metadatafields', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('metadatameaning', 'local_asodeltolstaff'));
echo $OUTPUT->single_button(new moodle_url('/local/asodeltolstaff/metadata.php', ['configure' => 1]),
    get_string('configure'), 'post');
echo $OUTPUT->footer();
