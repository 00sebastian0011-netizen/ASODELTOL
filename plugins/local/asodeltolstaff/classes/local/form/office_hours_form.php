<?php
namespace local_asodeltolstaff\local\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Explicit calendar slot entry; Moodle supplies sesskey protection. */
final class office_hours_form extends \moodleform {
    protected function definition(): void {
        $form = $this->_form;
        $form->addElement('hidden', 'courseid');
        $form->setType('courseid', PARAM_INT);
        $form->addElement('select', 'groupid', get_string('group', 'local_asodeltolstaff'), $this->_customdata['groups']);
        $form->setType('groupid', PARAM_INT);
        $form->addElement('select', 'trainerid', get_string('trainer', 'local_asodeltolstaff'), $this->_customdata['trainers']);
        $form->setType('trainerid', PARAM_INT);
        // New-entry defaults use the native date selector options.
        $start = time() + HOURSECS;
        $form->addElement('date_time_selector', 'start', get_string('scheduledstart', 'local_asodeltolstaff'),
            ['defaulttime' => $start]);
        $form->addElement('date_time_selector', 'end', get_string('scheduledend', 'local_asodeltolstaff'),
            ['defaulttime' => $start + HOURSECS]);
        $form->addElement('url', 'meetingurl', get_string('meetingurl', 'local_asodeltolstaff'));
        $form->setType('meetingurl', PARAM_URL);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int) $data['end'] - (int) $data['start'] < HOURSECS) {
            $errors['end'] = get_string('officehoursminimum', 'local_asodeltolstaff');
        }
        return $errors;
    }
}
