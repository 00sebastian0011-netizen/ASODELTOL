<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Administrative validation page.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$session = \local_asodeltolstaff\manager::get_session($id);
$course = get_course((int) $session->courseid);
$context = context_course::instance((int) $course->id);
require_course_login($course);
require_capability('local/asodeltolstaff:validate', $context);

$url = new moodle_url('/local/asodeltolstaff/validate.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('validate', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_asodeltolstaff\local\form\validation_form($url);
$form->set_data($session);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $course->id]));
}
if ($data = $form->get_data()) {
    \local_asodeltolstaff\manager::validate_session(
        $id,
        (string) $data->validationstatus,
        empty($data->validatedminutes) ? null : (int) $data->validatedminutes,
        (string) $data->reason
    );
    redirect(
        new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $course->id]),
        get_string('validation_saved', 'local_asodeltolstaff'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('validate', 'local_asodeltolstaff') . ': ' . s($session->sessioncode));
$form->display();
echo $OUTPUT->footer();
