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

/**
 * Tests for the dashboard summary, the next activity of a course and the cleaning helpers.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\dashboard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\course_progress::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\clean::class)]
final class dashboard_test extends \advanced_testcase {
    /**
     * The summary counts courses, puts the last visited one first and leaves finished ones out of the cards.
     */
    public function test_summary(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user(['firstname' => 'Ana']);
        $old = $generator->create_course(['fullname' => 'Visited long ago', 'enablecompletion' => 1]);
        $recent = $generator->create_course(['fullname' => 'Visited yesterday', 'enablecompletion' => 1]);
        $never = $generator->create_course(['fullname' => 'Never visited']);
        $done = $generator->create_course(['fullname' => 'Finished', 'enablecompletion' => 1]);
        foreach ([$old, $recent, $never, $done] as $course) {
            $generator->enrol_user($user->id, $course->id, 'student');
        }
        $now = time();
        foreach ([[$old, 30], [$recent, 1], [$done, 2]] as [$course, $days]) {
            $DB->insert_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $course->id,
                'timeaccess' => $now - $days * DAYSECS]);
        }
        $page = $generator->create_module('page', ['course' => $done->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $cm = get_coursemodule_from_instance('page', $page->id);
        (new completion_info($done))->update_state($cm, COMPLETION_COMPLETE, $user->id);
        $DB->insert_record('course_completions', ['userid' => $user->id, 'course' => $done->id, 'timecompleted' => $now]);
        $this->setUser($user);

        $summary = dashboard::summary($user, $now);

        $this->assertStringContainsString('Ana', $summary['greeting']);
        $this->assertSame([3, 1, 0], array_column($summary['figures'], 'value'));
        $this->assertSame('Visited yesterday', $summary['continue']['name']);
        $this->assertTrue($summary['continue']['isstarted']);
        $this->assertSame(['Visited long ago', 'Never visited'], array_column($summary['courses'], 'name'));
        $this->assertFalse($summary['nocourses']);
    }

    /**
     * Someone without courses gets the invitation to browse them.
     */
    public function test_summary_without_courses(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $summary = dashboard::summary($user);

        $this->assertTrue($summary['nocourses']);
        $this->assertFalse($summary['continue']);
        $this->assertSame([], $summary['courses']);
    }

    /**
     * The next activity is the first one still to do, in course order.
     */
    public function test_next_activity(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2, 'enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $cms = [];
        foreach (['First' => 1, 'Second' => 1, 'Third' => 2] as $name => $section) {
            $page = $generator->create_module('page', [
                'course' => $course->id, 'section' => $section, 'name' => $name, 'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
            $cms[$name] = get_coursemodule_from_instance('page', $page->id);
        }
        $completion = new completion_info($course);

        $this->assertSame('First', course_progress::get_for_user($course, $student->id)['next']['name']);

        $completion->update_state($cms['First'], COMPLETION_COMPLETE, $student->id);
        $completion->update_state($cms['Third'], COMPLETION_COMPLETE, $student->id);
        $next = course_progress::get_for_user($course, $student->id)['next'];
        $this->assertSame('Second', $next['name']);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $cms['Second']->id, $next['url']);

        $completion->update_state($cms['Second'], COMPLETION_COMPLETE, $student->id);
        $this->assertFalse(course_progress::get_for_user($course, $student->id)['next']);
    }

    /**
     * The cleaning helpers work on pages where no block has been loaded.
     */
    public function test_clean_needs_no_block_library(): void {
        global $CFG;

        $this->assertSame($CFG->wwwroot . '/my/', clean::link('/my/'));
        $this->assertSame('', clean::link('javascript:alert(1)'));
        $this->assertSame('fa-users', clean::icon('users'));
        $this->assertSame('#fff', clean::colour('#FFF'));

        // The page layout runs on every page, so it must not depend on the block classes.
        foreach (['/theme/rsmax/layout/drawers.php', '/theme/rsmax/lib.php', '/theme/rsmax/config.php'] as $file) {
            $this->assertStringNotContainsString('landing_block', file_get_contents($CFG->dirroot . $file), $file);
        }
    }
}
