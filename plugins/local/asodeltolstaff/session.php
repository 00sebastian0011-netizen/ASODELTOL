<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Create or edit a session record.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_course_login($course);
require_capability('local/asodeltolstaff:manageown', $context);

$session = null;
if ($id) {
    $session = \local_asodeltolstaff\manager::get_session($id);
    \local_asodeltolstaff\manager::require_session_manage($session);
}
$groups = \local_asodeltolstaff\manager::get_available_groups($courseid);
$groupoptions = [];
$traineroptions = [];
foreach ($groups as $group) {
    $groupoptions[$group->id] = format_string($group->name);
    foreach (\local_asodeltolstaff\manager::get_group_trainers($courseid, (int) $group->id) as $trainer) {
        $traineroptions[$trainer->id] = fullname($trainer);
    }
}
$sections = [];
$modinfo = get_fast_modinfo($course);
foreach ($modinfo->get_section_info_all() as $section) {
    if ((int) $section->section > 0) {
        $sections[$section->section] = get_section_name($course, $section);
    }
}

$url = new moodle_url('/local/asodeltolstaff/session.php', ['courseid' => $courseid, 'id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('newsession', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_asodeltolstaff\local\form\session_form($url, [
    'groups' => $groupoptions,
    'trainers' => $traineroptions,
    'sections' => $sections,
]);
$defaults = $session ?: (object) [
    'id' => 0,
    'courseid' => $courseid,
    'sectionnum' => 1,
    'scheduledstart' => time() + HOURSECS,
    'scheduledend' => time() + (2 * HOURSECS),
];
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $courseid]));
}
if ($data = $form->get_data()) {
    $saved = \local_asodeltolstaff\manager::save_session($data);
    redirect(
        new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $courseid]),
        get_string($id ? 'sessionupdated' : 'sessioncreated', 'local_asodeltolstaff'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('newsession', 'local_asodeltolstaff'));
$form->display();
echo $OUTPUT->footer();
