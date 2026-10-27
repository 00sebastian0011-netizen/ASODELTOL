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
 * Multiple native course destinations per category, with one central CAU.
 *
 * @package theme_rsmax
 */
final class course_quicklinks_form extends \moodleform {
    protected function definition(): void {
        $form = $this->_form;
        $options = $this->_customdata['options'];
        $form->addElement('hidden', 'courseid', (int) $this->_customdata['courseid']);
        $form->setType('courseid', PARAM_INT);

        foreach (\theme_rsmax\local\course_quicklink_groups::keys() as $key) {
            $field = 'ql_' . $key;
            $form->addElement('autocomplete', $field,
                get_string('coursequick' . $key, 'theme_rsmax'), $options, [
                    'multiple' => true,
                    'tags' => false,
                ]);
            $form->setType($field, PARAM_RAW_TRIMMED);
            $form->addHelpButton($field, 'coursequickhelp' . $key, 'theme_rsmax');
        }

        $form->addElement('static', 'supportinfo',
            get_string('coursequicksupport', 'theme_rsmax'),
            get_string('coursequicksupportcentral', 'theme_rsmax'));

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
