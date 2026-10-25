<?php
require_once('../../config.php');
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_course_login($course);
$context = context_course::instance($courseid);
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
$canopen = has_capability('moodle/course:sectionvisibility', $context);
$url = new moodle_url('/local/asodeltolstaff/communication.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('groupcommunication', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));
$form = new \local_asodeltolstaff\local\form\communication_form($url, compact('groups', 'sections', 'canopen'));
$form->set_data((object) ['courseid' => $courseid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $courseid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    switch ($data->action) {
        case 'announcement':
            \local_asodeltolstaff\group_communication::announce($courseid, $data->groupid, $data->section,
                $data->title, text_to_html($data->body));
            break;
        case 'schedule':
            \local_asodeltolstaff\group_communication::schedule($courseid, $data->groupid,
                $data->title, text_to_html($data->body), $data->start, $data->end);
            break;
        case 'open':
            $section = get_fast_modinfo($course)->get_section_info($data->section, MUST_EXIST);
            \local_asodeltolstaff\group_communication::open_section($courseid, $data->groupid, $section->id);
            break;
        default:
            throw new invalid_parameter_exception('Unsupported communication action');
    }
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('groupcommunication', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('communicationmeaning', 'local_asodeltolstaff'));
$form->display();
echo $OUTPUT->footer();
