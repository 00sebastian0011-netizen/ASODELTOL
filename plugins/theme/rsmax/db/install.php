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

/**
 * Runs when the theme is installed.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Arranges the default dashboard for the theme.
 */
function xmldb_theme_rsmax_install() {
    \theme_rsmax\local\dashboard_setup::apply();
    // The blocks of the theme are installed after it and add themselves then.
    \theme_rsmax\local\course_blocks::setup();
    // The AI assistant, when the site already has it; otherwise it is arranged when it arrives.
    \theme_rsmax\local\assistant::ensure();
}
