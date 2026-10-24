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
 * Tests for the course progress calculation.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\course_progress::class)]
final class course_progress_test extends \advanced_testcase {
    /**
     * Creates a course with two tracked pages in section 1, one tracked and one untracked page in section 2.
     *
     * @param bool $completion Whether completion is enabled in the course.
     * @return array The course, the student and the course modules by name.
     */
    private function create_course(bool $completion = true): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2, 'enablecompletion' => (int) $completion]);
        $student = $generator->create_and_enrol($course, 'student');
        $tracked = $completion ? ['completion' => COMPLETION_TRACKING_MANUAL] : [];
        $cms = [];
        foreach (['a' => [1, $tracked], 'b' => [1, $tracked], 'c' => [2, $tracked], 'untracked' => [2, []]] as $name => $spec) {
            $page = $generator->create_module('page', ['course' => $course->id, 'section' => $spec[0]] + $spec[1]);
            $cms[$name] = get_coursemodule_from_instance('page', $page->id);
        }
        return [$course, $student, $cms];
    }

    /**
     * Progress counts only tracked activities and is split by section.
     */
    public function test_progress_by_section(): void {
        $this->resetAfterTest();
        [$course, $student, $cms] = $this->create_course();

        $completion = new completion_info($course);
        $completion->update_state($cms['a'], COMPLETION_COMPLETE, $student->id);
        $completion->update_state($cms['b'], COMPLETION_COMPLETE, $student->id);

        $progress = course_progress::get_for_user($course, $student->id);

        $this->assertSame(66, $progress['percentage']);
        $this->assertSame(2, $progress['completed']);
        $this->assertSame(3, $progress['total']);
        $this->assertSame(1, $progress['remaining']);
        $this->assertFalse($progress['iscomplete']);
        $this->assertCount(2, $progress['sections']);
        $this->assertTrue($progress['sections'][0]['iscomplete']);
        $this->assertSame(100, $progress['sections'][0]['percentage']);
        $this->assertSame(1, $progress['sections'][1]['total']);
        $this->assertFalse($progress['sections'][1]['isstarted']);
    }

    /**
     * A course is complete when every tracked activity is.
     */
    public function test_complete_course(): void {
        $this->resetAfterTest();
        [$course, $student, $cms] = $this->create_course();

        $completion = new completion_info($course);
        foreach (['a', 'b', 'c'] as $name) {
            $completion->update_state($cms[$name], COMPLETION_COMPLETE, $student->id);
        }

        $progress = course_progress::get_for_user($course, $student->id);

        $this->assertSame(100, $progress['percentage']);
        $this->assertTrue($progress['iscomplete']);
    }

    /**
     * Activities hidden from the learner do not count.
     */
    public function test_hidden_activities_are_ignored(): void {
        $this->resetAfterTest();
        [$course, $student, $cms] = $this->create_course();
        set_coursemodule_visible($cms['c']->id, 0);

        $progress = course_progress::get_for_user($course, $student->id);

        $this->assertSame(2, $progress['total']);
        $this->assertCount(1, $progress['sections']);
    }

    /**
     * There is nothing to show without completion, for untracked users or with no tracked activities.
     */
    public function test_nothing_to_show(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        [$course, $student] = $this->create_course(false);
        $this->assertNull(course_progress::get_for_user($course, $student->id));

        [$course] = $this->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->assertNull(course_progress::get_for_user($course, $teacher->id));

        $empty = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($empty, 'student');
        $this->assertNull(course_progress::get_for_user($empty, $student->id));
    }
}
