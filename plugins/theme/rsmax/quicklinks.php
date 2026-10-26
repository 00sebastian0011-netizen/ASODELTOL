<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_course_login($course);
require_capability('moodle/course:manageactivities', $context);

$url = new moodle_url('/theme/rsmax/quicklinks.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursequickadmin', 'theme_rsmax'));
$PAGE->set_heading(format_string($course->fullname));

$options = \theme_rsmax\local\course_utilities::options_for_course($course);
$form = new \theme_rsmax\form\course_quicklinks_form($url, [
    'courseid' => $courseid,
    'options' => $options,
]);

$selected = \theme_rsmax\local\course_utilities::choices_for_course($courseid) ?? [];
$defaults = (object) ['courseid' => $courseid];
foreach (\theme_rsmax\local\course_utilities::keys() as $key) {
    $defaults->{'ql_' . $key} = $selected[$key] ?? 0;
}
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

if ($data = $form->get_data()) {
    require_sesskey();
    $submitted = [];
    foreach (\theme_rsmax\local\course_utilities::keys() as $key) {
        $submitted[$key] = $data->{'ql_' . $key};
    }
    \theme_rsmax\local\course_utilities::save($course, $submitted);
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('coursequicksaved', 'theme_rsmax'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursequickadmin', 'theme_rsmax'));
echo html_writer::tag('p', get_string('coursequickintro', 'theme_rsmax'));

$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
echo html_writer::div(html_writer::link($courseurl,
    get_string('coursequickcreate', 'theme_rsmax'),
    ['class' => 'btn btn-outline-primary mb-3']), 'rsmax-quicklinks-course-action');

$form->display();
echo $OUTPUT->footer();
