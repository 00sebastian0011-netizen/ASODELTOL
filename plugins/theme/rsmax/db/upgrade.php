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
 * Upgrade steps.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the theme.
 *
 * @param int $oldversion Version being upgraded from.
 * @return bool
 */
function xmldb_theme_rsmax_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026100710) {
        // Sites that installed an earlier version get the dashboard arrangement new installs get.
        \theme_rsmax\local\dashboard_setup::apply();
        upgrade_plugin_savepoint(true, 2026100710, 'theme', 'rsmax');
    }
    if ($oldversion < 2026100716) {
        // The table with the options of each course: where its banner is shown.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('theme_rsmax_course');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('bannerscope', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // The palette became warm. Colours still at their old default follow the new one;
        // a colour somebody chose is left alone.
        require_once(__DIR__ . '/../lib.php');
        foreach (THEME_RSMAX_OLD_COLOURS as $setting => $old) {
            if (strtolower((string) get_config('theme_rsmax', $setting)) === $old) {
                set_config($setting, THEME_RSMAX_COLOURS[$setting]['default'], 'theme_rsmax');
            }
        }
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026100716, 'theme', 'rsmax');
    }
    if ($oldversion < 2026100719) {
        // New courses start with the blocks of the theme, unless the site already decided otherwise.
        \theme_rsmax\local\course_blocks::setup();
        upgrade_plugin_savepoint(true, 2026100719, 'theme', 'rsmax');
    }
    if ($oldversion < 2026100725) {
        // The text of the buttons was white by default, which does not read on a light brand
        // colour. The default is now whichever reads; sites still on the old default follow it.
        if (get_config('theme_rsmax', 'buttontext') === 'light') {
            set_config('buttontext', 'auto', 'theme_rsmax');
        }
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026100725, 'theme', 'rsmax');
    }
    if ($oldversion < 2026100726) {
        // The header of the activities and their background now follow the dark colour and the
        // background of the site unless they are given a colour of their own.
        foreach (['stagecolor' => '#2b2624', 'papercolor' => '#f6f1ea'] as $setting => $old) {
            if (strtolower((string) get_config('theme_rsmax', $setting)) === $old) {
                set_config($setting, '', 'theme_rsmax');
            }
        }
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026100726, 'theme', 'rsmax');
    }
    if ($oldversion < 2026100727) {
        // The AI assistant: in new courses, and one for the site on its public pages.
        \theme_rsmax\local\assistant::ensure();
        upgrade_plugin_savepoint(true, 2026100727, 'theme', 'rsmax');
    }
    if ($oldversion < 2026100731) {
        // The content column is wider by default. Sites still on the old default follow it; a
        // width somebody chose is left alone.
        if ((int) get_config('theme_rsmax', 'contentwidth') === 1120) {
            set_config('contentwidth', 1280, 'theme_rsmax');
        }
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026100731, 'theme', 'rsmax');
    }
    return true;
}
