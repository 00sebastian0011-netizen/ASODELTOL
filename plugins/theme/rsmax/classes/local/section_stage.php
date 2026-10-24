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

use core_course\section_info;

/**
 * Data of the header of the page of a section.
 *
 * A section shown on a page of its own opens like an activity does: a dark panel with its name,
 * which section of the course it is, how much of it the learner has done and the sections on
 * either side.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_stage {
    /**
     * Returns the data of the header of a section page.
     *
     * @param section_info $current The section being shown.
     * @param array $bysection Progress of the learner by section id (completed and total), if tracked.
     * @return array position, total, positionlabel, hasprogress, completed, activities, percentage,
     *     iscomplete, previous and next (name and url, or false).
     */
    public static function export(section_info $current, array $bysection = []): array {
        $modinfo = get_fast_modinfo($current->course);
        $course = $modinfo->get_course();

        // The sections a learner moves through: the visible ones that are not inside an activity.
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->uservisible && !$section->is_delegated()) {
                $sections[] = $section;
            }
        }
        $index = -1;
        foreach ($sections as $key => $section) {
            if ($section->id == $current->id) {
                $index = $key;
            }
        }
        $link = function (?section_info $section) use ($course) {
            if (!$section) {
                return false;
            }
            return [
                'name' => get_section_name($course, $section),
                'url' => course_get_url($course, $section, ['navigation' => true])->out(false),
            ];
        };

        $progress = $bysection[$current->id] ?? null;
        return [
            'position' => $index + 1,
            'total' => count($sections),
            // The general section is number 0: it gets no figure.
            'positionlabel' => $current->sectionnum ? str_pad((string) $current->sectionnum, 2, '0', STR_PAD_LEFT) : '',
            'hasprogress' => !empty($progress['total']),
            'completed' => $progress['completed'] ?? 0,
            'activities' => $progress['total'] ?? 0,
            'percentage' => !empty($progress['total']) ? (int) floor($progress['completed'] * 100 / $progress['total']) : 0,
            'iscomplete' => !empty($progress['total']) && $progress['completed'] == $progress['total'],
            'previous' => $index > 0 ? $link($sections[$index - 1]) : false,
            'next' => $index >= 0 ? $link($sections[$index + 1] ?? null) : false,
        ];
    }
}
