<?php
namespace local_asodeltolstaff;

/** Native calendar storage and trainer/group permissions. */
#[\PHPUnit\Framework\Attributes\CoversClass(office_hours::class)]
final class office_hours_test extends \advanced_testcase {
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        get_string_manager()->reset_caches(true);
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $a = $g->create_group(['courseid' => $course->id]);
        $b = $g->create_group(['courseid' => $course->id]);
        $trainer = $g->create_and_enrol($course, 'editingteacher');
        groups_add_member($a->id, $trainer->id);
        return [$course, $a, $b, $trainer];
    }

    public function test_explicit_hour_is_stored_in_native_calendar_idempotently(): void {
        global $DB;
        [$course, $a, $b, $trainer] = $this->fixture();
        $start = time() + HOURSECS;
        $id = office_hours::create($course->id, $a->id, $trainer->id, $start, $start + HOURSECS, 'https://meeting.example.invalid/test');
        $event = $DB->get_record('event', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('local_asodeltolstaff', $event->component);
        $this->assertSame('group', $event->eventtype);
        $this->assertEquals($a->id, $event->groupid);
        $this->assertEquals($trainer->id, $event->userid);
        $this->assertEquals(HOURSECS, $event->timeduration);
        $this->assertSame($id, office_hours::create($course->id, $a->id, $trainer->id, $start, $start + HOURSECS, 'https://meeting.example.invalid/test'));
        $this->assertEquals(1, $DB->count_records('event', ['component' => 'local_asodeltolstaff']));
    }

    public function test_trainer_cannot_schedule_another_group(): void {
        [$course, $a, $b, $trainer] = $this->fixture();
        $this->setUser($trainer);
        $this->expectException(\moodle_exception::class);
        office_hours::create($course->id, $b->id, $trainer->id, time(), time() + HOURSECS);
    }

    public function test_short_consultation_is_rejected_without_writing(): void {
        [$course, $a, $b, $trainer] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        office_hours::create($course->id, $a->id, $trainer->id, time(), time() + 30 * MINSECS);
    }

    public function test_student_cannot_schedule(): void {
        [$course, $a, $b, $trainer] = $this->fixture();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        office_hours::create($course->id, $a->id, $trainer->id, time(), time() + HOURSECS);
    }

    public function test_inactive_trainer_cannot_be_scheduled(): void {
        global $DB;
        [$course, $a, $b, $trainer] = $this->fixture();
        $DB->set_field('user', 'suspended', 1, ['id' => $trainer->id]);
        $this->expectException(\moodle_exception::class);
        office_hours::create($course->id, $a->id, $trainer->id, time(), time() + HOURSECS);
    }

    public function test_duplicate_never_silently_overwrites_meeting_details(): void {
        [$course, $a, $b, $trainer] = $this->fixture();
        $start = time();
        office_hours::create($course->id, $a->id, $trainer->id, $start, $start + HOURSECS);
        $this->expectException(\invalid_parameter_exception::class);
        office_hours::create($course->id, $a->id, $trainer->id, $start, $start + HOURSECS, 'https://different.example.invalid');
    }
}
