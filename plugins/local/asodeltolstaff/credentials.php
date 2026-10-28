<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Institutional student municipality and credential administration (REQ-USR-01..05).
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$sysctx = context_system::instance();
require_capability('moodle/site:config', $sysctx);

$courseid = optional_param('courseid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

if ($action === 'export') {
    $format = required_param('format', PARAM_ALPHA);
    if (!in_array($format, ['csv', 'excel'], true)) {
        throw new moodle_exception('invalidparameter');
    }
    $columns = \local_asodeltolstaff\student_credentials::export_columns();
    $rawrows = \local_asodeltolstaff\student_credentials::directory_rows($courseid, $groupid);
    $exportrows = [];
    foreach ($rawrows as $row) {
        $filtered = [];
        foreach (array_keys($columns) as $col) {
            $filtered[$col] = \core\dataformat::escape_spreadsheet_formula((string) ($row[$col] ?? ''));
        }
        $exportrows[] = $filtered;
    }
    \core\dataformat::download_data('asodeltol-credentials-ledger', $format, $columns, new ArrayIterator($exportrows));
    exit;
}

$url = new moodle_url('/local/asodeltolstaff/credentials.php', array_filter([
    'courseid' => $courseid,
    'groupid' => $groupid,
]));
$PAGE->set_url($url);
$PAGE->set_context($sysctx);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('studentcredentials', 'local_asodeltolstaff'));
$PAGE->set_heading(get_string('studentcredentials', 'local_asodeltolstaff'));

$issuedcredentials = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    require_sesskey();
    if ($action === 'apply_policy') {
        \local_asodeltolstaff\student_credentials::apply_institutional_policy();
        $notice = get_string('credentialspolicyapplied', 'local_asodeltolstaff');
    } else if ($action === 'restore_policy') {
        \local_asodeltolstaff\student_credentials::restore_policy_state();
        $notice = get_string('credentialspolicyrestored', 'local_asodeltolstaff');
    } else if ($action === 'municipality') {
        $targetuserid = required_param('userid', PARAM_INT);
        $municipality = optional_param('municipality', '', PARAM_TEXT);
        $munistatus = required_param('municipalitystatus', PARAM_ALPHANUMEXT);
        \local_asodeltolstaff\student_credentials::update_municipality($targetuserid, $municipality, $munistatus);
        $notice = get_string('municipalitysaved', 'local_asodeltolstaff');
    } else if ($action === 'issue') {
        $targetuserid = required_param('userid', PARAM_INT);
        $mode = optional_param('mode', 'initial', PARAM_ALPHA);
        $issuedcredentials[] = \local_asodeltolstaff\student_credentials::issue_credential($targetuserid, $mode);
        $notice = get_string('credentialissued', 'local_asodeltolstaff');
    } else if ($action === 'bulk_generate') {
        $rows = \local_asodeltolstaff\student_credentials::directory_rows($courseid, $groupid);
        foreach ($rows as $row) {
            if ($row['deliverystatus'] === \local_asodeltolstaff\student_credentials::DELIVERY_PENDING) {
                $issuedcredentials[] = \local_asodeltolstaff\student_credentials::issue_credential(
                    (int) $row['userid'],
                    'initial'
                );
            }
        }
        $notice = get_string('credentialissued', 'local_asodeltolstaff');
    } else if ($action === 'deliver') {
        $targetuserid = required_param('userid', PARAM_INT);
        $channel = optional_param('channel', 'private_institutional', PARAM_ALPHANUMEXT);
        \local_asodeltolstaff\student_credentials::mark_delivered($targetuserid, $channel);
        $notice = get_string('credentialdelivered', 'local_asodeltolstaff');
    }
}

$preflight = \local_asodeltolstaff\student_credentials::preflight_check($courseid);
$rows = \local_asodeltolstaff\student_credentials::directory_rows($courseid, $groupid);
$courses = get_courses();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studentcredentials', 'local_asodeltolstaff'));
echo html_writer::tag('p', get_string('studentcredentialsmeaning', 'local_asodeltolstaff'));

if ($notice !== '') {
    echo $OUTPUT->notification($notice, \core\output\notification::NOTIFY_SUCCESS);
}

if ($issuedcredentials) {
    echo $OUTPUT->notification(get_string('credentialsonetimewarning', 'local_asodeltolstaff'),
        \core\output\notification::NOTIFY_WARNING);
    $once = new html_table();
    $once->head = [
        get_string('report_username', 'local_asodeltolstaff'),
        get_string('report_student', 'local_asodeltolstaff'),
        get_string('password'),
        get_string('action', 'local_asodeltolstaff'),
    ];
    foreach ($issuedcredentials as $item) {
        $once->data[] = [
            s($item['username']),
            s($item['fullname']),
            html_writer::tag('code', s($item['password'])),
            s($item['mode']),
        ];
    }
    echo html_writer::table($once);
}

// REQ-USR-05 Preflight and policy controls.
$authsummary = [];
foreach ($preflight['authcounts'] as $auth => $count) {
    $authsummary[] = s($auth) . ': ' . (int) $count;
}
echo html_writer::start_div('card mb-3 p-3');
echo html_writer::tag('h4', get_string('credentialspreflight', 'local_asodeltolstaff'));
echo html_writer::tag('p',
    get_string('report_registeredcount', 'local_asodeltolstaff') . ': ' . (int) $preflight['studentcount']
    . ' | Auth: ' . ($authsummary ? implode(', ', $authsummary) : '0')
    . ' | City hidden: ' . ($preflight['cityhidden'] ? 'OK' : 'No')
    . ' | City locked: ' . ($preflight['citylocked'] ? 'OK' : 'No')
    . ' | Password locked: ' . ($preflight['passwordlocked'] ? 'OK' : 'No')
);
$policybuttons = [];
$policybuttons[] = $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'apply_policy']),
    get_string('applycredentialspolicy', 'local_asodeltolstaff'),
    'post'
);
if ($preflight['hasbackup']) {
    $policybuttons[] = $OUTPUT->single_button(
        new moodle_url($url, ['action' => 'restore_policy']),
        get_string('restorecredentialspolicy', 'local_asodeltolstaff'),
        'post'
    );
}
$policybuttons[] = $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'bulk_generate']),
    get_string('bulkgeneratecredentials', 'local_asodeltolstaff'),
    'post'
);
$policybuttons[] = $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'export', 'format' => 'excel']),
    get_string('export', 'local_asodeltolstaff') . ' XLSX',
    'get'
);
$policybuttons[] = $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'export', 'format' => 'csv']),
    get_string('export', 'local_asodeltolstaff') . ' CSV',
    'get'
);
echo html_writer::div(implode(' ', $policybuttons), 'd-flex flex-wrap gap-2');
echo html_writer::end_div();

// Course filter form.
$courseoptions = [0 => get_string('fulllistofcourses')];
foreach ($courses as $c) {
    if ((int) $c->id === (int) SITEID) {
        continue;
    }
    $courseoptions[(int) $c->id] = format_string($c->fullname);
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/local/asodeltolstaff/credentials.php'), 'class' => 'mb-3']);
echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-select d-inline-block w-auto me-2']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$table = new html_table();
$table->head = [
    get_string('report_username', 'local_asodeltolstaff'),
    get_string('report_student', 'local_asodeltolstaff'),
    get_string('group', 'local_asodeltolstaff'),
    get_string('municipality', 'local_asodeltolstaff'),
    get_string('municipalitystatus', 'local_asodeltolstaff'),
    get_string('credentialdeliverystatus', 'local_asodeltolstaff'),
    get_string('timegeneratedutc', 'local_asodeltolstaff'),
    get_string('timedeliveredutc', 'local_asodeltolstaff'),
    get_string('timeresetutc', 'local_asodeltolstaff'),
    get_string('action', 'local_asodeltolstaff'),
];

$munistatusoptions = [
    \local_asodeltolstaff\student_credentials::MUNICIPALITY_VERIFIED => get_string('municipality_verified', 'local_asodeltolstaff'),
    \local_asodeltolstaff\student_credentials::MUNICIPALITY_PENDING => get_string('municipality_pending', 'local_asodeltolstaff'),
];
$channeloptions = [
    'private_institutional' => get_string('channel_private_institutional', 'local_asodeltolstaff'),
    'in_person_verified' => get_string('channel_in_person_verified', 'local_asodeltolstaff'),
    'verified_messaging' => get_string('channel_verified_messaging', 'local_asodeltolstaff'),
];

foreach ($rows as $row) {
    $uid = (int) $row['userid'];

    // Inline municipality update form (REQ-USR-01).
    $muniform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'd-flex gap-1 align-items-center']);
    $muniform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $muniform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'municipality']);
    $muniform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $uid]);
    $muniform .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'municipality',
        'value' => $row['municipality'],
        'class' => 'form-control form-control-sm',
        'size' => 14,
    ]);
    $muniform .= html_writer::select($munistatusoptions, 'municipalitystatus', $row['municipalitystatus'], false,
        ['class' => 'form-select form-select-sm w-auto']);
    $muniform .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges'),
        'class' => 'btn btn-sm btn-outline-primary']);
    $muniform .= html_writer::end_tag('form');

    // Credential actions (REQ-USR-02, REQ-USR-03, REQ-USR-04).
    $issueform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'd-inline me-1']);
    $issueform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $issueform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'issue']);
    $issueform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $uid]);
    $ismode = $row['timegeneratedutc'] === '' ? 'initial' : 'reset';
    $issueform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mode', 'value' => $ismode]);
    $issueform .= html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string($ismode === 'initial' ? 'generatecredential' : 'resetcredential', 'local_asodeltolstaff'),
        'class' => 'btn btn-sm btn-warning mb-1',
    ]);
    $issueform .= html_writer::end_tag('form');

    $deliverform = '';
    if ($row['timegeneratedutc'] !== '' || $row['timeresetutc'] !== '') {
        $deliverform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'd-flex gap-1 align-items-center mt-1']);
        $deliverform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $deliverform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deliver']);
        $deliverform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $uid]);
        $deliverform .= html_writer::select($channeloptions, 'channel', $row['channel'] ?: 'private_institutional', false,
            ['class' => 'form-select form-select-sm w-auto']);
        $deliverform .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('markcredentialdelivered', 'local_asodeltolstaff'),
            'class' => 'btn btn-sm btn-success',
        ]);
        $deliverform .= html_writer::end_tag('form');
    }

    $table->data[] = [
        s($row['username']),
        s($row['student']),
        s($row['group']),
        $muniform,
        s($munistatusoptions[$row['municipalitystatus']] ?? $row['municipalitystatus']),
        s(get_string('deliverystatus_' . $row['deliverystatus'], 'local_asodeltolstaff')),
        s($row['timegeneratedutc'] ?: '-'),
        s($row['timedeliveredutc'] ?: '-'),
        s($row['timeresetutc'] ?: '-'),
        $issueform . $deliverform,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
