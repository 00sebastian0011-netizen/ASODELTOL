<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff\event;

/**
 * Event emitted after a business record is appended to plugin history.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class record_changed extends \core\event\base {
    /**
     * Initialise event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_asstaff_history';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('history', 'local_asodeltolstaff');
    }

    /**
     * Human-readable event description without copying evidence or personal data.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' recorded action '{$this->other['action']}' "
            . "for session '{$this->other['sessionid']}'.";
    }

    /**
     * URL associated with the change.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/asodeltolstaff/history.php', [
            'id' => $this->other['sessionid'],
        ]);
    }

    /**
     * Validate required event properties.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['sessionid'], $this->other['entitytype'], $this->other['action'])) {
            throw new \coding_exception('Missing required record_changed event data.');
        }
    }
}
