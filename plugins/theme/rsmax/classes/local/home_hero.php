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

use core\url;

/**
 * The opening of the site home when nobody has built one: the name of the site, a line about
 * it, a search box for its courses and the way to the catalogue.
 *
 * It needs nothing but Moodle itself. A home page built with landing blocks has its own opening,
 * so this one steps aside as soon as the page holds one of them.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home_hero {
    /** Beginning of the name of the blocks a landing home page is built with. */
    public const LANDING_BLOCKS = 'pluginia_';

    /**
     * Whether the page is built with landing blocks.
     *
     * Only a block that shows something counts: one that is hidden, or has nothing to show to
     * this person, does not open the page.
     *
     * @param \moodle_page $page The page being shown.
     * @return bool
     */
    public static function is_built_with_blocks(\moodle_page $page): bool {
        foreach ($page->blocks->get_regions() as $region) {
            foreach ($page->blocks->get_blocks_for_region($region) as $block) {
                if (!str_starts_with($block->name(), self::LANDING_BLOCKS) || empty($block->instance->visible)) {
                    continue;
                }
                if (!empty($block->get_content()->text)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns the data of the opening of the home page.
     *
     * @param \stdClass $settings Settings of the theme.
     * @return array
     */
    public static function export(\stdClass $settings): array {
        global $CFG, $SITE;

        $context = \core\context\system::instance();
        $title = trim((string) ($settings->homeherotitle ?? ''));
        $text = trim((string) ($settings->homeherotext ?? ''));
        $loggedin = isloggedin() && !isguestuser();
        // A site that asks everybody to log in does not let a visitor search its courses.
        $cansearch = empty($CFG->forcelogin) || $loggedin;

        return [
            'title' => format_string($title !== '' ? $title : $SITE->fullname, true, ['context' => $context]),
            'text' => $text !== '' ? format_text($text, FORMAT_PLAIN, ['context' => $context, 'para' => false]) : '',
            'cansearch' => $cansearch,
            'searchurl' => (new url('/course/search.php'))->out(false),
            'coursesurl' => (new url('/course/index.php'))->out(false),
            'secondurl' => $loggedin ? (new url('/my/'))->out(false) : get_login_url(),
            'secondtext' => $loggedin ? get_string('myhome') : get_string('login'),
        ];
    }
}
