<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Export group-filtered session and attendance rows through Moodle data formats.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$format = required_param('format', PARAM_ALPHA);
if (!in_array($format, ['csv', 'excel'], true)) {
    throw new moodle_exception('invalidparameter');
}
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_course_login($course);
require_capability('local/asodeltolstaff:export', $context);

$columns = [
    'sessioncode' => get_string('sessioncode', 'local_asodeltolstaff'),
    'scheduleddate' => get_string('date'),
    'section' => get_string('section', 'local_asodeltolstaff'),
    'group' => get_string('group', 'local_asodeltolstaff'),
    'trainer' => get_string('trainer', 'local_asodeltolstaff'),
    'student' => get_string('student', 'local_asodeltolstaff'),
    'attendance' => get_string('attendance_status', 'local_asodeltolstaff'),
    'scheduledminutes' => get_string('scheduledminutes', 'local_asodeltolstaff'),
    'reportedminutes' => get_string('reportedminutes', 'local_asodeltolstaff'),
    'validatedminutes' => get_string('validatedminutes', 'local_asodeltolstaff'),
    'validationstatus' => get_string('validationstatus', 'local_asodeltolstaff'),
    'validator' => get_string('validatedby', 'local_asodeltolstaff'),
];
$rows = new ArrayIterator(\local_asodeltolstaff\manager::get_report_rows($courseid));
$escape = static function (array $row): array {
    foreach ($row as $key => $value) {
        $row[$key] = \core\dataformat::escape_spreadsheet_formula($value);
    }
    return $row;
};
\core\dataformat::download_data('asodeltol-session-report', $format, $columns, $rows, $escape);
