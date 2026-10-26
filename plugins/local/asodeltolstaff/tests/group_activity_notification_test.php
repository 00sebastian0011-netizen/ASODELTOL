<?php
namespace local_asodeltolstaff;

#[\PHPUnit\Framework\Attributes\CoversClass(group_publisher::class)]
final class group_activity_notification_test extends \advanced_testcase {
    public function test_assignment_is_pending_group_scoped_and_notifies_only_its_beneficiaries(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);
        $g = $this->getDataGenerator();
        $course = $g->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $firstgroup = $g->create_group(['courseid' => $course->id]);
        $secondgroup = $g->create_group(['courseid' => $course->id]);
        $trainer = $g->create_and_enrol($course, 'teacher');
        $first = $g->create_and_enrol($course, 'student');
        $second = $g->create_and_enrol($course, 'student');
        groups_add_member($firstgroup->id, $trainer->id);
        groups_add_member($firstgroup->id, $first->id);
        groups_add_member($secondgroup->id, $second->id);
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('local/asodeltolstaff:publishown', CAP_ALLOW, $teacherrole,
            \context_course::instance($course->id)->id);
        $this->setUser($trainer);
        accesslib_clear_all_caches_for_unit_testing();
        $sink = $this->redirectMessages();
        $cmid = group_publisher::create($course->id, $firstgroup->id, 1, 'assign', 'Synthetic pending activity',
            'QA fixture only', '<p>Synthetic instructions</p>', '', 0, time() + DAYSECS);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($first->id, $messages[0]->useridto);
        $this->assertSame(get_string('activitypublishedsubject', 'local_asodeltolstaff'), $messages[0]->subject);
        $this->assertStringContainsString('Synthetic pending activity', $messages[0]->fullmessage);
        $this->assertTrue(get_fast_modinfo($course, $first->id)->get_cm($cmid)->uservisible);
        $this->assertFalse(get_fast_modinfo($course, $second->id)->get_cm($cmid)->uservisible);
        $instance = $DB->get_record('assign', ['id' => get_coursemodule_from_id('assign', $cmid)->instance], '*', MUST_EXIST);
        $this->assertSame(0, (int) $instance->grade);
        $this->assertGreaterThan(time(), (int) $instance->duedate);
        require_once($GLOBALS['CFG']->dirroot . '/mod/assign/locallib.php');
        $cm = get_coursemodule_from_id('assign', $cmid);
        $assignment = new \assign(\context_module::instance($cmid), $cm, $course);
        $this->assertSame(GRADE_TYPE_TEXT, (int)$assignment->get_grade_item()->gradetype);
        $this->assertSame(0, $DB->count_records('grade_grades', ['itemid' => $assignment->get_grade_item()->id]));

        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($trainer->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draft, 'filepath' => '/',
            'filename' => 'synthetic-material.pdf'], '%PDF-1.4 synthetic material fixture');
        $material = group_publisher::create($course->id, $firstgroup->id, 1, 'resource', 'Synthetic material',
            'QA fixture only', '', '', $draft);
        $this->assertTrue(get_fast_modinfo($course, $first->id)->get_cm($material)->uservisible);
        $this->assertFalse(get_fast_modinfo($course, $second->id)->get_cm($material)->uservisible);
    }
}
