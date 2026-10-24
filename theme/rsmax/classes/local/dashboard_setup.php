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
 * Arranges the default dashboard of the site the way the theme is designed for: timeline first,
 * then the course overview, then the AI course recommender when it is installed.
 *
 * It only touches the default dashboard, the one new users get. Dashboards people have already
 * customised keep their layout until an administrator resets them.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_setup {
    /** Blocks of the main column of the dashboard, in order. Blocks that are not installed are skipped. */
    public const BLOCKS = ['timeline', 'myoverview', 'aicourserecommender'];

    /** Region of the dashboard that holds the main column. */
    public const REGION = 'content';

    /**
     * Puts the blocks of the default dashboard in order, adding the ones that are missing.
     *
     * @return string[] What was done, one line per block, for the command line and the logs.
     */
    public static function apply(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/my/lib.php');
        require_once($CFG->libdir . '/blocklib.php');

        $default = $DB->get_record('my_pages', ['userid' => null, 'name' => MY_PAGE_DEFAULT, 'private' => MY_PAGE_PRIVATE]);
        if (!$default) {
            return ['The site has no default dashboard: nothing to arrange.'];
        }
        $context = \core\context\system::instance();
        $where = ['parentcontextid' => $context->id, 'pagetypepattern' => 'my-index', 'subpagepattern' => $default->id];
        $enabled = $DB->get_records_menu('block', ['visible' => 1], '', 'name, id');

        $done = [];
        $weight = 0;
        foreach (self::BLOCKS as $name) {
            if (!isset($enabled[$name])) {
                $done[] = "$name: not installed or disabled, skipped";
                continue;
            }
            $instance = $DB->get_record('block_instances', $where + ['blockname' => $name], '*', IGNORE_MULTIPLE);
            if ($instance) {
                $DB->update_record('block_instances', [
                    'id' => $instance->id,
                    'defaultregion' => self::REGION,
                    'defaultweight' => $weight,
                    'timemodified' => time(),
                ]);
                // A position saved for the page would override the default order.
                $DB->delete_records('block_positions', ['blockinstanceid' => $instance->id]);
                $done[] = "$name: moved to position " . ($weight + 1);
            } else {
                $page = new \moodle_page();
                $page->set_context($context);
                $page->set_pagetype('my-index');
                $page->set_subpage($default->id);
                $page->set_url('/my/indexsys.php');
                $page->blocks->add_region(self::REGION, false);
                $page->blocks->add_block($name, self::REGION, $weight, false, 'my-index', $default->id);
                $done[] = "$name: added at position " . ($weight + 1);
            }
            $weight++;
        }

        // Any other block of the main column goes after ours, keeping its own order.
        $others = $DB->get_records('block_instances', $where + ['defaultregion' => self::REGION], 'defaultweight, id');
        foreach ($others as $instance) {
            if (!in_array($instance->blockname, self::BLOCKS)) {
                $DB->set_field('block_instances', 'defaultweight', $weight++, ['id' => $instance->id]);
            }
        }
        return $done;
    }
}
