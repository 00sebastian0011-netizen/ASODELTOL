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
 * Arranges the default dashboard for the theme: timeline, course overview and AI recommender.
 *
 * Usage: php theme/rsmax/cli/setup_dashboard.php [--reset-users]
 *
 * Without options only the default dashboard changes, the one new users get. With --reset-users
 * every user's dashboard is reset to that default, which discards their own changes.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/my/lib.php');

[$options, $unrecognised] = cli_get_params(['reset-users' => false, 'help' => false], ['h' => 'help']);
if ($unrecognised || $options['help']) {
    cli_writeln('Arranges the default dashboard: timeline, course overview and AI recommender.');
    cli_writeln('Options: --reset-users  also reset the dashboard of every user to that default.');
    exit($unrecognised ? 1 : 0);
}

foreach (\theme_rsmax\local\dashboard_setup::apply() as $line) {
    cli_writeln($line);
}
if ($options['reset-users']) {
    my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index');
    cli_writeln('Every user now has the default dashboard.');
}
