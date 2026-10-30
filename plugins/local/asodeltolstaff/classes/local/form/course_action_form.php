<?php
namespace local_asodeltolstaff\local\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

final class course_action_form extends \moodleform {
    protected function definition(): void {
        $m = $this->_form;
        $m->addElement('hidden', 'id'); $m->setType('id', PARAM_INT);
        $m->addElement('text', 'idnumber', get_string('idnumber')); $m->setType('idnumber', PARAM_TEXT);
        $m->addElement('text', 'fullname', get_string('fullnamecourse')); $m->setType('fullname', PARAM_TEXT);
        $m->addElement('editor', 'summary_editor', get_string('coursesummary')); $m->setType('summary_editor', PARAM_RAW);
        $m->addElement('selectyesno', 'visible', get_string('visible'));
        $m->addElement('date_time_selector', 'startdate', get_string('startdate'));
        $m->addElement('date_time_selector', 'enddate', get_string('enddate'));
        $m->addElement('select', 'lang', get_string('language'), get_string_manager()->get_list_of_translations());
        $m->addElement('text', 'adminroleid', get_string('adminroleid', 'local_asodeltolstaff')); $m->setType('adminroleid', PARAM_ALPHANUMEXT);
        $m->addElement('text', 'auditorroleid', get_string('auditorroleid', 'local_asodeltolstaff')); $m->setType('auditorroleid', PARAM_ALPHANUMEXT);
        foreach (['idnumber','fullname','summary_editor','lang','adminroleid','auditorroleid'] as $field) {
            $m->addRule($field, get_string('required'), 'required');
        }
        $this->add_action_buttons();
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)$data['enddate'] <= (int)$data['startdate']) {
            $errors['enddate'] = get_string('enddatebeforestartdate');
        }
        return $errors;
    }
}
