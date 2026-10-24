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

use core_course\cm_info;
use core_course\modinfo;
use core_course\route\controller\course_navigation;

/**
 * What the bar that moves between the pages of a course says besides "previous" and "next".
 *
 * Moodle only knows where each button goes once it is pressed. Here the same rules are followed
 * beforehand, so the buttons can name what they lead to and the bar can say how far along the
 * section the learner is.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linear_nav {
    /**
     * Returns the names of the pages around an activity and its place in its section.
     *
     * @param cm_info $current The activity being shown.
     * @return array previousname, previousissection, nextname, nextissection, position, total and
     *     percentage. A name is empty when that button leaves the course content.
     */
    public static function export(cm_info $current): array {
        $navigation = new course_navigation();
        $modinfo = $current->get_modinfo();
        $course = $modinfo->get_course();
        $section = $navigation->get_section($current);
        $all = $navigation->get_all_section_cms($modinfo, $section);

        $names = [];
        $position = 0;
        $index = -1;
        foreach ($all as $cm) {
            if ($cm->id == $current->id) {
                $index = count($names);
                $position = $index + 1;
                $names[] = $cm->get_formatted_name();
            } else if (self::is_navigable($cm)) {
                $names[] = $cm->get_formatted_name();
            }
        }

        $data = [
            'previousname' => '',
            'previousissection' => false,
            'nextname' => '',
            'nextissection' => false,
            'position' => $position,
            'total' => count($names),
            'percentage' => $names ? (int) round($position * 100 / count($names)) : 0,
        ];
        if ($index > 0) {
            $data['previousname'] = $names[$index - 1];
        } else if ($navigation->get_adjacent_section($modinfo, $section, 'previous')) {
            // From the first activity Moodle goes back to the page of its own section.
            $data['previousname'] = get_section_name($course, $section);
            $data['previousissection'] = true;
        }
        if ($index >= 0 && $index + 1 < count($names)) {
            $data['nextname'] = $names[$index + 1];
        } else if ($next = $navigation->get_adjacent_section($modinfo, $section, 'next')) {
            // From the last one, to the page of the next section.
            $data['nextname'] = get_section_name($course, $next);
            $data['nextissection'] = true;
        }
        return $data;
    }

    /**
     * Whether the bar stops at an activity. The same rule Moodle applies when a button is pressed.
     *
     * @param cm_info $cm The activity.
     * @return bool
     */
    private static function is_navigable(cm_info $cm): bool {
        return !empty($cm->get_navigation_url())
            && $cm->is_visible_on_course_page()
            && (!$cm->is_stealth() || has_capability('moodle/course:viewhiddenactivities', $cm->context))
            && modinfo::is_mod_type_visible_on_course($cm->modname);
    }
}
