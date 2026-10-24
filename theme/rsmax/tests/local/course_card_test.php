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

use core_course_list_element;

/**
 * Tests of the card of a course in the catalogue.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\course_card::class)]
final class course_card_test extends \advanced_testcase {
    /**
     * The summary is plain text, shortened, and a visitor gets no progress.
     */
    public function test_export_for_visitor(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category(['name' => 'Business']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->setGuestUser();

        $summary = '<p>Plan &amp; <strong>measure</strong></p>' . str_repeat('<p>More words here.</p>', 20);
        $data = course_card::export(new core_course_list_element($course), 'Name', $summary);

        $this->assertSame($course->id, $data['id']);
        $this->assertSame('Business', $data['category']);
        $this->assertStringStartsWith('Plan & measure', $data['summary']);
        $this->assertStringNotContainsString('<', $data['summary']);
        $this->assertLessThanOrEqual(course_card::SUMMARYLENGTH + 3, \core_text::strlen($data['summary']));
        $this->assertFalse($data['hasprogress']);
        $this->assertFalse($data['hidden']);
        $this->assertSame('', $data['price']);
        $this->assertNotEmpty($data['image']);
    }

    /**
     * A logged-in user sees progress when the course tracks completion, and a hidden course is marked.
     */
    public function test_export_for_user(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'visible' => 0]);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id], ['completion' => COMPLETION_TRACKING_MANUAL]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $data = course_card::export(new core_course_list_element(get_course($course->id)), 'Name', '');

        $this->assertTrue($data['hasprogress']);
        $this->assertSame(0, $data['progress']);
        $this->assertTrue($data['hidden']);
        $this->assertSame('', $data['summary']);
    }

    /**
     * The price comes from an enabled enrolment method that has a cost.
     */
    public function test_price(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertSame('', course_card::price($course->id));

        set_config('enrol_plugins_enabled', 'manual,guest,self,fee');
        $plugin = enrol_get_plugin('fee');
        $plugin->add_instance($course, ['cost' => 49, 'currency' => 'EUR', 'status' => ENROL_INSTANCE_ENABLED]);
        $this->assertNotEmpty($DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'fee']));

        $this->assertStringContainsString('49', course_card::price($course->id));
    }
}
