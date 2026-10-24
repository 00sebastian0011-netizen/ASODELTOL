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
use core\url;

/**
 * Banners of a course and of its sections.
 *
 * The course image is usually square and made for cards. A banner is a wide, short picture
 * that goes behind the header of the course pages. A course has one, each section may have its
 * own, and the course decides whether its banner is also used on the pages inside it.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class banner {
    /** File area of the banner of a course (item id 0). */
    public const AREA_COURSE = 'coursebanner';

    /** File area of the banners of the sections (item id: the id of the section). */
    public const AREA_SECTION = 'sectionbanner';

    /** The course banner is only shown on the main page of the course. */
    public const SCOPE_HOME = 0;

    /** The course banner is shown on every page of the course that has no banner of its own. */
    public const SCOPE_ALL = 1;

    /**
     * Options of the file pickers the banners are uploaded with.
     *
     * @return array
     */
    public static function file_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']];
    }

    /**
     * Returns what the theme knows about the banners of a course.
     *
     * @param int $courseid Course id.
     * @return array scope, course (URL or an empty string) and sections (URLs by section id).
     */
    public static function get(int $courseid): array {
        global $DB;

        $cache = \cache::make('theme_rsmax', 'banners');
        $data = $cache->get($courseid);
        if ($data !== false) {
            return $data;
        }

        $context = context_course::instance($courseid);
        $data = [
            'scope' => (int) $DB->get_field('theme_rsmax_course', 'bannerscope', ['courseid' => $courseid]),
            'course' => '',
            'sections' => [],
        ];
        $fs = get_file_storage();
        foreach ([self::AREA_COURSE, self::AREA_SECTION] as $area) {
            foreach ($fs->get_area_files($context->id, 'theme_rsmax', $area, false, 'itemid, id', false) as $file) {
                // The time of the file goes in the address, so a new picture is never served from an old cache.
                $address = url::make_pluginfile_url(
                    $context->id,
                    'theme_rsmax',
                    $area,
                    $file->get_itemid(),
                    '/' . $file->get_timemodified() . '/',
                    $file->get_filename(),
                )->out(false);
                if ($area == self::AREA_COURSE) {
                    $data['course'] = $address;
                } else {
                    $data['sections'][$file->get_itemid()] = $address;
                }
            }
        }
        $cache->set($courseid, $data);
        return $data;
    }

    /**
     * Returns the banner to show on a page of a course.
     *
     * @param int $courseid Course id.
     * @param int $sectionid Section the page belongs to, 0 when it is not inside one.
     * @param bool $home Whether it is the main page of the course.
     * @return string URL of the picture, empty when the page has no banner.
     */
    public static function for_page(int $courseid, int $sectionid = 0, bool $home = false): string {
        $data = self::get($courseid);
        if ($sectionid && !empty($data['sections'][$sectionid])) {
            return $data['sections'][$sectionid];
        }
        if ($home || $data['scope'] == self::SCOPE_ALL) {
            return $data['course'];
        }
        return '';
    }

    /**
     * Sets where the banner of a course is shown.
     *
     * @param int $courseid Course id.
     * @param int $scope One of the SCOPE_ constants.
     */
    public static function set_scope(int $courseid, int $scope): void {
        global $DB;

        $scope = $scope == self::SCOPE_ALL ? self::SCOPE_ALL : self::SCOPE_HOME;
        $record = $DB->get_record('theme_rsmax_course', ['courseid' => $courseid]);
        if ($record) {
            $record->bannerscope = $scope;
            $record->timemodified = time();
            $DB->update_record('theme_rsmax_course', $record);
        } else {
            $DB->insert_record('theme_rsmax_course', (object) [
                'courseid' => $courseid,
                'bannerscope' => $scope,
                'timemodified' => time(),
            ]);
        }
        self::purge($courseid);
    }

    /**
     * Forgets what is cached about the banners of a course.
     *
     * @param int $courseid Course id.
     */
    public static function purge(int $courseid): void {
        \cache::make('theme_rsmax', 'banners')->delete($courseid);
    }

    /**
     * Removes the options of a deleted course. Its files go with its context.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $DB->delete_records('theme_rsmax_course', ['courseid' => $event->objectid]);
        self::purge($event->objectid);
    }

    /**
     * Removes the banner of a deleted section.
     *
     * @param \core\event\course_section_deleted $event
     */
    public static function section_deleted(\core\event\course_section_deleted $event): void {
        get_file_storage()->delete_area_files($event->contextid, 'theme_rsmax', self::AREA_SECTION, $event->objectid);
        self::purge($event->courseid);
    }
}
