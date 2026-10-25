<?php
namespace local_asodeltolstaff;

#[\PHPUnit\Framework\Attributes\CoversClass(group_communication::class)]
final class section_release_test extends \advanced_testcase {
    public function test_opening_unit_adds_group_rule_and_preserves_existing_availability(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);
        require_once($CFG->dirroot . '/course/lib.php');
        $g = $this->getDataGenerator();
        $course = $g->create_course(['groupmode' => SEPARATEGROUPS]);
        $allowed = $g->create_group(['courseid' => $course->id]);
        $other = $g->create_group(['courseid' => $course->id]);
        $trainer = $g->create_and_enrol($course, 'teacher');
        $first = $g->create_and_enrol($course, 'student');
        $second = $g->create_and_enrol($course, 'student');
        groups_add_member($allowed->id, $trainer->id);
        groups_add_member($allowed->id, $first->id);
        groups_add_member($other->id, $second->id);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $original = ['op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]], 'showc' => [false]];
        course_update_section($course->id, $section, (object) ['visible' => 0, 'availability' => json_encode($original)]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('local/asodeltolstaff:publishown', CAP_ALLOW, $roleid, \context_course::instance($course->id)->id);
        assign_capability('moodle/course:sectionvisibility', CAP_ALLOW, $roleid, \context_course::instance($course->id)->id);
        $this->setUser($trainer);
        accesslib_clear_all_caches_for_unit_testing();
        group_communication::open_section($course->id, $allowed->id, $section->id);
        $updated = $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST);
        $availability = json_decode($updated->availability, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame(1, (int) $updated->visible);
        $this->assertSame(2, count($availability['c']));
        $this->assertSame('group', $availability['c'][0]['type']);
        $this->assertSame((int) $allowed->id, (int) $availability['c'][0]['id']);
        $this->assertSame($original, $availability['c'][1]);
        // Both users remain blocked by the original future date condition;
        // the new group restriction is conjunctive and does not erase it.
        $this->assertFalse(get_fast_modinfo($course, $first->id)->get_section_info(1)->uservisible);
        $this->assertFalse(get_fast_modinfo($course, $second->id)->get_section_info(1)->uservisible);
    }
}
