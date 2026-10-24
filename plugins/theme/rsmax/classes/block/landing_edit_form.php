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

use MoodleQuickForm;

/**
 * Base class of the settings forms of the Pluginia landing blocks.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class landing_edit_form extends \block_edit_form {
    /**
     * Returns the class of the block this form configures.
     *
     * @return string
     */
    protected function block_class(): string {
        return get_class($this->block);
    }

    /**
     * Returns the frankenstyle name of the block plugin.
     *
     * @return string
     */
    protected function component(): string {
        return 'block_' . $this->block->name();
    }

    /**
     * Adds the heading fields every landing block has: title and subtitle.
     *
     * @param MoodleQuickForm $mform Form.
     */
    protected function add_heading_fields(MoodleQuickForm $mform): void {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $this->add_text($mform, 'title');
        $this->add_text($mform, 'subtitle');
        $this->add_select($mform, 'audience', landing_block::AUDIENCES, 'theme_rsmax', null, 'block:audience');
        $mform->addHelpButton('config_audience', 'block:audience', 'theme_rsmax');
    }

    /**
     * Adds a single line text field named config_$name, labelled with the string "block:$name".
     *
     * @param MoodleQuickForm $mform Form.
     * @param string $name Setting name.
     * @param string|null $component Plugin with the label; the theme by default.
     */
    protected function add_text(MoodleQuickForm $mform, string $name, ?string $component = null): void {
        $mform->addElement('text', 'config_' . $name, get_string('block:' . $name, $component ?? 'theme_rsmax'), ['size' => 60]);
        $mform->setType('config_' . $name, PARAM_TEXT);
    }

    /**
     * Adds a select named config_$name whose options are strings "$prefix:$option" of the plugin.
     *
     * @param MoodleQuickForm $mform Form.
     * @param string $name Setting name.
     * @param array $options Option keys.
     * @param string $component Plugin with the strings.
     * @param string|null $default Default option; the first one when null.
     * @param string|null $prefix String prefix; the setting name when null.
     */
    protected function add_select(
        MoodleQuickForm $mform,
        string $name,
        array $options,
        string $component,
        ?string $default = null,
        ?string $prefix = null
    ): void {
        $prefix ??= $name;
        $choices = [];
        foreach ($options as $option) {
            $choices[$option] = get_string($prefix . ':' . $option, $component);
        }
        $mform->addElement('select', 'config_' . $name, get_string($prefix, $component), $choices);
        $mform->setDefault('config_' . $name, $default ?? reset($options));
    }

    /**
     * Adds a yes/no select named config_$name, labelled with a string of the block.
     *
     * @param MoodleQuickForm $mform Form.
     * @param string $name Setting name and string identifier.
     * @param int $default Default value.
     */
    protected function add_yesno(MoodleQuickForm $mform, string $name, int $default = 0): void {
        $mform->addElement('selectyesno', 'config_' . $name, get_string($name, $this->component()));
        $mform->setDefault('config_' . $name, $default);
    }

    /**
     * Adds a select of whole numbers named config_$name.
     *
     * @param MoodleQuickForm $mform Form.
     * @param string $name Setting name.
     * @param int $min Smallest number.
     * @param int $max Largest number.
     * @param int $default Default number.
     * @param string $label Label.
     */
    protected function add_number(MoodleQuickForm $mform, string $name, int $min, int $max, int $default, string $label): void {
        $numbers = range($min, $max);
        $mform->addElement('select', 'config_' . $name, $label, array_combine($numbers, $numbers));
        $mform->setDefault('config_' . $name, $default);
    }

    /**
     * Adds the selector of items per row.
     *
     * @param MoodleQuickForm $mform Form.
     * @param int $default Default number of columns.
     * @param int $min Fewest columns.
     * @param int $max Most columns.
     */
    protected function add_columns_field(MoodleQuickForm $mform, int $default = 3, int $min = 2, int $max = 4): void {
        $this->add_number($mform, 'columns', $min, $max, $default, get_string('block:columns', 'theme_rsmax'));
    }

    /**
     * Adds the colour scheme selector.
     *
     * @param MoodleQuickForm $mform Form.
     */
    protected function add_scheme_field(MoodleQuickForm $mform): void {
        $this->add_select($mform, 'scheme', landing_block::SCHEMES, 'theme_rsmax', null, 'block:scheme');
        foreach (array_keys(landing_block::CUSTOMCOLOURS) as $name) {
            $mform->addElement(
                'text',
                'config_' . $name,
                get_string('block:' . $name, 'theme_rsmax'),
                ['size' => 10, 'placeholder' => '#00a8d6', 'maxlength' => 9]
            );
            $mform->setType('config_' . $name, PARAM_TEXT);
            $mform->hideIf('config_' . $name, 'config_scheme', 'neq', 'custom');
        }
        $mform->addHelpButton('config_custombg', 'block:custombg', 'theme_rsmax');
    }

    /**
     * Checks the settings shared by all landing blocks: custom colours must be hexadecimal.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors by field.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (array_keys(landing_block::CUSTOMCOLOURS) as $name) {
            $value = trim((string) ($data['config_' . $name] ?? ''));
            if ($value !== '' && landing_block::clean_colour($value) === '') {
                $errors['config_' . $name] = get_string('block:invalidcolour', 'theme_rsmax');
            }
        }
        return $errors;
    }

    /**
     * Adds the automatic advance selector of a carousel.
     *
     * @param MoodleQuickForm $mform Form.
     */
    protected function add_autoplay_field(MoodleQuickForm $mform): void {
        $choices = [];
        foreach (landing_block::AUTOPLAY as $seconds) {
            $choices[$seconds] = $seconds
                ? get_string('block:autoplay:seconds', 'theme_rsmax', $seconds)
                : get_string('block:autoplay:off', 'theme_rsmax');
        }
        $mform->addElement('select', 'config_autoplay', get_string('block:autoplay', 'theme_rsmax'), $choices);
        $mform->setDefault('config_autoplay', 0);
    }

    /**
     * Adds a file manager for one of the block file areas.
     *
     * @param MoodleQuickForm $mform Form.
     * @param string $area File area, as declared in file_areas() of the block.
     * @param array $options File manager options.
     */
    protected function add_image_field(MoodleQuickForm $mform, string $area, array $options): void {
        $mform->addElement('filemanager', 'config_' . $area, get_string('block:image', 'theme_rsmax'), null, $options);
    }

    /**
     * Returns how many items the instance has stored.
     *
     * @return int
     */
    protected function stored_items(): int {
        $indexes = $this->block->item_indexes();
        return $indexes ? max($indexes) + 1 : 0;
    }

    /**
     * Adds the repeated fields of the items declared by the block, with buttons to add and delete items.
     *
     * Labels are the strings "item:<field>" of the block.
     *
     * @param MoodleQuickForm $mform Form.
     * @param int $initial Empty items offered on a new block.
     */
    protected function add_item_fields(MoodleQuickForm $mform, int $initial = 3): void {
        $component = $this->component();
        $elements = [$mform->createElement('header', 'itemheader', get_string('item', $component) . ' {no}')];
        $options = [];
        foreach ($this->block_class()::item_fields() as $field => $type) {
            $name = 'config_item_' . $field;
            $label = get_string('item:' . $field, $component);
            switch ($type) {
                case 'textarea':
                    $elements[] = $mform->createElement('textarea', $name, $label, ['rows' => 3, 'cols' => 60]);
                    $options[$name]['type'] = PARAM_TEXT;
                    break;
                case 'image':
                    $elements[] = $mform->createElement('filemanager', $name, $label, null, landing_block::IMAGE_OPTIONS);
                    break;
                case 'yesno':
                    $elements[] = $mform->createElement('selectyesno', $name, $label);
                    break;
                case 'icon':
                    $elements[] = $mform->createElement('text', $name, $label, ['size' => 30, 'placeholder' => 'fa-rocket']);
                    $options[$name]['type'] = PARAM_ALPHANUMEXT;
                    $options[$name]['helpbutton'] = ['block:icon', 'theme_rsmax'];
                    break;
                default:
                    $elements[] = $mform->createElement('text', $name, $label, ['size' => 60]);
                    $options[$name]['type'] = PARAM_TEXT;
            }
        }
        $delete = get_string('block:deleteitem', 'theme_rsmax');
        $elements[] = $mform->createElement('submit', 'config_deleteitem', $delete, [], false);

        $this->repeat_elements(
            $elements,
            max($this->stored_items(), $initial),
            $options,
            'config_items',
            'config_additems',
            2,
            get_string('block:additems', 'theme_rsmax'),
            true,
            'config_deleteitem'
        );
    }

    /**
     * Loads the instance settings and puts the stored images in draft areas for editing.
     *
     * @param \stdClass $defaults Default values.
     */
    public function set_data($defaults) {
        $class = $this->block_class();
        $component = $this->component();
        $contextid = $this->block->context->id;

        foreach ($class::file_areas() as $area => $options) {
            $draftitemid = file_get_submitted_draft_itemid('config_' . $area);
            file_prepare_draft_area($draftitemid, $contextid, $component, $area, 0, $options);
            $defaults->{'config_' . $area} = $draftitemid;
        }

        $count = $this->stored_items();
        foreach ($class::item_fields() as $field => $type) {
            if ($type != 'image') {
                continue;
            }
            $drafts = [];
            for ($index = 0; $index < $count; $index++) {
                $draftitemid = 0;
                file_prepare_draft_area(
                    $draftitemid,
                    $contextid,
                    $component,
                    $class::item_area($field),
                    $index,
                    landing_block::IMAGE_OPTIONS
                );
                $drafts[$index] = $draftitemid;
            }
            $defaults->{'config_item_' . $field} = $drafts;
        }
        parent::set_data($defaults);
    }
}
