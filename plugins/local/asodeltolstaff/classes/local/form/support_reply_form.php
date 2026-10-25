<?php
namespace local_asodeltolstaff\local\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

final class support_reply_form extends \moodleform {
    protected function definition(): void {
        $f = $this->_form;
        $f->addElement('hidden', 'caseid');
        $f->setType('caseid', PARAM_INT);
        $f->addElement('textarea', 'response', get_string('supportreply', 'local_asodeltolstaff'), ['rows' => 5]);
        $f->setType('response', PARAM_TEXT);
        $f->addRule('response', null, 'required');
        $f->addElement('advcheckbox', 'resolved', get_string('supportresolve', 'local_asodeltolstaff'));
        $this->add_action_buttons(true, get_string('sendmessage', 'message'));
    }
}
