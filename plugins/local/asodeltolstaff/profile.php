<?php
require('../../config.php');
require_login();

$targetid = optional_param('id', (int) $USER->id, PARAM_INT);
$courseid = optional_param('course', 0, PARAM_INT);
$target = \core_user::get_user($targetid, '*', MUST_EXIST);
$ownprofile = $targetid === (int) $USER->id;
$course = null;
if (!$ownprofile) {
    if (!$courseid) {
        throw new moodle_exception('missingparam', '', '', 'course');
    }
    $course = get_course($courseid);
    $decision = \local_asodeltolstaff\profile_privacy::control_view_profile(
        $target,
        $course,
        context_user::instance($targetid)
    );
    if ($decision !== \core\user::VIEWPROFILE_FORCE_ALLOW) {
        throw new required_capability_exception(
            context_course::instance($courseid),
            'moodle/user:viewdetails',
            'nopermissions',
            ''
        );
    }
}

$context = $course ? context_course::instance($courseid) : context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_url('/local/asodeltolstaff/profile.php', $ownprofile ? [] : [
    'id' => $targetid,
    'course' => $courseid,
]);
if ($course) {
    $PAGE->set_course($course);
}
$PAGE->set_title(get_string('profile'));
$PAGE->set_heading(get_string('profile'));

$values = [get_string('fullname') => fullname($target)];
if ($ownprofile) {
    $fields = $DB->get_records_menu('user_info_field', ['shortname' => 'numero_documento'], '', 'shortname,id');
    $document = '';
    if (!empty($fields['numero_documento'])) {
        $document = (string) ($DB->get_field('user_info_data', 'data', [
            'userid' => $USER->id,
            'fieldid' => $fields['numero_documento'],
        ]) ?: '');
    }
    $values[get_string('idnumber')] = $document;
    $values[get_string('email')] = $USER->email;
} else if (has_capability('moodle/user:viewhiddendetails', $context, $USER->id)) {
    // This permission is intentionally evaluated in the already-authorised
    // course context. Never grant it in the target's global user context.
    $values[get_string('city')] = $target->city;
    $values[get_string('country')] = $target->country;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('profile'));
echo html_writer::start_tag('dl', ['class' => 'local-asodeltolstaff-minimal-profile']);
foreach ($values as $label => $value) {
    echo html_writer::tag('dt', s($label));
    echo html_writer::tag('dd', s($value));
}
echo html_writer::end_tag('dl');
echo $OUTPUT->footer();
