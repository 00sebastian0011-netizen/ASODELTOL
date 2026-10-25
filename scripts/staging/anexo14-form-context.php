<?php
// Read-only investigation of native composite form values; no submissions.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8053') {
    throw new RuntimeException('Local staging required');
}
\core\session\manager::set_user(get_admin());
$course = $DB->get_record('course', ['idnumber' => 'ASODELTOL-AF8'], '*', MUST_EXIST);
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_url('/local/asodeltolstaff/officehours.php', ['courseid' => $course->id]);
$form = new \local_asodeltolstaff\local\form\office_hours_form(null, ['groups' => [1 => 'Synthetic'], 'trainers' => [1 => 'Synthetic']]);
$form->set_data((object) ['courseid' => $course->id]);
$property = new ReflectionProperty(\moodleform::class, '_form');
$qf = $property->getValue($form);
$result = [];
foreach (['start', 'end'] as $field) {
    $element = $qf->getElement($field);
    $result[$field] = $element->getValue();
    $options = new ReflectionProperty($element, '_options');
    $result[$field]['options'] = $options->getValue($element);
    $result[$field]['form_default'] = $qf->_defaultValues[$field] ?? null;
    $result[$field]['constant'] = $qf->_constantValues[$field] ?? null;
    $result[$field]['submitted'] = $qf->_submitValues[$field] ?? null;
    $result[$field]['is_submitted'] = $qf->isSubmitted();
    $optionsvalue = $options->getValue($element);
    $result[$field]['expected_date'] = usergetdate($optionsvalue['defaulttime']);
    $element->onQuickFormEvent('updateValue', null, $qf);
    $result[$field]['after_update'] = $element->getValue();
}
echo json_encode($result, JSON_THROW_ON_ERROR);
