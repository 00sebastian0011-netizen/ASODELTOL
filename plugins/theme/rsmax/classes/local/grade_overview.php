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
use stdClass;

/**
 * How a whole course is doing, for whoever grades it.
 *
 * The grader report is a large table: one row per learner, one column per activity. Before
 * reading it cell by cell a teacher wants to know three things: how the group is doing, which
 * activities go well or badly, and who is falling behind. This gathers them.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_overview {
    /** Learners below this percentage of the course are listed as needing attention. */
    public const ATTENTION = 50;

    /** Most learners listed as needing attention. */
    public const MAXATTENTION = 5;

    /** Seconds the figures of a course are kept. */
    public const TTL = 300;

    /**
     * Whether the user may see the figures of the whole course.
     *
     * Somebody who only sees the groups they belong to gets no summary of everybody.
     *
     * @param stdClass $course Course record.
     * @return bool
     */
    public static function can_view(stdClass $course): bool {
        $context = context_course::instance($course->id);
        if (!has_capability('moodle/grade:viewall', $context)) {
            return false;
        }
        return groups_get_course_groupmode($course) != SEPARATEGROUPS
            || has_capability('moodle/site:accessallgroups', $context);
    }

    /**
     * Returns the summary of the grades of a course.
     *
     * @param stdClass $course Course record.
     * @return array|null learners, haslearners, average, hasaverage, gradedpercentage, items (name,
     *     kind, average, hasaverage, graded, learners, haspass, passed, passpercentage, state),
     *     attention (name, url, percentage) and hasattention. Null when the course has nothing to grade.
     */
    public static function export(stdClass $course): ?array {
        $cache = \cache::make('theme_rsmax', 'gradeoverview');
        $data = $cache->get($course->id);
        if ($data === false) {
            $data = self::calculate($course);
            $cache->set($course->id, $data);
        }
        return $data ?: null;
    }

    /**
     * Forgets the figures of a course when one of its learners is graded.
     *
     * @param \core\event\user_graded $event
     */
    public static function user_graded(\core\event\user_graded $event): void {
        \cache::make('theme_rsmax', 'gradeoverview')->delete($event->courseid);
    }

    /**
     * Works the summary out.
     *
     * @param stdClass $course Course record.
     * @return array Empty when the course has nothing to grade.
     */
    private static function calculate(stdClass $course): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $context = context_course::instance($course->id);
        // The people the gradebook grades: active in the course, with one of its graded roles.
        $roles = array_filter(explode(',', (string) $CFG->gradebookroles));
        if (!$roles) {
            return [];
        }
        $graded = get_role_users($roles, $context, true, 'ra.id, u.id AS userid', 'u.id', false);
        $graded = array_flip(array_column($graded, 'userid'));
        $active = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
        $userids = array_keys(array_intersect_key($graded, $active));
        if (!$userids) {
            return [];
        }

        $items = \grade_item::fetch_all(['courseid' => $course->id]) ?: [];
        $items = array_filter($items, fn($item) => $item->gradetype == GRADE_TYPE_VALUE && $item->grademax > $item->grademin
            && in_array($item->itemtype, ['mod', 'manual', 'course']));
        if (!$items) {
            return [];
        }
        usort($items, fn($a, $b) => $a->sortorder <=> $b->sortorder);

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        [$itemsql, $itemparams] = $DB->get_in_or_equal(array_column($items, 'id'), SQL_PARAMS_NAMED, 'i');
        $grades = $DB->get_recordset_select(
            'grade_grades',
            "itemid $itemsql AND userid $usersql AND finalgrade IS NOT NULL",
            $itemparams + $userparams,
            '',
            'id, itemid, userid, finalgrade, rawgrademin, rawgrademax'
        );
        $byitem = [];
        $ranges = [];
        foreach ($grades as $grade) {
            $byitem[$grade->itemid][$grade->userid] = (float) $grade->finalgrade;
            $ranges[$grade->itemid][$grade->userid] = [(float) $grade->rawgrademin, (float) $grade->rawgrademax];
        }
        $grades->close();

        $learners = count($userids);
        $modinfo = get_fast_modinfo($course);
        $rows = [];
        $cells = 0;
        $gradedcells = 0;
        $total = null;
        foreach ($items as $item) {
            $values = $byitem[$item->id] ?? [];
            if ($item->itemtype == 'course') {
                // The total of each learner has a range of its own: what is not graded yet may not count.
                $total = [];
                foreach ($values as $userid => $value) {
                    [$min, $max] = $ranges[$item->id][$userid];
                    if ($max <= $min) {
                        [$min, $max] = [$item->grademin, $item->grademax];
                    }
                    $total[$userid] = max(0, min(100, ($value - $min) * 100 / ($max - $min)));
                }
                continue;
            }
            $percentages = array_map(
                fn($value) => max(0, min(100, ($value - $item->grademin) * 100 / ($item->grademax - $item->grademin))),
                $values
            );
            $cells += $learners;
            $gradedcells += count($values);
            $haspass = $item->gradepass > 0;
            $passed = $haspass ? count(array_filter($values, fn($value) => $value >= $item->gradepass)) : 0;
            $average = $values ? (int) round(array_sum($percentages) / count($percentages)) : 0;
            $kind = '';
            if ($item->itemtype == 'mod' && !empty($modinfo->instances[$item->itemmodule][$item->iteminstance])) {
                $kind = $modinfo->instances[$item->itemmodule][$item->iteminstance]->modfullname;
            }
            $rows[] = [
                'name' => $item->get_name(),
                'kind' => $kind,
                'average' => $average,
                'hasaverage' => !empty($values),
                'graded' => count($values),
                'learners' => $learners,
                'haspass' => $haspass && !empty($values),
                'passed' => $passed,
                'passpercentage' => $values ? (int) round($passed * 100 / count($values)) : 0,
                // Against the pass mark when there is one, and without judgement when there is not.
                'state' => !$values ? 'pending'
                    : ($haspass ? ($average >= ($item->gradepass - $item->grademin) * 100 / ($item->grademax - $item->grademin)
                        ? 'passed' : 'failed') : 'graded'),
            ];
        }
        if (!$rows) {
            return [];
        }

        $attention = [];
        if ($total) {
            asort($total);
            $low = array_filter($total, fn($percentage) => $percentage < self::ATTENTION);
            $users = $low ? \core_user::get_users_by_id(array_keys(array_slice($low, 0, self::MAXATTENTION, true))) : [];
            foreach (array_slice($low, 0, self::MAXATTENTION, true) as $userid => $percentage) {
                if (!isset($users[$userid])) {
                    continue;
                }
                $attention[] = [
                    'name' => s(fullname($users[$userid])),
                    'url' => (new \core\url(
                        '/grade/report/user/index.php',
                        ['id' => $course->id, 'userid' => $userid]
                    ))->out(false),
                    'percentage' => (int) round($percentage),
                ];
            }
        }

        return [
            'learners' => $learners,
            'average' => $total ? (int) round(array_sum($total) / count($total)) : 0,
            'hasaverage' => !empty($total),
            'gradedpercentage' => $cells ? (int) round($gradedcells * 100 / $cells) : 0,
            'items' => $rows,
            'attention' => $attention,
            'hasattention' => !empty($attention),
            'attentionlimit' => self::ATTENTION,
        ];
    }
}
