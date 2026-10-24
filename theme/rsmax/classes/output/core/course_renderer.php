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

namespace theme_rsmax\output\core;

use core_course_list_element;
use coursecat_helper;
use stdClass;
use theme_rsmax\local\course_card;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/renderer.php');

/**
 * Course renderer: Moodle's, with the courses of the catalogue shown as cards.
 *
 * Only the box of one course changes. Lists, categories, paging and search stay Moodle's.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_renderer extends \core_course_renderer {
    /**
     * Renders one course of a list as a card.
     *
     * @param coursecat_helper $chelper Display settings of the list.
     * @param core_course_list_element|stdClass $course Course.
     * @param string $additionalclasses Classes Moodle adds to the box, such as first, last, odd and even.
     * @return string
     */
    protected function coursecat_coursebox(coursecat_helper $chelper, $course, $additionalclasses = '') {
        if ($chelper->get_show_courses() <= self::COURSECAT_SHOW_COURSES_COUNT) {
            return '';
        }
        if ($course instanceof stdClass) {
            $course = new core_course_list_element($course);
        }
        $summary = $course->has_summary()
            ? $chelper->get_course_formatted_summary($course, ['overflowdiv' => false, 'noclean' => false, 'para' => false])
            : '';
        $data = course_card::export($course, $chelper->get_course_formatted_name($course), $summary);
        return $this->render_from_template('theme_rsmax/course_card', $data);
    }

    /**
     * The box with the course shown above the enrolment options.
     *
     * On the landing page of the course the theme already presents the course, so it is left out.
     *
     * @param stdClass $course Course record.
     * @return string HTML
     */
    public function course_info_box(stdClass $course) {
        $settings = $this->page->theme->settings;
        if ($this->page->pagetype == 'enrol-index' && (!isset($settings->enrollanding) || !empty($settings->enrollanding))) {
            return '';
        }
        return parent::course_info_box($course);
    }
}
