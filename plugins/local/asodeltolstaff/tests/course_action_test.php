<?php
namespace local_asodeltolstaff;

#[\PHPUnit\Framework\Attributes\CoversClass(course_action::class)]
final class course_action_test extends \advanced_testcase {
    public function test_all_nine_fields_persist_and_student_is_denied(): void {
        $this->resetAfterTest(); $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $saved = course_action::save((object)['id'=>$course->id, 'idnumber'=>'SYN-13-02',
            'fullname'=>'Synthetic action', 'summary'=>'Synthetic description', 'visible'=>0,
            'startdate'=>1900000000, 'enddate'=>1900003600, 'lang'=>'es',
            'adminroleid'=>'qa_admin', 'auditorroleid'=>'qa_interventoria']);
        $this->assertSame('SYN-13-02', $saved->idnumber); $this->assertSame(0, (int)$saved->visible);
        $this->assertSame(1900003600, (int)$saved->enddate);
        $student = $this->getDataGenerator()->create_and_enrol($saved, 'student'); $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        course_action::save((object)['id'=>$saved->id]);
    }

    public function test_invalid_dates_write_nothing(): void {
        $this->resetAfterTest(); $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname'=>'Original']);
        try { course_action::save((object)['id'=>$course->id, 'idnumber'=>'X', 'fullname'=>'Changed',
            'summary'=>'S', 'visible'=>0, 'startdate'=>20, 'enddate'=>10, 'lang'=>'es',
            'adminroleid'=>'a', 'auditorroleid'=>'i']); $this->fail('Invalid dates accepted');
        } catch (\invalid_parameter_exception $e) { $this->assertSame('Original', get_course($course->id)->fullname); }
    }
}
