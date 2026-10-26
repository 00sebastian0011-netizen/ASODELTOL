<?php
// Accessible player for media and a caption file stored in one Moodle page module.
require_once('../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$video = required_param('video', PARAM_FILE);
$caption = required_param('caption', PARAM_FILE);
if (!preg_match('/^[a-zA-Z0-9._-]+\.mp4$/D', $video)
        || !preg_match('/^[a-zA-Z0-9._-]+\.vtt$/D', $caption)) {
    throw new invalid_parameter_exception('Unsupported media filename');
}
$cm = get_coursemodule_from_id('page', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$coursecontext = context_course::instance($course->id);
if (!is_siteadmin() && !is_enrolled($coursecontext, $USER->id, '', true)
        && !has_capability('local/asodeltolstaff:viewall', $coursecontext)) {
    throw new moodle_exception('notenrolled', 'enrol');
}
$context = context_module::instance($cmid);
require_capability('mod/page:view', $context);
if (!get_fast_modinfo($course, $USER->id)->get_cm($cmid)->uservisible) {
    throw new moodle_exception('nopermissions', 'error');
}
$storage = get_file_storage();
foreach ([$video, $caption] as $filename) {
    if (!$storage->get_file($context->id, 'mod_page', 'content', 0, '/', $filename)) {
        throw new moodle_exception('filenotfound', 'error');
    }
}
$url = new moodle_url('/local/asodeltolstaff/media.php',
    ['cmid' => $cmid, 'video' => $video, 'caption' => $caption]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('accessiblemedia', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$videourl = moodle_url::make_pluginfile_url($context->id, 'mod_page', 'content', 0, '/', $video);
$captionurl = moodle_url::make_pluginfile_url($context->id, 'mod_page', 'content', 0, '/', $caption);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('accessiblemedia', 'local_asodeltolstaff'));
echo html_writer::tag('video',
    html_writer::empty_tag('source', ['src' => $videourl->out(false), 'type' => 'video/mp4'])
    . html_writer::empty_tag('track', ['src' => $captionurl->out(false), 'kind' => 'subtitles',
        'srclang' => 'es', 'label' => 'Español', 'default' => 'default']),
    ['controls' => 'controls', 'playsinline' => 'playsinline', 'preload' => 'metadata',
        'width' => 640, 'class' => 'asodeltol-accessible-video']);
echo html_writer::tag('p', html_writer::link($captionurl, get_string('downloadcaptions', 'local_asodeltolstaff')));
echo $OUTPUT->footer();
