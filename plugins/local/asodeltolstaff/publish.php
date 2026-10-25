<?php
require_once('../../config.php');
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_course_login($course);
require_capability('local/asodeltolstaff:publishown', $context);
$groups = [];
foreach (groups_get_all_groups($courseid, is_siteadmin() ? 0 : $USER->id) as $group) {
    $groups[$group->id] = format_string($group->name);
}
$sections = [];
foreach (get_fast_modinfo($course)->get_section_info_all() as $section) {
    if ($section->section > 0) {
        $sections[$section->section] = get_section_name($course, $section);
    }
}
$url = new moodle_url('/local/asodeltolstaff/publish.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('publishresource', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));
$form = new \local_asodeltolstaff\local\form\publish_form($url, ['groups' => $groups, 'sections' => $sections,
    'maxbytes' => get_max_upload_file_size($CFG->maxbytes, $course->maxbytes)]);
$form->set_data((object) ['courseid' => $courseid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $courseid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $id = \local_asodeltolstaff\group_publisher::create($courseid, (int) $data->groupid, (int) $data->section,
        (string) $data->kind, (string) $data->title, (string) $data->reference, (string) ($data->body['text'] ?? ''),
        (string) ($data->externalurl ?? ''), (int) ($data->asset ?? 0), (int) ($data->duedate ?? 0));
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('publishresource', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('publishmeaning', 'local_asodeltolstaff'));
$form->display();
echo $OUTPUT->footer();
