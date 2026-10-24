<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_rsmax\form;

use theme_rsmax\local\banner;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to upload the banner of a course or of one of its sections.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class banner_form extends \moodleform {
    /**
     * Defines the fields: the picture and, for the course, where it is shown.
     */
    protected function definition() {
        $mform = $this->_form;
        $sectionname = $this->_customdata['sectionname'] ?? '';

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sectionid');
        $mform->setType('sectionid', PARAM_INT);

        $label = $sectionname === ''
            ? get_string('bannercourse', 'theme_rsmax')
            : get_string('bannersection', 'theme_rsmax', $sectionname);
        $mform->addElement('filemanager', 'banner', $label, null, banner::file_options());
        $mform->addElement('static', 'bannerhelp', '', get_string('bannersize', 'theme_rsmax'));

        if ($sectionname === '') {
            $mform->addElement('select', 'scope', get_string('bannerscope', 'theme_rsmax'), [
                banner::SCOPE_HOME => get_string('bannerscope:home', 'theme_rsmax'),
                banner::SCOPE_ALL => get_string('bannerscope:all', 'theme_rsmax'),
            ]);
            $mform->addHelpButton('scope', 'bannerscope', 'theme_rsmax');
        }

        $this->add_action_buttons($sectionname !== '');
    }
}
