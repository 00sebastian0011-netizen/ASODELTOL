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

namespace theme_rsmax;

use core\hook\output\before_footer_html_generation;
use core\output\supplementary_sticky_footer;
use core_courseformat\output\local\linearnavigation\footer_content;

/**
 * Hook listeners of the theme.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Gives the bar that moves between pages to the course formats that do not have it.
     *
     * Moodle only adds the bar in formats that declare they support it, so in a format written
     * before it existed (tabs, grid and others) an activity has no way to go to the next one.
     * The addresses the bar uses work in any format, so the theme adds it there itself. A format
     * that supports the bar and has it turned off by the administrator is left alone.
     *
     * @param before_footer_html_generation $hook
     */
    public static function add_navigation_bar(before_footer_html_generation $hook): void {
        $page = $hook->renderer->get_page();
        if ($page->theme->name != 'rsmax' && !in_array('rsmax', $page->theme->parents)) {
            return;
        }
        if (isset($page->theme->settings->navigationbarallformats) && empty($page->theme->settings->navigationbarallformats)) {
            return;
        }
        if ($page->cm === null || $page->pagelayout != 'incourse' || $page->user_is_editing()) {
            return;
        }
        if ($page->has_sticky_footer() || !$page->should_show_navigation_footer() || $page->get_supplementary_content() !== null) {
            return;
        }
        if (course_get_format($page->course)->uses_linear_navigation()) {
            return;
        }
        if ($page->cm->is_stealth() && !has_capability('moodle/course:viewhiddenactivities', $page->cm->context)) {
            return;
        }

        $content = $hook->renderer->render(new footer_content($page->cm));
        $hook->add_html($hook->renderer->render(new supplementary_sticky_footer($content, 'course-linear-navigation')));
    }
}
