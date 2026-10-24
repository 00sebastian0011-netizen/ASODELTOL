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

/**
 * Tests of the header of an activity page.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\activity_stage::class)]
final class activity_stage_test extends \advanced_testcase {
    /**
     * The route lists the activities of the section that have a page, in order, with their state.
     */
    public function test_export(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $options = ['completion' => COMPLETION_TRACKING_MANUAL];
        $page = $generator->create_module('page', ['course' => $course->id, 'section' => 1], $options);
        $generator->create_module('label', ['course' => $course->id, 'section' => 1]);
        $forum = $generator->create_module('forum', ['course' => $course->id, 'section' => 1], $options);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'visible' => 0]);
        $generator->create_module('quiz', ['course' => $course->id, 'section' => 2]);
        $user = $generator->create_and_enrol($course, 'student');
        $this->setUser($user);

        $modinfo = get_fast_modinfo($course);
        (new \completion_info($course))->update_state($modinfo->get_cm($page->cmid), COMPLETION_COMPLETE, $user->id);
        $modinfo = get_fast_modinfo($course);

        $stage = activity_stage::export($modinfo->get_cm($forum->cmid), $user->id);

        $this->assertSame('collaboration', $stage['purpose']);
        $this->assertSame(2, $stage['position']);
        $this->assertSame(2, $stage['total']);
        $this->assertSame('02', $stage['positionlabel']);
        $this->assertTrue($stage['hasstops']);
        $this->assertCount(2, $stage['stops']);
        $this->assertTrue($stage['stops'][0]['isdone']);
        $this->assertFalse($stage['stops'][0]['iscurrent']);
        $this->assertFalse($stage['stops'][1]['isdone']);
        $this->assertTrue($stage['stops'][1]['iscurrent']);
    }

    /**
     * A section with a single activity has no route to draw, and a visitor has nothing done.
     */
    public function test_export_alone_and_not_tracked(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'section' => 1]);
        $this->setGuestUser();

        $stage = activity_stage::export(get_fast_modinfo($course)->get_cm($quiz->cmid), 0);

        $this->assertSame('assessment', $stage['purpose']);
        $this->assertSame(1, $stage['position']);
        $this->assertFalse($stage['hasstops']);
        $this->assertFalse($stage['stops'][0]['isdone']);
    }

    /**
     * Long sections show a window of stops around the current activity.
     */
    public function test_export_long_section(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $cmids = [];
        for ($i = 0; $i < activity_stage::MAXSTOPS + 6; $i++) {
            $cmids[] = $generator->create_module('page', ['course' => $course->id, 'section' => 1])->cmid;
        }
        $this->setAdminUser();

        $stage = activity_stage::export(get_fast_modinfo($course)->get_cm(end($cmids)), 2);

        $this->assertCount(activity_stage::MAXSTOPS, $stage['stops']);
        $this->assertSame(activity_stage::MAXSTOPS + 6, $stage['total']);
        $this->assertTrue(end($stage['stops'])['iscurrent']);
    }
}
