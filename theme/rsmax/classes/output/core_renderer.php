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

namespace theme_rsmax\output;

use core\context\system as context_system;
use core\url;

/**
 * Core renderer: Boost's, plus a search box that is always available.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Returns the search form shown in the navigation bar.
     *
     * It uses global search when the site has it and the user may query it, and falls back to
     * the course search otherwise, so there is always something to search with.
     *
     * @return string HTML, empty when the search box is turned off in the theme settings.
     */
    public function navbar_search(): string {
        global $CFG;

        if (!get_config('theme_rsmax', 'navbarsearch') || during_initial_install()) {
            return '';
        }
        if (!empty($CFG->forcelogin) && !isloggedin()) {
            return '';
        }

        $global = !empty($CFG->enableglobalsearch) && has_capability('moodle/search:query', context_system::instance());
        $data = [
            'action' => (new url($global ? '/search/index.php' : '/course/search.php'))->out(false),
            'inputname' => $global ? 'q' : 'search',
            'placeholder' => get_string($global ? 'search' : 'searchcourses'),
        ];
        return $this->render_from_template('theme_rsmax/navbar_search', $data);
    }

    /**
     * Renders the bar that moves between the pages of a course.
     *
     * Moodle only gives it two addresses. The theme adds what each button leads to and the place
     * of the page in its section, and draws it with its own version of the template.
     *
     * @param \core_courseformat\output\local\linearnavigation\footer_content $content
     * @return string HTML
     */
    protected function render_footer_content(
        \core_courseformat\output\local\linearnavigation\footer_content $content
    ): string {
        $data = $content->export_for_template($this);
        if ($this->page->cm) {
            $data += \theme_rsmax\local\linear_nav::export($this->page->cm);
        }
        return $this->render_from_template($content->get_template_name($this), $data);
    }
}
