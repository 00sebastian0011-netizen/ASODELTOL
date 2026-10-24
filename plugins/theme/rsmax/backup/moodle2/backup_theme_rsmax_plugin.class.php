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
 * Puts the banners of a course and of its sections in the backup of the course.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup of what the theme stores for a course.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_theme_rsmax_plugin extends backup_theme_plugin {
    /**
     * Describes the data of the theme at course level: where the banner is shown, the banner
     * itself and the banners of the sections.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        // No condition on the theme of the course: the banners belong to the course whatever theme shows it.
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name(), null, ['bannerscope']);
        $sections = new backup_nested_element('sectionbanners');
        $section = new backup_nested_element('sectionbanner', ['id'], ['section']);
        $plugin->add_child($wrapper);
        $wrapper->add_child($sections);
        $sections->add_child($section);

        // Always one row, with or without options saved for the course.
        $wrapper->set_source_sql(
            'SELECT c.id, COALESCE(t.bannerscope, 0) AS bannerscope
               FROM {course} c
          LEFT JOIN {theme_rsmax_course} t ON t.courseid = c.id
              WHERE c.id = ?',
            [backup::VAR_COURSEID]
        );
        $section->set_source_sql(
            'SELECT id, section FROM {course_sections} WHERE course = ? ORDER BY section',
            [backup::VAR_COURSEID]
        );

        $wrapper->annotate_files('theme_rsmax', 'coursebanner', null);
        $section->annotate_files('theme_rsmax', 'sectionbanner', 'id');
        return $plugin;
    }
}
