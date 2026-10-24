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

use completion_info;
use stdClass;

/**
 * Works out how far a learner is through a course, overall and section by section.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_progress {
    /**
     * Returns the progress of a user in a course.
     *
     * Only activities with completion tracking that the user can see are counted, the same rule
     * core uses for the progress shown on course cards.
     *
     * @param stdClass $course The course record.
     * @param int $userid The learner.
     * @return array|null Null when there is nothing to show (completion off, user not tracked or
     *     no tracked activities); otherwise percentage, completed, total, sections, bysection
     *     (completed and total by section id, subsections included) and next (the first activity
     *     still to do, or false).
     */
    public static function get_for_user(stdClass $course, int $userid): ?array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $completion = new completion_info($course);
        if (!$completion->is_enabled() || !$completion->is_tracked_user($userid)) {
            return null;
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $completed = 0;
        $total = 0;
        $sections = [];
        $bysection = [];
        $next = null;
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible || empty($modinfo->sections[$section->section])) {
                continue;
            }
            $sectioncompleted = 0;
            $sectiontotal = 0;
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm->uservisible || $cm->deletioninprogress || !$completion->is_enabled($cm)) {
                    continue;
                }
                $sectiontotal++;
                $state = $completion->get_data($cm, true, $userid)->completionstate;
                if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
                    $sectioncompleted++;
                } else if ($next === null && $cm->url) {
                    // The first activity still to do, in course order, is where the learner goes next.
                    $next = ['id' => (int) $cm->id, 'name' => $cm->get_formatted_name(), 'url' => $cm->url->out(false)];
                }
            }
            $completed += $sectioncompleted;
            $total += $sectiontotal;
            if ($sectiontotal) {
                $bysection[$section->id] = ['completed' => $sectioncompleted, 'total' => $sectiontotal];
            }
            // Subsections count towards the course total but are not listed on their own.
            if ($sectiontotal && !$section->is_delegated()) {
                $sections[] = [
                    'id' => (int) $section->id,
                    'name' => get_section_name($course, $section),
                    'url' => course_get_url($course, $section->section)->out(false),
                    'completed' => $sectioncompleted,
                    'total' => $sectiontotal,
                    'percentage' => (int) floor($sectioncompleted * 100 / $sectiontotal),
                    'iscomplete' => $sectioncompleted == $sectiontotal,
                    'isstarted' => $sectioncompleted > 0,
                ];
            }
        }
        if (!$total) {
            return null;
        }

        return [
            'percentage' => (int) floor($completed * 100 / $total),
            'completed' => $completed,
            'total' => $total,
            'remaining' => $total - $completed,
            'iscomplete' => $completed == $total,
            'sections' => $sections,
            'bysection' => $bysection,
            'next' => $next ?? false,
        ];
    }
}
