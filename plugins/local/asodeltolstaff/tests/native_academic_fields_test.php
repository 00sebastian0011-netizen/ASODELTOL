<?php
namespace local_asodeltolstaff;

/** Course/group fields stay in Moodle core or its Custom Fields API. */
#[\PHPUnit\Framework\Attributes\CoversClass(metadata_fields::class)]
final class native_academic_fields_test extends \advanced_testcase {
    public function test_course_native_fields_and_role_metadata_are_saved(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fields = metadata_fields::configure();
        $course = $this->getDataGenerator()->create_course([
            'idnumber' => 'QA-AF8-01', 'fullname' => 'Synthetic action', 'summary' => 'Synthetic description',
            'visible' => 0, 'startdate' => 1800000000, 'enddate' => 1801000000, 'lang' => 'es',
        ]);
        $this->assertSame('QA-AF8-01', $course->idnumber);
        $this->assertSame('Synthetic action', $course->fullname);
        $this->assertSame('Synthetic description', $course->summary);
        $this->assertSame(0, (int) $course->visible);
        $this->assertSame(1800000000, (int) $course->startdate);
        $this->assertSame(1801000000, (int) $course->enddate);
        $this->assertSame('es', $course->lang);

        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $handler->instance_form_save((object) ['id' => $course->id,
            'customfield_asodeltol_adminroleid' => 'qa-admin-role',
            'customfield_asodeltol_auditorroleid' => 'qa-auditor-role']);
        $saved = $handler->get_instance_data($course->id, true);
        $this->assertSame('qa-admin-role', $saved[$fields['asodeltol_adminroleid']]->get_value());
        $this->assertSame('qa-auditor-role', $saved[$fields['asodeltol_auditorroleid']]->get_value());
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(has_capability('local/asodeltolstaff:export', \context_course::instance($course->id)));
    }

    public function test_group_identity_description_status_and_course_link_are_saved(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fields = metadata_fields::configure();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id,
            'name' => 'QA group', 'idnumber' => 'QA-G-01', 'description' => 'Synthetic group description']);
        $this->assertEquals($course->id, $group->courseid);
        $this->assertSame('QA-G-01', $group->idnumber);
        $this->assertSame('Synthetic group description', $group->description);
        $handler = \core_customfield\handler::get_handler('core_group', 'group');
        $options = $handler->get_fields()[$fields['asodeltol_available']]->get_options();
        $availablelabel = get_string('groupavailableoption', 'local_asodeltolstaff');
        $availableindex = array_search($availablelabel, $options, true);
        $this->assertNotFalse($availableindex);
        $handler->instance_form_save((object) ['id' => $group->id,
            'customfield_asodeltol_available' => $availableindex]);
        $saved = $handler->get_instance_data($group->id, true);
        $this->assertSame((int) $availableindex, (int) $saved[$fields['asodeltol_available']]->get_value());
        $this->assertSame($availablelabel, $saved[$fields['asodeltol_available']]->export_value());
    }
}
