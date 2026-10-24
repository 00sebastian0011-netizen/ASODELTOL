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

use core_course_list_element;

/**
 * Builds the card of a course for the course catalogue, category pages and search results.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_card {
    /** Longest summary shown on a card, in characters. */
    public const SUMMARYLENGTH = 130;

    /** Most teachers named on a card. */
    public const MAXTEACHERS = 2;

    /**
     * Returns the image of a course: its first overview image, or the pattern Moodle draws for it.
     *
     * @param core_course_list_element $course Course.
     * @return string URL.
     */
    public static function image(core_course_list_element $course): string {
        global $OUTPUT;

        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return \core\url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }
        return $OUTPUT->get_generated_image_for_id($course->id);
    }

    /**
     * Returns the price of a course, taken from the first enabled enrolment method that has a cost.
     *
     * @param int $courseid Course.
     * @return string Formatted price, empty when the course is free.
     */
    public static function price(int $courseid): string {
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if (!empty($instance->cost) && (float) $instance->cost > 0 && !empty($instance->currency)) {
                return \core_payment\helper::get_cost_as_string((float) $instance->cost, $instance->currency);
            }
        }
        return '';
    }

    /**
     * Returns the data of the card of a course.
     *
     * @param core_course_list_element $course Course.
     * @param string $name Formatted name of the course.
     * @param string $summary Formatted summary of the course, as HTML.
     * @return array
     */
    public static function export(core_course_list_element $course, string $name, string $summary): array {
        $text = html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        $teachers = [];
        foreach ($course->get_course_contacts() as $contact) {
            $teachers[] = s($contact['username']);
        }
        $category = \core_course_category::get($course->category, IGNORE_MISSING);

        $progress = null;
        if (isloggedin() && !isguestuser()) {
            $progress = \core_completion\progress::get_course_progress_percentage(get_course($course->id));
        }

        return [
            'id' => $course->id,
            'name' => $name,
            'url' => (new \core\url('/course/view.php', ['id' => $course->id]))->out(false),
            'image' => self::image($course),
            'category' => $category ? $category->get_formatted_name() : '',
            'summary' => $text === '' ? '' : shorten_text($text, self::SUMMARYLENGTH),
            'teachers' => implode(', ', array_slice($teachers, 0, self::MAXTEACHERS)),
            'price' => self::price($course->id),
            'hasprogress' => $progress !== null,
            'progress' => $progress === null ? 0 : (int) floor($progress),
            'hidden' => !$course->visible,
        ];
    }
}
