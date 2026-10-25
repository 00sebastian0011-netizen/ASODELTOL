<?php
namespace local_asodeltolstaff;

/** Native completion counts, own-record privacy and staff group scope. */
#[\PHPUnit\Framework\Attributes\CoversClass(progress_report::class)]
final class progress_report_test extends \advanced_testcase {
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecompletion', 1);
        get_string_manager()->reset_caches(true);
        $g = $this->getDataGenerator();
        $course = $g->create_course(['numsections' => 2, 'enablecompletion' => 1]);
        $a = $g->create_group(['courseid' => $course->id]);
        $b = $g->create_group(['courseid' => $course->id]);
        $first = $g->create_and_enrol($course, 'student');
        $second = $g->create_and_enrol($course, 'student');
        $trainer = $g->create_and_enrol($course, 'teacher');
        groups_add_member($a->id, $first->id);
        groups_add_member($a->id, $trainer->id);
        groups_add_member($b->id, $second->id);
        $p = $g->create_module('page', ['course' => $course->id, 'section' => 1, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $g->create_module('page', ['course' => $course->id, 'section' => 1, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $g->create_module('page', ['course' => $course->id, 'section' => 2, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $completion = new \completion_info($course);
        $this->setUser($first);
        $completion->update_state(get_fast_modinfo($course)->get_cm($p->cmid), COMPLETION_COMPLETE, $first->id);
        return [$course, $a, $b, $first, $second, $trainer];
    }

    public function test_unit_and_course_counts_reconcile_with_native_progress(): void {
        [$course, $a, $b, $first] = $this->fixture();
        $data = progress_report::summary($course->id, $first->id);
        $this->assertEqualsWithDelta(100 / 3, $data['overall_percentage'], 0.001);
        $this->assertCount(2, $data['units']);
        $this->assertEquals(50, $data['units'][0]['percentage']);
        $this->assertEquals(0, $data['units'][1]['percentage']);
    }

    public function test_learner_cannot_request_another_learner_by_id(): void {
        [$course, $a, $b, $first, $second] = $this->fixture();
        $this->expectException(\required_capability_exception::class);
        progress_report::summary($course->id, $second->id);
    }

    public function test_trainer_can_read_own_group_but_not_another_group(): void {
        [$course, $a, $b, $first, $second, $trainer] = $this->fixture();
        $this->setUser($trainer);
        $this->assertEqualsWithDelta(100 / 3, progress_report::summary($course->id, $first->id)['overall_percentage'], 0.001);
        $this->expectException(\moodle_exception::class);
        progress_report::summary($course->id, $second->id);
    }

    public function test_read_only_observer_can_read_both_groups(): void {
        global $DB;
        [$course, $a, $b, $first, $second] = $this->fixture();
        $this->setAdminUser();
        $role = create_role('Synthetic progress observer', 'syntheticprogress', 'Read only');
        $context = \context_course::instance($course->id);
        foreach (['local/asodeltolstaff:viewsessions', 'local/asodeltolstaff:viewall', 'moodle/grade:viewall'] as $cap) {
            assign_capability($cap, CAP_ALLOW, $role, $context->id);
        }
        $observer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($observer->id, $course->id, $role);
        $this->setUser($observer);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('moodle/grade:edit', $context));
        $this->assertEqualsWithDelta(100 / 3, progress_report::summary($course->id, $first->id)['overall_percentage'], 0.001);
        $this->assertEquals(0, progress_report::summary($course->id, $second->id)['overall_percentage']);
    }

    public function test_no_completion_configuration_does_not_invent_zero_percent(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $learner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($learner);
        $result = progress_report::summary($course->id, $learner->id);
        $this->assertNull($result['overall_percentage']);
        $this->assertSame([], $result['units']);
    }
}
