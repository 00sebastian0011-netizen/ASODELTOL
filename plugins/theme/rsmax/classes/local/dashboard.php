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

use core_course\external\course_summary_exporter;
use stdClass;

/**
 * Builds the summary shown at the top of the dashboard: a greeting, a few figures, the course to
 * continue with and the other courses in progress.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {
    /** Courses whose progress is worked out and shown, the most recently visited first. */
    public const MAXCOURSES = 5;

    /** Days ahead looked at for things to do. */
    public const DUEDAYS = 7;

    /**
     * Returns the courses of a user, the most recently visited first and never visited ones last.
     *
     * @param int $userid User.
     * @return stdClass[] Course records with an extra "lastaccess".
     */
    public static function courses(int $userid): array {
        global $DB;

        $courses = enrol_get_users_courses($userid, true, '*');
        $lastaccess = $DB->get_records_menu('user_lastaccess', ['userid' => $userid], '', 'courseid, timeaccess');
        foreach ($courses as $course) {
            $course->lastaccess = (int) ($lastaccess[$course->id] ?? 0);
        }
        uasort($courses, fn($a, $b) => [$b->lastaccess, $a->sortorder] <=> [$a->lastaccess, $b->sortorder]);
        return array_values($courses);
    }

    /**
     * Returns how many of the given courses the user has completed.
     *
     * @param int $userid User.
     * @param int[] $courseids Courses.
     * @return int
     */
    public static function completed_count(int $userid, array $courseids): int {
        global $DB;

        if (!$courseids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        return $DB->count_records_select(
            'course_completions',
            "userid = :userid AND timecompleted IS NOT NULL AND course $insql",
            $params
        );
    }

    /**
     * Returns how many things with a date the user has to do in the next days.
     *
     * @param int $now Current time.
     * @return int
     */
    public static function due_count(int $now): int {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        try {
            $events = \core_calendar\local\api::get_action_events_by_timesort(
                $now,
                $now + self::DUEDAYS * DAYSECS,
                null,
                50
            );
        } catch (\Throwable $e) {
            // The figure is a convenience: the dashboard must show even if the calendar cannot answer.
            debugging('Could not read the upcoming events: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
        return count($events);
    }

    /**
     * Returns the card of a course.
     *
     * @param stdClass $course Course record.
     * @param int $userid User.
     * @return array
     */
    protected static function course_card(stdClass $course, int $userid): array {
        global $OUTPUT;

        $context = \core\context\course::instance($course->id);
        $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
        return [
            'name' => format_string($course->fullname, true, ['context' => $context]),
            'url' => (new \core\url('/course/view.php', ['id' => $course->id]))->out(false),
            'image' => course_summary_exporter::get_course_image($course) ?: $OUTPUT->get_generated_image_for_id($course->id),
            'hasprogress' => $progress !== null,
            'progress' => $progress === null ? 0 : (int) floor($progress),
            'isstarted' => $course->lastaccess > 0,
        ];
    }

    /**
     * Returns the data of the dashboard summary for the current user.
     *
     * @param stdClass $user User.
     * @param int|null $now Current time, for tests.
     * @param string $allcoursesurl Where "all my courses" leads; the My courses page by default.
     * @return array
     */
    public static function summary(stdClass $user, ?int $now = null, string $allcoursesurl = ''): array {
        $now ??= time();
        $courses = self::courses($user->id);
        $completed = self::completed_count($user->id, array_column($courses, 'id'));

        $cards = [];
        foreach (array_slice($courses, 0, self::MAXCOURSES) as $course) {
            $card = self::course_card($course, $user->id);
            // A finished course is not something to continue with.
            if (!$card['hasprogress'] || $card['progress'] < 100) {
                $cards[] = $card;
            }
        }
        $continue = array_shift($cards);

        $figures = [
            [
                'icon' => 'fa-book-open',
                'value' => count($courses) - $completed,
                'label' => get_string('dashboard:inprogress', 'theme_rsmax'),
            ],
            ['icon' => 'fa-circle-check', 'value' => $completed, 'label' => get_string('dashboard:completed', 'theme_rsmax')],
            ['icon' => 'fa-calendar-day', 'value' => self::due_count($now),
                'label' => get_string('dashboard:due', 'theme_rsmax', self::DUEDAYS)],
        ];

        return [
            'greeting' => get_string('dashboard:greeting', 'theme_rsmax', format_string($user->firstname)),
            'date' => userdate($now, get_string('strftimedaydate', 'langconfig')),
            'figures' => $figures,
            'continue' => $continue ?: false,
            'courses' => $cards,
            'hascourses' => !empty($cards),
            'nocourses' => empty($courses),
            'allcoursesurl' => $allcoursesurl ?: (new \core\url('/my/courses.php'))->out(false),
            'catalogueurl' => (new \core\url('/course/index.php'))->out(false),
        ];
    }
}
