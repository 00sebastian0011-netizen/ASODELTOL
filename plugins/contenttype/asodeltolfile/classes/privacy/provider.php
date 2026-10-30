<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace contenttype_asodeltolfile\privacy;

/**
 * Privacy provider: this plugin stores no personal data (core content bank owns the records).
 *
 * @package    contenttype_asodeltolfile
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Reason string identifier.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
