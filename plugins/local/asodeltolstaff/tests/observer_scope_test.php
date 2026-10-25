<?php
namespace local_asodeltolstaff;

/** Proposed observer profiles: synthetic tests, not institutional role assignment. */
#[\PHPUnit\Framework\Attributes\CoversClass(report_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(progress_report::class)]
final class observer_scope_test extends \advanced_testcase {
    public static function profiles(): array {
        return [['sena'], ['interventoria']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('profiles')]
    public function test_observer_reads_own_group_without_write_permissions(string $profile): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $othercourse = $g->create_course();
        $context = \context_course::instance($course->id);
        $role = create_role('Synthetic ' . $profile, 'qa_' . $profile, 'Proposal only');
        foreach (['moodle/course:view', 'moodle/grade:viewall',
                'local/asodeltolstaff:viewsessions', 'local/asodeltolstaff:export'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $role, $context->id);
        }
        $observer = $g->create_user();
        $g->enrol_user($observer->id, $course->id, $role);
        $learner = $g->create_and_enrol($course, 'student');
        $outsider = $g->create_and_enrol($course, 'student');
        $group = $g->create_group(['courseid' => $course->id]);
        $othergroup = $g->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $observer->id);
        groups_add_member($group->id, $learner->id);
        groups_add_member($othergroup->id, $outsider->id);
        $this->setUser($observer);
        accesslib_clear_all_caches_for_unit_testing();
        foreach (['moodle/grade:edit', 'moodle/course:update', 'moodle/role:assign',
                'local/asodeltolstaff:manageown', 'local/asodeltolstaff:validate'] as $capability) {
            $this->assertFalse(has_capability($capability, $context));
        }
        $this->assertArrayHasKey($course->id, course_access::exportable_courses());
        $this->assertArrayNotHasKey($othercourse->id, course_access::exportable_courses());
        $rows = report_manager::rows($course->id, 'participants');
        $this->assertCount(1, $rows);
        $this->assertSame($learner->username, $rows[0]['username']);
        $this->assertSame((int) $learner->id, progress_report::summary($course->id, $learner->id)['userid']);
        $this->expectException(\moodle_exception::class);
        progress_report::summary($course->id, $outsider->id);
    }
}
