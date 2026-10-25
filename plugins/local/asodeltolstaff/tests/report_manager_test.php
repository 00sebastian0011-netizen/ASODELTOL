<?php
namespace local_asodeltolstaff;

/** Enrollment exports must preserve scope and distinguish native completion. */
#[\PHPUnit\Framework\Attributes\CoversClass(report_manager::class)]
final class report_manager_test extends \advanced_testcase {
    /** Test fixture: one learner in two groups, another learner suspended. */
    private function fixture(): array {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectMessages();
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        set_config('enablecompletion', 1);
        get_string_manager()->reset_caches(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'idnumber' => 'SYN-REPORT']);
        $a = $generator->create_group(['courseid' => $course->id, 'name' => 'Synthetic A']);
        $b = $generator->create_group(['courseid' => $course->id, 'name' => 'Synthetic B']);
        $first = $generator->create_user(['username' => 'report.synthetic.first', 'email' => 'first@example.invalid']);
        $second = $generator->create_user(['username' => 'report.synthetic.second', 'email' => 'second@example.invalid', 'suspended' => 1]);
        $trainer = $generator->create_user(['email' => 'trainer@example.invalid']);
        $generator->enrol_user($first->id, $course->id, 'student');
        $generator->enrol_user($second->id, $course->id, 'student');
        $generator->enrol_user($trainer->id, $course->id, 'editingteacher');
        groups_add_member($a->id, $first->id);
        groups_add_member($b->id, $first->id);
        groups_add_member($b->id, $second->id);
        groups_add_member($a->id, $trainer->id);
        $completion = new \completion_completion(['course' => $course->id, 'userid' => $first->id]);
        $completion->mark_complete(1700000000);
        return [$course, $a, $b, $first, $second, $trainer];
    }

    public function test_consolidated_deduplicates_learners_and_reconciles_completion(): void {
        [$course] = $this->fixture();
        $rows = report_manager::rows($course->id, 'consolidated');
        $this->assertCount(1, $rows);
        $this->assertSame('SYN-REPORT', $rows[0]['trainingactioncode']);
        $this->assertSame(2, $rows[0]['groupcount']);
        $this->assertSame(2, $rows[0]['registeredcount']);
        $this->assertSame(1, $rows[0]['activecount']);
        $this->assertSame(1, $rows[0]['moodlecompletedcount']);
        $this->assertSame(1, $rows[0]['completionenabled']);
    }

    public function test_participants_include_suspended_state_and_completion_timestamp(): void {
        [$course] = $this->fixture();
        $rows = report_manager::rows($course->id, 'participants');
        $this->assertCount(3, $rows);
        $suspended = array_values(array_filter($rows, static fn(array $r): bool => $r['username'] === 'report.synthetic.second'));
        $this->assertCount(1, $suspended);
        $this->assertSame('inactive', $suspended[0]['enrolmentstate']);
        $this->assertSame(0, $suspended[0]['moodlecompleted']);
        $this->assertSame('', $suspended[0]['completionutc']);
        $completed = array_values(array_filter($rows, static fn(array $r): bool => $r['username'] === 'report.synthetic.first'));
        $this->assertSame('2023-11-14T22:13:20Z', $completed[0]['completionutc']);
    }

    public function test_trainer_exports_only_authorized_group(): void {
        [$course, $a, $b, $first, $second, $trainer] = $this->fixture();
        $this->setUser($trainer);
        $rows = report_manager::rows($course->id, 'participants');
        $this->assertCount(1, $rows);
        $this->assertSame('Synthetic A', $rows[0]['group']);
        $this->assertSame($first->username, $rows[0]['username']);
        $summary = report_manager::rows($course->id, 'consolidated')[0];
        $this->assertSame(1, $summary['registeredcount']);
        $this->assertSame(1, $summary['groupcount']);
    }

    public function test_student_cannot_export_enrollment_data(): void {
        [$course, $a, $b, $student] = $this->fixture();
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        report_manager::rows($course->id, 'participants');
    }

    public function test_invalid_dataset_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        report_manager::columns('arbitrary_sql');
    }

    public function test_no_group_membership_never_falls_back_to_all_learners(): void {
        global $CFG;
        [$course, $a, $b, $first, $second, $trainer] = $this->fixture();
        groups_remove_member($a->id, $trainer->id);
        $this->setUser($trainer);
        $this->assertSame([], report_manager::rows($course->id, 'participants'));
        $this->assertSame([], report_manager::rows($course->id, 'consolidated'));
    }
}
