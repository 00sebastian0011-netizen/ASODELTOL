<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Session overview with group-aware rows.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_course_login($course);
require_capability('local/asodeltolstaff:viewsessions', $context);

$url = new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('stafftracking', 'local_asodeltolstaff'));
$PAGE->set_heading(format_string($course->fullname));

$sessions = \local_asodeltolstaff\manager::get_accessible_sessions($courseid);
$canviewall = \local_asodeltolstaff\manager::can_view_all($context);
$canmanage = has_capability('local/asodeltolstaff:manageown', $context);
$canvalidate = has_capability('local/asodeltolstaff:validate', $context);
$canviewevidence = has_capability('local/asodeltolstaff:viewevidence', $context);
$modinfo = get_fast_modinfo($course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('stafftracking', 'local_asodeltolstaff'));

$buttons = [];
$buttons[] = $OUTPUT->single_button(
    new moodle_url('/local/asodeltolstaff/progress.php', ['courseid' => $courseid]),
    get_string('academicprogress', 'local_asodeltolstaff'), 'get'
);
if (has_capability('local/asodeltolstaff:publishown', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/communication.php', ['courseid' => $courseid]),
        get_string('groupcommunication', 'local_asodeltolstaff'), 'get'
    );
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/publish.php', ['courseid' => $courseid]),
        get_string('publishresource', 'local_asodeltolstaff'), 'get'
    );
}
if ($canmanage) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/officehours.php', ['courseid' => $courseid]),
        get_string('officehours', 'local_asodeltolstaff'), 'get'
    );
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/session.php', ['courseid' => $courseid]),
        get_string('newsession', 'local_asodeltolstaff'),
        'get'
    );
}
if (has_capability('local/asodeltolstaff:export', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/reports.php', ['courseid' => $courseid]),
        get_string('additionalreports', 'local_asodeltolstaff'), 'get'
    );
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/export.php', ['courseid' => $courseid, 'format' => 'excel']),
        get_string('export', 'local_asodeltolstaff') . ' XLSX',
        'get'
    );
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/export.php', ['courseid' => $courseid, 'format' => 'csv']),
        get_string('export', 'local_asodeltolstaff') . ' CSV',
        'get'
    );
}
if (has_capability('moodle/site:config', context_system::instance())) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/credentials.php', ['courseid' => $courseid]),
        get_string('studentcredentials', 'local_asodeltolstaff'),
        'get'
    );
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/asodeltolstaff/satisfaction_survey.php', ['courseid' => $courseid]),
        get_string('satisfactionsurvey', 'local_asodeltolstaff'),
        'get'
    );
}
if ($buttons) {
    echo html_writer::div(implode(' ', $buttons), 'mb-3');
}
echo html_writer::link(new moodle_url('/calendar/view.php', ['view' => 'month', 'course' => $courseid]),
    get_string('calendar'), ['class' => 'btn btn-outline-secondary mb-3']);

$table = new html_table();
$table->head = [
    get_string('sessioncode', 'local_asodeltolstaff'),
    get_string('section', 'local_asodeltolstaff'),
    get_string('group', 'local_asodeltolstaff'),
    get_string('trainer', 'local_asodeltolstaff'),
    get_string('scheduledminutes', 'local_asodeltolstaff'),
    get_string('reportedminutes', 'local_asodeltolstaff'),
    get_string('validatedminutes', 'local_asodeltolstaff'),
    get_string('validationstatus', 'local_asodeltolstaff'),
    get_string('attendance', 'local_asodeltolstaff'),
    get_string('evidence', 'local_asodeltolstaff'),
    get_string('action', 'local_asodeltolstaff'),
];

foreach ($sessions as $session) {
    $group = $DB->get_record('groups', ['id' => $session->groupid], 'id,name', MUST_EXIST);
    $sectioninfo = $modinfo->get_section_info((int) $session->sectionnum, MUST_EXIST);
    $trainer = core_user::get_user((int) $session->trainerid, '*', IGNORE_MISSING);
    $attendance = \local_asodeltolstaff\manager::get_visible_attendance($session);
    $attendancevalues = array_map(
        static fn(stdClass $entry): string => get_string('status_' . $entry->status, 'local_asodeltolstaff'),
        $attendance
    );
    $reportedminutes = empty($session->reportedstart) || empty($session->reportedend)
        ? '-'
        : \local_asodeltolstaff\manager::duration_minutes(
            (int) $session->reportedstart,
            (int) $session->reportedend
        );
    $actions = [];
    $ownmanageable = $canmanage && ($canviewall
        || ((int) $session->trainerid === (int) $USER->id
            && groups_is_member((int) $session->groupid, (int) $USER->id)));
    if ($ownmanageable) {
        $actions[] = html_writer::link(
            new moodle_url('/local/asodeltolstaff/session.php', ['courseid' => $courseid, 'id' => $session->id]),
            get_string('edit')
        );
        $actions[] = html_writer::link(
            new moodle_url('/local/asodeltolstaff/attendance.php', ['id' => $session->id]),
            get_string('attendance', 'local_asodeltolstaff')
        );
    }
    if ($canvalidate) {
        $actions[] = html_writer::link(
            new moodle_url('/local/asodeltolstaff/validate.php', ['id' => $session->id]),
            get_string('validate', 'local_asodeltolstaff')
        );
    }
    if ($ownmanageable || $canviewall) {
        $actions[] = html_writer::link(
            new moodle_url('/local/asodeltolstaff/history.php', ['id' => $session->id]),
            get_string('history', 'local_asodeltolstaff')
        );
    }
    $evidence = '-';
    if ($canviewevidence && $session->evidenceref !== '') {
        $evidence = html_writer::link(
            new moodle_url($session->evidenceref),
            get_string('evidence', 'local_asodeltolstaff'),
            ['rel' => 'noopener noreferrer', 'target' => '_blank']
        );
    }
    $table->data[] = [
        s($session->sessioncode),
        'UT' . (int) $session->sectionnum . ' — ' . s(get_section_name($course, $sectioninfo)),
        s($group->name),
        $trainer ? s(fullname($trainer)) : get_string('deleteduser', 'core'),
        \local_asodeltolstaff\manager::duration_minutes(
            (int) $session->scheduledstart,
            (int) $session->scheduledend
        ),
        $reportedminutes,
        $session->validatedminutes === null ? '-' : (int) $session->validatedminutes,
        get_string('status_' . $session->validationstatus, 'local_asodeltolstaff'),
        $attendancevalues ? implode(', ', $attendancevalues) : '-',
        $evidence,
        implode(' | ', $actions),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
