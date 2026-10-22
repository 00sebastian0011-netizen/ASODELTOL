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
 * Form for scheduled and trainer-reported session values.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_form extends \moodleform {
    /**
     * Define fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $groups = $this->_customdata['groups'];
        $trainers = $this->_customdata['trainers'];
        $sections = $this->_customdata['sections'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'sessioncode', get_string('sessioncode', 'local_asodeltolstaff'), ['maxlength' => 64]);
        $mform->setType('sessioncode', PARAM_ALPHANUMEXT);
        $mform->addRule('sessioncode', null, 'required', null, 'client');

        $mform->addElement('select', 'sectionnum', get_string('section', 'local_asodeltolstaff'), $sections);
        $mform->setType('sectionnum', PARAM_INT);
        $mform->addElement('select', 'groupid', get_string('group', 'local_asodeltolstaff'), $groups);
        $mform->setType('groupid', PARAM_INT);
        $mform->addElement('select', 'trainerid', get_string('trainer', 'local_asodeltolstaff'), $trainers);
        $mform->setType('trainerid', PARAM_INT);

        $mform->addElement('date_time_selector', 'scheduledstart', get_string('scheduledstart', 'local_asodeltolstaff'));
        $mform->addElement('date_time_selector', 'scheduledend', get_string('scheduledend', 'local_asodeltolstaff'));
        $mform->addElement('date_time_selector', 'reportedstart', get_string('reportedstart', 'local_asodeltolstaff'), [
            'optional' => true,
        ]);
        $mform->addElement('date_time_selector', 'reportedend', get_string('reportedend', 'local_asodeltolstaff'), [
            'optional' => true,
        ]);
        $mform->addElement('url', 'evidenceref', get_string('evidence', 'local_asodeltolstaff'), ['maxlength' => 2048]);
        $mform->setType('evidenceref', PARAM_URL);
        $mform->addHelpButton('evidenceref', 'evidence', 'local_asodeltolstaff');
        $mform->addElement('textarea', 'reason', get_string('reason', 'local_asodeltolstaff'), ['rows' => 3]);
        $mform->setType('reason', PARAM_TEXT);
        $this->add_action_buttons(true, get_string('savesession', 'local_asodeltolstaff'));
    }

    /**
     * Client-independent interval validation.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int) $data['scheduledend'] <= (int) $data['scheduledstart']) {
            $errors['scheduledend'] = get_string('invalidscheduledperiod', 'local_asodeltolstaff');
        }
        $reportedstart = empty($data['reportedstart']) ? null : (int) $data['reportedstart'];
        $reportedend = empty($data['reportedend']) ? null : (int) $data['reportedend'];
        if (($reportedstart === null) !== ($reportedend === null)
                || ($reportedstart !== null && $reportedend <= $reportedstart)) {
            $errors['reportedend'] = get_string('invalidreportedperiod', 'local_asodeltolstaff');
        }
        if (!empty($data['evidenceref'])) {
            $parts = parse_url($data['evidenceref']);
            if (!is_array($parts)
                    || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                    || empty($parts['host'])
                    || isset($parts['user'])
                    || isset($parts['pass'])) {
                $errors['evidenceref'] = get_string('invalidevidence', 'local_asodeltolstaff');
            }
        }
        return $errors;
    }
}
