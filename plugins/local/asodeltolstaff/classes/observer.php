<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff;

/**
 * Event observer entry points.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Notify the beneficiary only after Moodle records course completion.
     *
     * The notification is auxiliary: any failure must not roll back or
     * invalidate Moodle's native completion record.
     *
     * @param \core\event\course_completed $event Course completion event.
     */
    public static function course_completed(\core\event\course_completed $event): void {
        try {
            final_grade_notification::send($event);
        } catch (\Throwable) {
            debugging('ASODELTOL final grade notification could not be sent.', DEBUG_DEVELOPER);
        }
    }
}
