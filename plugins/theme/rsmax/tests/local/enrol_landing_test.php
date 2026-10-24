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
 * Tests of the landing page of a course and of the blocks new courses start with.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\enrol_landing::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\course_blocks::class)]
final class enrol_landing_test extends \advanced_testcase {
    /**
     * Somebody outside the course sees its visible sections and activities, and nothing hidden.
     */
    public function test_export(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 3, 'summary' => '<p>About it</p>', 'enablecompletion' => 1]);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Reading']);
        $generator->create_module('label', ['course' => $course->id, 'section' => 1]);
        $generator->create_module('quiz', ['course' => $course->id, 'section' => 1, 'name' => 'Secret', 'visible' => 0]);
        $generator->create_module('forum', ['course' => $course->id, 'section' => 2, 'name' => 'Debate']);
        $generator->create_module('page', ['course' => $course->id, 'section' => 3, 'name' => 'Hidden section']);
        $DB->set_field('course_sections', 'visible', 0, ['course' => $course->id, 'section' => 3]);
        rebuild_course_cache($course->id, true);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        // The reader is not in the course.
        $this->setUser($generator->create_user());

        $data = enrol_landing::export(get_course($course->id), 'activities');

        $this->assertTrue($data['hassummary']);
        $this->assertTrue($data['isfree']);
        $this->assertFalse($data['rating']);
        $this->assertCount(2, $data['sections']);
        $this->assertSame('01', $data['sections'][0]['number']);
        $this->assertSame(1, $data['sections'][0]['count']);
        $this->assertSame('Reading', $data['sections'][0]['activities'][0]['name']);
        $this->assertSame('content', $data['sections'][0]['activities'][0]['purpose']);
        $this->assertSame('Debate', $data['sections'][1]['activities'][0]['name']);
        $this->assertSame(fullname($teacher), $data['teachers'][0]['name']);
        $names = json_encode($data['sections']);
        $this->assertStringNotContainsString('Secret', $names);
        $this->assertStringNotContainsString('Hidden section', $names);

        // Sections only: the counts stay, the names of the activities are not given.
        $data = enrol_landing::export(get_course($course->id), 'sections');
        $this->assertTrue($data['hassections']);
        $this->assertSame([], $data['sections'][0]['activities']);
        $this->assertSame(1, $data['sections'][0]['count']);

        $data = enrol_landing::export(get_course($course->id), 'none');
        $this->assertFalse($data['hassections']);
        $this->assertSame([], $data['sections']);
    }

    /**
     * Custom fields, tags and the end of enrolment show without the theme knowing them beforehand.
     */
    public function test_details_tags_and_deadline(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $custom = $generator->get_plugin_generator('core_customfield');
        $category = $custom->create_category(['component' => 'core_course', 'area' => 'course']);
        $topics = $custom->create_field(['categoryid' => $category->get('id'), 'type' => 'text',
            'shortname' => 'topics', 'name' => 'Topics']);
        $custom->create_field(['categoryid' => $category->get('id'), 'type' => 'text',
            'shortname' => 'empty', 'name' => 'Left empty']);
        // Visible to nobody: 0 is "not visible" for course fields.
        $secret = $custom->create_field(['categoryid' => $category->get('id'), 'type' => 'text',
            'shortname' => 'secret', 'name' => 'Internal code', 'configdata' => ['visibility' => 0]]);
        $course = $generator->create_course(['tags' => ['planning', 'agile']]);
        $custom->add_instance_data($topics, $course->id, 'Scope and schedule');
        $custom->add_instance_data($secret, $course->id, 'X-99');

        $end = time() + WEEKSECS;
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $DB->update_record('enrol', (object) ['id' => $self->id, 'status' => ENROL_INSTANCE_ENABLED, 'enrolenddate' => $end]);

        $this->setUser($generator->create_user());
        $details = enrol_landing::details($course->id);
        $this->assertSame(['Topics'], array_column($details, 'name'));
        $this->assertStringContainsString('Scope and schedule', $details[0]['value']);

        $tags = array_column(enrol_landing::tags($course->id), 'name');
        sort($tags);
        $this->assertSame(['agile', 'planning'], $tags);

        $this->assertSame($end, enrol_landing::deadline($course->id));
        // Without an end the page promises no date.
        $DB->set_field('enrol', 'enrolenddate', 0, ['id' => $self->id]);
        $this->assertSame(0, enrol_landing::deadline($course->id));
    }

    /**
     * The dates of the course settings are always told, whether they have passed or not.
     */
    public function test_course_dates(): void {
        $this->resetAfterTest();
        $start = time() - WEEKSECS;
        $end = time() + 4 * WEEKSECS;
        $course = $this->getDataGenerator()->create_course(['startdate' => $start, 'enddate' => $end]);
        $this->setUser($this->getDataGenerator()->create_user());

        $facts = enrol_landing::export(get_course($course->id))['facts'];
        $dates = array_values(array_filter($facts, fn($fact) => !empty($fact['isdate'])));
        $this->assertCount(2, $dates);
        $this->assertSame(get_string('enrolfactstarted', 'theme_rsmax'), $dates[0]['label']);
        $this->assertSame(userdate($start, get_string('strftimedatefullshort')), $dates[0]['value']);
        $this->assertSame(get_string('enrolfactends', 'theme_rsmax'), $dates[1]['label']);
        $this->assertSame(userdate($end, get_string('strftimedatefullshort')), $dates[1]['value']);

        // A course without an end only tells its start.
        $course = $this->getDataGenerator()->create_course(['startdate' => time() + DAYSECS, 'enddate' => 0]);
        $facts = enrol_landing::export(get_course($course->id))['facts'];
        $dates = array_values(array_filter($facts, fn($fact) => !empty($fact['isdate'])));
        $this->assertCount(1, $dates);
        $this->assertSame(get_string('enrolfactstarts', 'theme_rsmax'), $dates[0]['label']);
    }

    /**
     * The theme writes the blocks of new courses, and never over a value the site set itself.
     */
    public function test_default_course_blocks(): void {
        $this->resetAfterTest();
        unset_config('defaultblocks_override');
        unset_config('defaultblockswritten', 'theme_rsmax');

        set_config('coursedefaultblocks', 'calendar_upcoming,not_a_block,html', 'theme_rsmax');
        $this->assertTrue(course_blocks::apply());
        $this->assertSame(':calendar_upcoming,html', get_config('core', 'defaultblocks_override'));

        // A new course starts with them.
        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);
        global $DB;
        $blocks = $DB->get_fieldset_select('block_instances', 'blockname', 'parentcontextid = ?', [$context->id]);
        sort($blocks);
        $this->assertSame(['calendar_upcoming', 'html'], $blocks);

        // Emptying the setting removes the value the theme wrote.
        set_config('coursedefaultblocks', '', 'theme_rsmax');
        $this->assertTrue(course_blocks::apply());
        $this->assertFalse(get_config('core', 'defaultblocks_override'));

        // A value somebody else set is left alone.
        set_config('defaultblocks_override', 'participants:');
        set_config('coursedefaultblocks', 'html', 'theme_rsmax');
        $this->assertFalse(course_blocks::apply());
        $this->assertSame('participants:', get_config('core', 'defaultblocks_override'));
    }
}
