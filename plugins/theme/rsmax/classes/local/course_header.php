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

use core_course\external\course_summary_exporter;
use stdClass;

/**
 * Builds the header of the main page of a course: image, category and teachers.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_header {
    /** Most teachers named in the header. */
    public const MAXTEACHERS = 3;

    /**
     * Returns the data of the header of a course.
     *
     * @param stdClass $course Course record.
     * @return array name, image, category and teachers (a list of names).
     */
    public static function export(stdClass $course): array {
        $context = \core\context\course::instance($course->id);
        $category = \core_course_category::get($course->category, IGNORE_MISSING);
        $teachers = [];
        foreach ((new \core_course_list_element($course))->get_course_contacts() as $contact) {
            $teachers[] = s($contact['username']);
        }
        return [
            'name' => format_string($course->fullname, true, ['context' => $context]),
            // Without a picture the header keeps the background of the theme.
            'image' => course_summary_exporter::get_course_image($course) ?: '',
            'category' => $category ? $category->get_formatted_name() : '',
            'teachers' => implode(', ', array_slice($teachers, 0, self::MAXTEACHERS)),
        ];
    }
}
