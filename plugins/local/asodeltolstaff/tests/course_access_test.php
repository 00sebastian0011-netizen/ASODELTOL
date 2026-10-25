<?php
namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

#[\PHPUnit\Framework\Attributes\CoversClass(course_access::class)]
final class course_access_test extends \advanced_testcase {
    public function test_site_course_and_guest_are_not_exportable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertArrayNotHasKey(SITEID, course_access::exportable_courses());
        $this->setGuestUser();
        $this->assertSame([], course_access::exportable_courses());
    }
    public function test_manager_can_select_course_without_enrolment(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $admin = get_admin();
        $this->setUser($admin);
        $this->assertArrayHasKey($course->id, course_access::exportable_courses());
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $admin->id]));
    }

    public function test_student_without_export_capability_is_excluded(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $this->assertArrayNotHasKey($course->id, course_access::exportable_courses());
    }

    public function test_trainer_is_limited_to_courses_with_export_capability(): void {
        $this->resetAfterTest();
        $allowed = $this->getDataGenerator()->create_course(['visible' => 1]);
        $other = $this->getDataGenerator()->create_course(['visible' => 1]);
        $trainer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($trainer->id, $allowed->id, 'teacher');
        $this->setUser($trainer);
        $courses = course_access::exportable_courses();
        $this->assertArrayHasKey($allowed->id, $courses);
        $this->assertArrayNotHasKey($other->id, $courses);
    }
}
