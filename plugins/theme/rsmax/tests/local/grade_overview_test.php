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
 * Tests of the summary of the grades of a course.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\grade_overview::class)]
final class grade_overview_test extends \advanced_testcase {
    /**
     * The summary counts learners only, averages what is graded and names who is falling behind.
     */
    public function test_export(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'name' => 'Quiz', 'grade' => 10]);
        $assign = $generator->create_module('assign', ['course' => $course->id, 'name' => 'Essay', 'grade' => 100]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $good = $generator->create_and_enrol($course, 'student', ['firstname' => 'Good', 'lastname' => 'Learner']);
        $weak = $generator->create_and_enrol($course, 'student', ['firstname' => 'Weak', 'lastname' => 'Learner']);
        $generator->create_and_enrol($course, 'student');

        $quizitem = \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id]);
        $quizitem->gradepass = 6;
        $quizitem->update();
        $quizitem->update_final_grade($good->id, 9);
        $quizitem->update_final_grade($weak->id, 3);
        // A grade given to the teacher by mistake is nobody's grade in the gradebook.
        $quizitem->update_final_grade($teacher->id, 10);
        grade_regrade_final_grades($course->id);

        $this->setUser($teacher);
        $this->assertTrue(grade_overview::can_view($course));
        $data = grade_overview::export($course);

        $this->assertSame(3, $data['learners']);
        $items = array_column($data['items'], null, 'name');
        $this->assertSame(2, $items['Quiz']['graded']);
        $this->assertSame(60, $items['Quiz']['average']);
        $this->assertTrue($items['Quiz']['haspass']);
        $this->assertSame(50, $items['Quiz']['passpercentage']);
        $this->assertSame('passed', $items['Quiz']['state']);
        $this->assertSame('pending', $items['Essay']['state']);
        $this->assertFalse($items['Essay']['hasaverage']);
        // Two of six possible grades exist.
        $this->assertSame(33, $data['gradedpercentage']);
        $this->assertTrue($data['hasattention']);
        $this->assertSame(fullname($weak), $data['attention'][0]['name']);
        $this->assertNotContains(fullname($good), array_column($data['attention'], 'name'));

        // A new grade is seen at once, not when the stored figures expire.
        $quizitem->update_final_grade($weak->id, 10);
        grade_regrade_final_grades($course->id);
        $this->assertSame(95, array_column(grade_overview::export($course)['items'], null, 'name')['Quiz']['average']);

        // A learner does not get the figures of the others.
        $this->setUser($good);
        $this->assertFalse(grade_overview::can_view($course));
    }

    /**
     * A course with nothing to grade has no summary.
     */
    public function test_nothing_to_grade(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setAdminUser();
        $this->assertNull(grade_overview::export($course));
    }
}
