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
 * Form for provisional attendance declarations.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attendance_form extends \moodleform {
    /**
     * Define fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $students = $this->_customdata['students'];
        $current = $this->_customdata['current'];
        $statuses = [];
        foreach (\local_asodeltolstaff\manager::ATTENDANCE_STATUSES as $status) {
            $statuses[$status] = get_string('status_' . $status, 'local_asodeltolstaff');
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        foreach ($students as $student) {
            $field = 'status_' . (int) $student->id;
            $mform->addElement('select', $field, fullname($student), $statuses);
            $mform->setType($field, PARAM_ALPHANUMEXT);
            if (isset($current[$student->id])) {
                $mform->setDefault($field, $current[$student->id]->status);
            }
        }
        $mform->addElement('textarea', 'reason', get_string('reason', 'local_asodeltolstaff'), ['rows' => 3]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('saveattendance', 'local_asodeltolstaff'));
    }
}
