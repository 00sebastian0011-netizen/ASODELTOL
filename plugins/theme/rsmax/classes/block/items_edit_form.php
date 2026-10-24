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

namespace theme_rsmax\block;

/**
 * Settings form of the landing blocks that are a list of items: heading, layout, columns,
 * carousel, colours and the items themselves, all taken from what the block class declares.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class items_edit_form extends landing_edit_form {
    /**
     * Adds the settings of the block.
     *
     * @param \MoodleQuickForm $mform Form.
     */
    protected function specific_definition($mform) {
        $class = $this->block_class();
        $this->add_heading_fields($mform);

        $hascarousel = $class::ALWAYSCAROUSEL || in_array('carousel', $class::LAYOUTS);
        if ($class::LAYOUTS) {
            $this->add_select($mform, 'layout', $class::LAYOUTS, $this->component());
        }
        if ($class::COLUMNS) {
            $this->add_columns_field($mform, ...$class::COLUMNS);
        }
        if ($hascarousel) {
            $this->add_autoplay_field($mform);
            if (!$class::ALWAYSCAROUSEL) {
                $mform->hideIf('config_autoplay', 'config_layout', 'neq', 'carousel');
            }
        }
        if ($class::CANOVERLAP) {
            $mform->addElement('selectyesno', 'config_overlap', get_string('block:overlap', 'theme_rsmax'));
            $mform->addHelpButton('config_overlap', 'block:overlap', 'theme_rsmax');
        }
        $this->add_scheme_field($mform);
        $this->add_item_fields($mform);
    }
}
