<?php
require_once('../../config.php');
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_course_login($course);
require_capability('local/asodeltolstaff:manageown', $context);
$groups = [];
$trainers = [];
foreach (\local_asodeltolstaff\manager::get_available_groups($courseid) as $group) {
    $groups[$group->id] = format_string($group->name);
    foreach (\local_asodeltolstaff\manager::get_group_trainers($courseid, $group->id) as $trainer) {
        $trainers[$trainer->id] = fullname($trainer);
    }
}
$url = new moodle_url('/local/asodeltolstaff/officehours.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('officehours', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));
$form = new \local_asodeltolstaff\local\form\office_hours_form($url, ['groups' => $groups, 'trainers' => $trainers]);
$form->set_data((object) ['courseid' => $courseid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $courseid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    \local_asodeltolstaff\office_hours::create($courseid, (int) $data->groupid, (int) $data->trainerid,
        (int) $data->start, (int) $data->end, (string) $data->meetingurl);
    redirect(new moodle_url('/calendar/view.php', ['view' => 'month', 'course' => $courseid]));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('officehours', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('officehoursminimum', 'local_asodeltolstaff'));
$form->display();
echo $OUTPUT->footer();
