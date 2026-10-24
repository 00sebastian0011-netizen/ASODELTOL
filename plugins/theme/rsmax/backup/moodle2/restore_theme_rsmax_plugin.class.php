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

/**
 * Brings the banners of a course and of its sections back from a backup.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore of what the theme stores for a course.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_theme_rsmax_plugin extends restore_theme_plugin {
    /**
     * Returns the paths of the backup this class reads.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [new restore_path_element('theme_rsmax_course', $this->get_pathfor('/'))];
    }

    /**
     * Restores where the banner of the course is shown, and the banner.
     *
     * @param array|stdClass $data Data of the element.
     */
    public function process_theme_rsmax_course($data) {
        $data = (object) $data;
        \theme_rsmax\local\banner::set_scope($this->task->get_courseid(), (int) ($data->bannerscope ?? 0));
        $this->add_related_files('theme_rsmax', 'coursebanner', null);
    }

    /**
     * Restores the banners of the sections, once the sections exist and their new ids are known.
     */
    public function after_restore_course() {
        $this->add_related_files('theme_rsmax', 'sectionbanner', 'course_section');
        \theme_rsmax\local\banner::purge($this->task->get_courseid());
    }
}
