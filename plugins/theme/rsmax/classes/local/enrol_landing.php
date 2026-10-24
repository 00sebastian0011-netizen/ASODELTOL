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

use core\context\course as context_course;
use core_course_list_element;
use stdClass;

/**
 * Data of the page where somebody who is not in a course yet decides to join it.
 *
 * Moodle shows there a course box and the enrolment forms. The theme turns it into the landing
 * page of the course: what it is about, what it contains, who teaches it and what others think
 * of it, beside a card with the way in.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_landing {
    /** The region of the course page whose blocks are shown on this page. */
    public const REGION = 'enrol-page';

    /** How much of the content of the course is listed: nothing, its sections, or its activities too. */
    public const CONTENTS = ['none', 'sections', 'activities'];

    /** Most teachers shown. */
    public const MAXTEACHERS = 6;

    /**
     * Returns the data of the landing page of a course.
     *
     * @param stdClass $course Course record.
     * @param string $contents One of CONTENTS.
     * @return array
     */
    public static function export(stdClass $course, string $contents = 'sections'): array {
        global $CFG, $OUTPUT;
        require_once($CFG->dirroot . '/course/renderer.php');

        $context = context_course::instance($course->id);
        $element = new core_course_list_element($course);
        $category = \core_course_category::get($course->category, IGNORE_MISSING);

        $summary = '';
        if ($element->has_summary()) {
            $helper = new \coursecat_helper();
            $summary = $helper->get_course_formatted_summary($element, ['overflowdiv' => false, 'noclean' => false]);
        }

        $teachers = [];
        foreach (array_slice($element->get_course_contacts(), 0, self::MAXTEACHERS, true) as $userid => $contact) {
            $user = \core_user::get_user($userid);
            $teachers[] = [
                'name' => s($contact['username']),
                'role' => $contact['rolename'],
                'picture' => $user ? $OUTPUT->user_picture($user, ['size' => 100, 'link' => false, 'alttext' => false]) : '',
            ];
        }

        $sections = self::sections($course, $contents == 'activities');
        $activities = array_sum(array_column($sections, 'count'));

        $facts = [];
        if ($sections) {
            $facts[] = ['icon' => 'fa-layer-group', 'value' => count($sections),
                'label' => get_string('enrolfactsections', 'theme_rsmax')];
        }
        if ($activities) {
            $facts[] = ['icon' => 'fa-list-check', 'value' => $activities,
                'label' => get_string('enrolfactactivities', 'theme_rsmax')];
        }
        // The dates set in the course settings, past or to come: when it starts and when it ends.
        $now = time();
        if (!empty($course->startdate)) {
            $facts[] = ['icon' => 'fa-calendar-day', 'isdate' => true,
                'value' => userdate($course->startdate, get_string('strftimedatefullshort')),
                'label' => get_string($course->startdate > $now ? 'enrolfactstarts' : 'enrolfactstarted', 'theme_rsmax')];
        }
        if (!empty($course->enddate)) {
            $facts[] = ['icon' => 'fa-flag-checkered', 'isdate' => true,
                'value' => userdate($course->enddate, get_string('strftimedatefullshort')),
                'label' => get_string($course->enddate > $now ? 'enrolfactends' : 'enrolfactended', 'theme_rsmax')];
        }
        if (!empty($course->enablecompletion)) {
            $facts[] = ['icon' => 'fa-chart-simple', 'value' => get_string('enrolfactprogressvalue', 'theme_rsmax'),
                'label' => get_string('enrolfactprogress', 'theme_rsmax')];
        }

        $price = course_card::price($course->id);
        $details = self::details($course->id);
        $tags = self::tags($course->id);
        $deadline = self::deadline($course->id);
        return [
            'name' => format_string($course->fullname, true, ['context' => $context]),
            'category' => $category ? $category->get_formatted_name() : '',
            'image' => course_card::image($element),
            'banner' => banner::for_page($course->id, 0, true) ?: course_card::image($element),
            'summary' => $summary,
            'hassummary' => trim(strip_tags($summary)) !== '',
            'teachers' => $teachers,
            'hasteachers' => !empty($teachers),
            'teachernames' => implode(', ', array_slice(array_column($teachers, 'name'), 0, 3)),
            'facts' => $facts,
            'hasfacts' => !empty($facts),
            'sections' => $contents == 'none' ? [] : $sections,
            'hassections' => $contents != 'none' && !empty($sections),
            'showactivities' => $contents == 'activities',
            'rating' => self::rating($course->id),
            'price' => $price,
            'isfree' => $price === '',
            'details' => $details,
            'hasdetails' => !empty($details),
            'tags' => $tags,
            'hastags' => !empty($tags),
            'deadline' => $deadline ? userdate($deadline, get_string('strftimedatefullshort')) : '',
        ];
    }

    /**
     * Returns the custom fields of a course the reader may see and that have a value.
     *
     * Sites add fields of their own to courses (topics, level, hours, a date...). Whatever they
     * add shows on this page without the theme knowing about it beforehand.
     *
     * @param int $courseid Course id.
     * @return array[] name and value (HTML).
     */
    public static function details(int $courseid): array {
        $handler = \core_course\customfield\course_handler::create();
        $details = [];
        foreach ($handler->get_instance_data($courseid, true) as $data) {
            $field = $data->get_field();
            if (!$handler->can_view($field, $courseid)) {
                continue;
            }
            $value = $data->export_value();
            if ($value === null || trim(strip_tags((string) $value, '<img>')) === '') {
                continue;
            }
            $details[] = ['name' => $field->get_formatted_name(), 'value' => (string) $value];
        }
        return $details;
    }

    /**
     * Returns the tags of a course.
     *
     * @param int $courseid Course id.
     * @return array[] name and url.
     */
    public static function tags(int $courseid): array {
        if (!\core_tag_tag::is_enabled('core', 'course')) {
            return [];
        }
        $tags = [];
        foreach (\core_tag_tag::get_item_tags('core', 'course', $courseid) as $tag) {
            $tags[] = ['name' => $tag->get_display_name(), 'url' => $tag->get_view_url()->out(false)];
        }
        return $tags;
    }

    /**
     * Returns when enrolment closes: the latest end of the methods that are open now and have one.
     *
     * @param int $courseid Course id.
     * @return int Timestamp, 0 when there is no limit or a method stays open without one.
     */
    public static function deadline(int $courseid): int {
        $now = time();
        $deadline = 0;
        foreach (enrol_get_instances($courseid, true) as $instance) {
            // Only the methods people use by themselves say until when one can join.
            if (!in_array($instance->enrol, ['self', 'fee', 'paypal'])) {
                continue;
            }
            if (!empty($instance->enrolstartdate) && $instance->enrolstartdate > $now) {
                continue;
            }
            if (empty($instance->enrolenddate)) {
                return 0;
            }
            if ($instance->enrolenddate > $now) {
                $deadline = max($deadline, (int) $instance->enrolenddate);
            }
        }
        return $deadline;
    }

    /**
     * Returns the sections of a course as somebody outside it may see them.
     *
     * Whether the user can enter each activity does not count here, as nobody outside the course
     * can: what counts is what the course shows on its page.
     *
     * @param stdClass $course Course record.
     * @param bool $withactivities Whether to list the activities of each section.
     * @return array[] name, number, count, countlabel, hasactivities, isfirst and activities (name, kind, purpose, icon).
     */
    public static function sections(stdClass $course, bool $withactivities): array {
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->visible || $section->is_delegated()) {
                continue;
            }
            $activities = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                // Text and media areas are not activities, and stealth or hidden ones are not announced.
                if (!$cm->visible || !$cm->is_visible_on_course_page() || !$cm->has_view() || $cm->deletioninprogress) {
                    continue;
                }
                $activities[] = [
                    'name' => $cm->get_formatted_name(),
                    'kind' => $cm->modfullname,
                    'purpose' => activity_stage::purpose($cm->modname),
                    'icon' => $cm->get_icon_url()->out(false),
                ];
            }
            if (!$activities) {
                continue;
            }
            $sections[] = [
                'name' => get_section_name($course, $section),
                'number' => str_pad((string) (count($sections) + 1), 2, '0', STR_PAD_LEFT),
                'count' => count($activities),
                'countlabel' => count($activities) == 1
                    ? get_string('enrolactivitycountone', 'theme_rsmax')
                    : get_string('enrolactivitycount', 'theme_rsmax', count($activities)),
                'hasactivities' => $withactivities,
                'activities' => $withactivities ? $activities : [],
                'isfirst' => !$sections,
            ];
        }
        return $sections;
    }

    /**
     * Returns the ratings of a course, when the rating block is installed and the course has any.
     *
     * @param int $courseid Course id.
     * @return array|false average, count and stars (a list of isfull and ishalf).
     */
    public static function rating(int $courseid) {
        $class = '\block_pluginia_course_rating\local\ratings';
        if (!class_exists($class)) {
            return false;
        }
        $summary = $class::summary($courseid);
        if (empty($summary['count'])) {
            return false;
        }
        $stars = [];
        for ($star = 1; $star <= 5; $star++) {
            $stars[] = [
                'isfull' => $summary['average'] >= $star - 0.25,
                'ishalf' => $summary['average'] < $star - 0.25 && $summary['average'] >= $star - 0.75,
            ];
        }
        return [
            'average' => format_float($summary['average'], 1),
            'count' => $summary['count'],
            'stars' => $stars,
        ];
    }

    /**
     * Renders the blocks a course keeps for this page: those in its "enrolment page" region.
     *
     * The page of the enrolment options belongs to the category, not to the course, so Moodle
     * never shows blocks of the course there. The theme brings them over.
     *
     * @param stdClass $course Course record.
     * @param \moodle_page $page The page being shown.
     * @param \core_renderer $output Renderer.
     * @return string HTML, empty when the course has no blocks for this page.
     */
    public static function blocks(stdClass $course, \moodle_page $page, \core_renderer $output): string {
        global $DB;

        $context = context_course::instance($course->id);
        $records = $DB->get_records(
            'block_instances',
            ['parentcontextid' => $context->id, 'defaultregion' => self::REGION],
            'defaultweight, id'
        );
        $html = '';
        foreach ($records as $record) {
            // What Moodle adds to a block when it loads the blocks of a page itself.
            $record->region = $record->defaultregion;
            $record->weight = $record->defaultweight;
            $record->visible = 1;
            $record->blockpositionid = null;
            $block = block_instance($record->blockname, $record, $page);
            if (!$block || !has_capability('moodle/block:view', $block->context)) {
                continue;
            }
            $content = $block->get_content_for_output($output);
            if ($content) {
                $html .= $output->block($content, self::REGION);
            }
        }
        return $html;
    }
}
