<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Append-only history viewer.
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
$history = \local_asodeltolstaff\manager::get_history($session);
$canviewevidence = has_capability('local/asodeltolstaff:viewevidence', $context);

$url = new moodle_url('/local/asodeltolstaff/history.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('history', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));

$redact = static function (mixed $value) use (&$redact): mixed {
    if (!is_array($value)) {
        return $value;
    }
    foreach ($value as $key => $item) {
        $value[$key] = $key === 'evidenceref' ? '[restricted]' : $redact($item);
    }
    return $value;
};

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('history', 'local_asodeltolstaff') . ': ' . s($session->sessioncode));
$table = new html_table();
$table->head = [get_string('date'), get_string('user'), get_string('action'), get_string('reason', 'local_asodeltolstaff')];
foreach ($history as $entry) {
    $actor = empty($entry->actorid) ? null : core_user::get_user((int) $entry->actorid, '*', IGNORE_MISSING);
    $payload = json_decode($entry->payload, true, 512, JSON_THROW_ON_ERROR);
    if (!$canviewevidence) {
        $payload = $redact($payload);
    }
    $table->data[] = [
        userdate((int) $entry->timecreated),
        $actor ? s(fullname($actor)) : get_string('deleteduser', 'core'),
        s($entry->action),
        s($entry->reason) . html_writer::tag(
            'details',
            html_writer::tag('summary', get_string('details'))
                . html_writer::tag('pre', s(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)))
        ),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
