<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Administrative controller to provision and inspect the SENA PFCE 2026 satisfaction survey (OV-ENCUESTA).
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 4, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sectionnum = optional_param('sectionnum', -1, PARAM_INT);
$gated = optional_param('gated', 0, PARAM_BOOL);

require_login();
$sysctx = context_system::instance();
require_capability('moodle/site:config', $sysctx);

$courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'id ASC', 'id,fullname,shortname');
if (!$courses) {
    throw new moodle_exception('cannotfindcourse');
}
if (!isset($courses[$courseid])) {
    $courseid = (int) reset($courses)->id;
}
$course = get_course($courseid);
$context = context_course::instance($course->id);

$url = new moodle_url('/local/asodeltolstaff/satisfaction_survey.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('satisfactionsurvey', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));

if ($action === 'provision' && data_submitted()) {
    require_sesskey();
    $targetsection = $sectionnum >= 0 ? $sectionnum : null;
    $result = \local_asodeltolstaff\satisfaction_survey::provision($course->id, $targetsection, (bool) $gated);
    redirect(
        $url,
        get_string('satisfactionsurveyprovisioned', 'local_asodeltolstaff', (object) [
            'cmid' => $result['cmid'],
            'items' => $result['items'],
        ]),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$status = \local_asodeltolstaff\satisfaction_survey::status($course->id);
$spec = \local_asodeltolstaff\satisfaction_survey::specification();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('satisfactionsurvey', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('satisfactionsurveymeaning', 'local_asodeltolstaff'), ['class' => 'text-muted']);

// Course selector.
$courseoptions = [];
foreach ($courses as $c) {
    $courseoptions[(int) $c->id] = format_string($c->fullname);
}
$select = new single_select(new moodle_url('/local/asodeltolstaff/satisfaction_survey.php'), 'courseid', $courseoptions, $course->id, null);
$select->set_label(get_string('course') . ': ');
echo html_writer::div($OUTPUT->render($select), 'mb-3');

echo \local_asodeltolstaff\satisfaction_survey::build_header_html($course);

if ($status) {
    $summary = new html_table();
    $summary->head = [
        get_string('name'),
        get_string('section', 'local_asodeltolstaff'),
        get_string('satisfactionsurveyitems', 'local_asodeltolstaff'),
        get_string('satisfactionsurveyresponses', 'local_asodeltolstaff'),
        get_string('action', 'local_asodeltolstaff'),
    ];
    $links = [
        html_writer::link($status['viewurl'], get_string('satisfactionsurveyopen', 'local_asodeltolstaff'), ['class' => 'btn btn-sm btn-primary me-1']),
        html_writer::link($status['completeurl'], get_string('satisfactionsurveyanswer', 'local_asodeltolstaff'), ['class' => 'btn btn-sm btn-success me-1']),
        html_writer::link($status['entriesurl'], get_string('satisfactionsurveyentries', 'local_asodeltolstaff'), ['class' => 'btn btn-sm btn-outline-secondary']),
    ];
    $summary->data[] = [
        s($status['name']) . ' (CM #' . (int) $status['cmid'] . ')',
        s($status['sectionname'] ?: ('Sección ' . $status['sectionnum'])),
        (int) $status['items'],
        (int) $status['responses'],
        implode(' ', $links),
    ];
    echo html_writer::table($summary);
}

// Provisioning form.
$formurl = new moodle_url('/local/asodeltolstaff/satisfaction_survey.php', [
    'courseid' => $course->id,
    'action' => 'provision',
]);
$formhtml = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$formhtml .= html_writer::div(
    html_writer::checkbox('gated', 1, $status ? (bool) $status['gated'] : false, get_string('satisfactionsurveygate', 'local_asodeltolstaff')),
    'mb-2'
);
$formhtml .= html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-success',
    'value' => get_string('satisfactionsurveyapply', 'local_asodeltolstaff'),
]);
echo html_writer::tag('form', $formhtml, ['method' => 'post', 'action' => $formurl->out(false), 'class' => 'card card-body mb-4']);

echo $OUTPUT->footer();

