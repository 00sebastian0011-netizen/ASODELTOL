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

use cm_info;
use completion_info;

/**
 * The header of an activity page: what kind of activity it is and where it stands in its section.
 *
 * The section is drawn as a route with one stop per activity, so the learner sees what is behind,
 * where they are and what is left without going back to the course page.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_stage {
    /** Most stops drawn on the route; longer sections show the stops around the current one. */
    public const MAXSTOPS = 24;

    /** Purposes Moodle gives to activities, each with a colour of its own in the theme. */
    private const PURPOSES = ['assessment', 'collaboration', 'communication', 'content', 'interactivecontent', 'administration'];

    /**
     * Returns the purpose of a kind of activity, as a word safe to use in a CSS class.
     *
     * @param string $modname Name of the module, such as forum.
     * @return string One of the purposes Moodle defines, or other.
     */
    public static function purpose(string $modname): string {
        $purpose = plugin_supports('mod', $modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER);
        return in_array($purpose, self::PURPOSES, true) ? $purpose : 'other';
    }

    /**
     * Returns the data of the header of an activity page.
     *
     * @param cm_info $current The activity being shown.
     * @param int $userid User the completion states belong to.
     * @return array kind, purpose, section, sectionurl, position, total, positionlabel, hasstops and stops (name, url,
     *     isdone, iscurrent).
     */
    public static function export(cm_info $current, int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $modinfo = $current->get_modinfo();
        $course = $modinfo->get_course();
        $completion = new completion_info($course);
        $tracked = $completion->is_enabled() && $userid && !isguestuser($userid) && $completion->is_tracked_user($userid);

        $stops = [];
        $position = 0;
        foreach ($modinfo->sections[$current->sectionnum] ?? [] as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            // Text and media areas have no page of their own, so they are not stops.
            if (!$cm->uservisible || $cm->deletioninprogress || !$cm->url) {
                continue;
            }
            $isdone = false;
            if ($tracked && $completion->is_enabled($cm)) {
                $state = $completion->get_data($cm, true, $userid)->completionstate;
                $isdone = $state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS;
            }
            $stops[] = [
                'name' => $cm->get_formatted_name(),
                'url' => $cm->url->out(false),
                'isdone' => $isdone,
                'iscurrent' => $cm->id == $current->id,
            ];
            if ($cm->id == $current->id) {
                $position = count($stops);
            }
        }
        $total = count($stops);
        if ($total > self::MAXSTOPS) {
            $start = max(0, min($position - (int) (self::MAXSTOPS / 2), $total - self::MAXSTOPS));
            $stops = array_slice($stops, $start, self::MAXSTOPS);
        }

        $section = $current->get_section_info();
        return [
            'kind' => $current->modfullname,
            'purpose' => self::purpose($current->modname),
            'section' => get_section_name($course, $section),
            'sectionurl' => course_get_url($course, $section->section)->out(false),
            'position' => $position,
            'total' => $total,
            'positionlabel' => $position ? str_pad((string) $position, 2, '0', STR_PAD_LEFT) : '',
            'hasstops' => $position && $total > 1,
            'stops' => $stops,
        ];
    }
}
