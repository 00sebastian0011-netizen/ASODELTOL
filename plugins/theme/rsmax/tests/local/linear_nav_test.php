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
 * Tests of what the bar between pages says, and of the header of a section page.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\linear_nav::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\section_stage::class)]
final class linear_nav_test extends \advanced_testcase {
    /**
     * The buttons name the activity on each side, skipping what has no page or is hidden, and
     * at the ends of a section they name a section.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);
        $first = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'First']);
        $generator->create_module('label', ['course' => $course->id, 'section' => 1]);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Hidden', 'visible' => 0]);
        $second = $generator->create_module('forum', ['course' => $course->id, 'section' => 1, 'name' => 'Second']);
        $last = $generator->create_module('page', ['course' => $course->id, 'section' => 2, 'name' => 'Last']);
        $this->setUser($generator->create_and_enrol($course, 'student'));
        $modinfo = get_fast_modinfo($course);

        $data = linear_nav::export($modinfo->get_cm($first->cmid));
        $this->assertSame(1, $data['position']);
        $this->assertSame(2, $data['total']);
        $this->assertSame(50, $data['percentage']);
        $this->assertSame('Second', $data['nextname']);
        $this->assertFalse($data['nextissection']);
        // Before the first activity of a section comes the page of that section.
        $this->assertTrue($data['previousissection']);
        $this->assertSame(get_section_name($course, 1), $data['previousname']);

        $data = linear_nav::export($modinfo->get_cm($second->cmid));
        $this->assertSame(2, $data['position']);
        $this->assertSame('First', $data['previousname']);
        $this->assertFalse($data['previousissection']);
        // After the last one, the next section.
        $this->assertTrue($data['nextissection']);
        $this->assertSame(get_section_name($course, 2), $data['nextname']);

        // The last activity of the course leads nowhere inside it.
        $data = linear_nav::export($modinfo->get_cm($last->cmid));
        $this->assertSame('', $data['nextname']);
        $this->assertSame(100, $data['percentage']);
    }

    /**
     * The header of a section page says which section it is, what is done and what is around.
     */
    public function test_section_stage(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 3]);
        $this->setUser($generator->create_and_enrol($course, 'student'));
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(2);

        $stage = section_stage::export($section, [$section->id => ['completed' => 1, 'total' => 4]]);
        $this->assertSame(3, $stage['position']);
        $this->assertSame(4, $stage['total']);
        $this->assertSame('02', $stage['positionlabel']);
        $this->assertTrue($stage['hasprogress']);
        $this->assertSame(25, $stage['percentage']);
        $this->assertFalse($stage['iscomplete']);
        $this->assertSame(get_section_name($course, 1), $stage['previous']['name']);
        $this->assertSame(get_section_name($course, 3), $stage['next']['name']);

        // The general section has no figure, nothing before it and, untracked, no progress.
        $stage = section_stage::export($modinfo->get_section_info(0));
        $this->assertSame('', $stage['positionlabel']);
        $this->assertFalse($stage['previous']);
        $this->assertFalse($stage['hasprogress']);

        $this->assertFalse(section_stage::export($modinfo->get_section_info(3))['next']);
    }
}
