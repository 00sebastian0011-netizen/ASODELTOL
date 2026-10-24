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

namespace theme_rsmax\local;

/**
 * The blocks a new course starts with.
 *
 * Moodle reads them from the site value "defaultblocks_override". The theme writes that value
 * from its own setting, and only ever replaces a value it wrote itself: one an administrator put
 * in config.php, or set by other means, is left alone.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_blocks {
    /**
     * Blocks of a new course, when they are installed: who teaches it, what is coming, what
     * people think of it and the AI assistant of the course.
     */
    public const DEFAULTS = ['pluginia_people', 'calendar_upcoming', 'pluginia_course_rating', 'openaiagent'];

    /**
     * Returns the blocks chosen in the theme settings that exist on the site.
     *
     * @return string[]
     */
    public static function chosen(): array {
        $chosen = array_filter(explode(',', (string) get_config('theme_rsmax', 'coursedefaultblocks')));
        return array_values(array_intersect($chosen, array_keys(\core_component::get_plugin_list('block'))));
    }

    /**
     * Writes the chosen blocks where Moodle looks for the blocks of a new course.
     *
     * @return bool False when the site keeps a value of its own and nothing was written.
     */
    public static function apply(): bool {
        global $CFG;

        if (isset($CFG->config_php_settings['defaultblocks_override'])) {
            return false;
        }
        $written = (string) get_config('theme_rsmax', 'defaultblockswritten');
        $current = (string) get_config('core', 'defaultblocks_override');
        if ($current !== '' && $current !== $written) {
            return false;
        }
        // Blocks before the colon go to the left and after it to the right: the theme has one side panel.
        $chosen = self::chosen();
        $value = $chosen ? ':' . implode(',', $chosen) : '';
        if ($value === '') {
            unset_config('defaultblocks_override');
        } else {
            set_config('defaultblocks_override', $value);
        }
        set_config('defaultblockswritten', $value, 'theme_rsmax');
        return true;
    }

    /**
     * Sets the blocks of new courses for the first time, with those of the defaults that exist.
     */
    public static function setup(): void {
        if (get_config('theme_rsmax', 'coursedefaultblocks') === false) {
            $existing = array_keys(\core_component::get_plugin_list('block'));
            set_config('coursedefaultblocks', implode(',', array_intersect(self::DEFAULTS, $existing)), 'theme_rsmax');
        }
        self::apply();
    }

    /**
     * Adds a block of the theme to the blocks of new courses when it is installed.
     *
     * The blocks depend on the theme, so they are installed after it: each one of the defaults
     * calls this from its own installation.
     *
     * @param string $block Name of the block, without the block_ prefix.
     */
    public static function installed(string $block): void {
        if (!in_array($block, self::DEFAULTS)) {
            return;
        }
        $chosen = array_filter(explode(',', (string) get_config('theme_rsmax', 'coursedefaultblocks')));
        if (in_array($block, $chosen)) {
            return;
        }
        // In the order of the defaults, whatever order the blocks are installed in.
        $chosen[] = $block;
        $chosen = array_merge(array_intersect(self::DEFAULTS, $chosen), array_diff($chosen, self::DEFAULTS));
        set_config('coursedefaultblocks', implode(',', $chosen), 'theme_rsmax');
        self::apply();
    }
}
