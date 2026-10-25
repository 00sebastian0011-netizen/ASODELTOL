<?php
namespace local_asodeltolstaff\local\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

final class communication_form extends \moodleform {
    protected function definition(): void {
        $f = $this->_form;
        $f->addElement('hidden', 'courseid');
        $f->setType('courseid', PARAM_INT);
        $f->addElement('select', 'groupid', get_string('group'), $this->_customdata['groups']);
        $f->setType('groupid', PARAM_INT);
        $actions = ['announcement' => get_string('groupannouncement', 'local_asodeltolstaff'),
            'schedule' => get_string('groupschedule', 'local_asodeltolstaff')];
        if ($this->_customdata['canopen']) {
            $actions['open'] = get_string('opengroupsection', 'local_asodeltolstaff');
        }
        $f->addElement('select', 'action', get_string('action'), $actions);
        $f->setType('action', PARAM_ALPHA);
        $f->addElement('select', 'section', get_string('section', 'local_asodeltolstaff'), $this->_customdata['sections']);
        $f->setType('section', PARAM_INT);
        $f->addElement('text', 'title', get_string('name'), ['maxlength' => 255]);
        $f->setType('title', PARAM_TEXT);
        $f->addElement('textarea', 'body', get_string('description'), ['rows' => 5]);
        $f->setType('body', PARAM_TEXT);
        $f->addElement('date_time_selector', 'start', get_string('startdate'), ['optional' => true]);
        $f->addElement('date_time_selector', 'end', get_string('enddate'), ['optional' => true]);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data['action'] !== 'open' && (trim($data['title']) === '' || trim($data['body']) === '')) {
            $errors['title'] = get_string('required');
        }
        if ($data['action'] === 'schedule' && (empty($data['start']) || $data['end'] <= $data['start'])) {
            $errors['end'] = get_string('invalidinterval', 'local_asodeltolstaff');
        }
        return $errors;
    }
}
