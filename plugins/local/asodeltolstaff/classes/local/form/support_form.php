<?php
namespace local_asodeltolstaff\local\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

final class support_form extends \moodleform {
    protected function definition(): void {
        $f = $this->_form;
        $f->addElement('select', 'category', get_string('supportcategory', 'local_asodeltolstaff'),
            ['' => get_string('supportcategory_choose', 'local_asodeltolstaff')]
                + \local_asodeltolstaff\support::categories());
        $f->setType('category', PARAM_ALPHANUMEXT);
        $f->addRule('category', null, 'required');
        $f->addElement('text', 'subject', get_string('subject', 'forum'), ['maxlength' => 80]);
        $f->setType('subject', PARAM_TEXT);
        $f->addRule('subject', null, 'required');
        $f->addElement('textarea', 'body', get_string('message', 'message'), ['rows' => 6, 'maxlength' => 4000]);
        $f->setType('body', PARAM_TEXT);
        $f->addRule('body', null, 'required');
        $this->add_action_buttons(true, get_string('sendmessage', 'message'));
    }
}
