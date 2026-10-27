<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_rsmax\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Six course-specific selectors backed by Moodle activities (not free-form URLs).
 *
 * @package theme_rsmax
 */
final class course_quicklinks_form extends \moodleform {
    protected function definition(): void {
        $form = $this->_form;
        $options = $this->_customdata['options'];
        $form->addElement('hidden', 'courseid', (int) $this->_customdata['courseid']);
        $form->setType('courseid', PARAM_INT);

        foreach (\theme_rsmax\local\course_utilities::keys() as $key) {
            $field = 'ql_' . $key;
            if ($key === 'support') {
                // Centralised CAU remains visible but has no misleading course-level selector.
                $form->addElement('static', 'supportinfo', get_string('coursequicksupport', 'theme_rsmax'),
                    get_string('coursequicksupportcentral', 'theme_rsmax'));
                $form->addElement('hidden', $field, 0);
                $form->setType($field, PARAM_INT);
                continue;
            }
            $form->addElement('select', $field,
                get_string('coursequick' . $key, 'theme_rsmax'), $options[$key]);
            $form->setType($field, PARAM_INT);
            $form->addHelpButton($field, 'coursequickhelp' . $key, 'theme_rsmax');
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
