<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Navigation integration for local_asodeltolstaff.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a course navigation entry only for authorised users.
 *
 * @param navigation_node $navigation Course navigation.
 * @param stdClass $course Course.
 * @param context_course $context Course context.
 */
function local_asodeltolstaff_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('local/asodeltolstaff:viewsessions', $context)) {
        return;
    }
    $navigation->add(
        get_string('stafftracking', 'local_asodeltolstaff'),
        new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_asodeltolstaff'
    );
}
