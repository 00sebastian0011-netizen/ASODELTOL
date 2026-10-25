<?php
namespace local_asodeltolstaff\local\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

final class support_form extends \moodleform {
    protected function definition(): void {
        $f = $this->_form;
        $f->addElement('text', 'subject', get_string('subject', 'forum'), ['maxlength' => 160]);
        $f->setType('subject', PARAM_TEXT);
        $f->addRule('subject', null, 'required');
        $f->addElement('textarea', 'body', get_string('message', 'message'), ['rows' => 6, 'maxlength' => 4000]);
        $f->setType('body', PARAM_TEXT);
        $f->addRule('body', null, 'required');
        $this->add_action_buttons(true, get_string('sendmessage', 'message'));
    }
}
