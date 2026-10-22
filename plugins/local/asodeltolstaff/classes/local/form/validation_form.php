<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff\local\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Administrative validation form.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validation_form extends \moodleform {
    /**
     * Define fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'validationstatus', get_string('validationstatus', 'local_asodeltolstaff'), [
            \local_asodeltolstaff\manager::VALIDATION_VALIDATED => get_string('status_validated', 'local_asodeltolstaff'),
            \local_asodeltolstaff\manager::VALIDATION_REJECTED => get_string('status_rejected', 'local_asodeltolstaff'),
        ]);
        $mform->setType('validationstatus', PARAM_ALPHA);
        $mform->addElement('text', 'validatedminutes', get_string('validatedminutes', 'local_asodeltolstaff'));
        $mform->setType('validatedminutes', PARAM_INT);
        $mform->addElement('textarea', 'reason', get_string('reason', 'local_asodeltolstaff'), ['rows' => 3]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('validate', 'local_asodeltolstaff'));
    }
}
