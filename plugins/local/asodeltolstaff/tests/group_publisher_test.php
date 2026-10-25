<?php
namespace local_asodeltolstaff;

/** Resource publishing must never grant course-wide editing or expose another group. */
#[\PHPUnit\Framework\Attributes\CoversClass(group_publisher::class)]
final class group_publisher_test extends \advanced_testcase {
    private function fixture(): array {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/group/lib.php');
        set_config('enableavailability', 1);
        get_string_manager()->reset_caches(true);
        $g = $this->getDataGenerator();
        $course = $g->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $a = $g->create_group(['courseid' => $course->id]);
        $b = $g->create_group(['courseid' => $course->id]);
        $trainer = $g->create_and_enrol($course, 'teacher');
        $first = $g->create_and_enrol($course, 'student');
        $second = $g->create_and_enrol($course, 'student');
        groups_add_member($a->id, $trainer->id);
        groups_add_member($a->id, $first->id);
        groups_add_member($b->id, $second->id);
        $role = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('local/asodeltolstaff:publishown', CAP_ALLOW, $role, \context_course::instance($course->id)->id, true);
        $this->setUser($trainer);
        accesslib_clear_all_caches_for_unit_testing();
        return [$course, $a, $b, $trainer, $first, $second];
    }

    public function test_own_group_page_is_visible_only_to_assigned_group(): void {
        [$course, $a, $b, $trainer, $first, $second] = $this->fixture();
        $this->assertFalse(has_capability('moodle/course:manageactivities', \context_course::instance($course->id)));
        $id = group_publisher::create($course->id, $a->id, 1, 'page', 'Synthetic group page', 'SYNTHETIC-QA-NOT-APPROVAL', '<p>Synthetic fixture</p>');
        $this->setUser($first);
        $this->assertTrue(get_fast_modinfo($course, $first->id)->get_cm($id)->uservisible);
        $this->setUser($second);
        $this->assertFalse(get_fast_modinfo($course, $second->id)->get_cm($id)->uservisible);
    }

    public function test_other_group_is_denied_before_writing(): void {
        global $DB;
        [$course, $a, $b] = $this->fixture();
        $before = $DB->count_records('course_modules', ['course' => $course->id]);
        try {
            group_publisher::create($course->id, $b->id, 1, 'page', 'Denied', 'SYNTHETIC', '<p>Fixture</p>');
            $this->fail('Other group accepted');
        } catch (\moodle_exception $exception) {
            $this->assertEquals($before, $DB->count_records('course_modules', ['course' => $course->id]));
        }
    }

    public function test_student_is_denied(): void {
        [$course, $a, $b, $trainer, $student] = $this->fixture();
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        group_publisher::create($course->id, $a->id, 1, 'page', 'Denied', 'SYNTHETIC', '<p>Fixture</p>');
    }

    public function test_sanitizes_html_and_does_not_create_grade_items(): void {
        global $DB;
        [$course, $a] = $this->fixture();
        $before = $DB->count_records('grade_items', ['courseid' => $course->id]);
        $id = group_publisher::create($course->id, $a->id, 1, 'page', 'Safe fixture', 'SYNTHETIC',
            '<p>Fixture</p><script>alert(1)</script><img src="x" onerror="alert(2)">');
        $cm = get_coursemodule_from_id('page', $id, $course->id, false, MUST_EXIST);
        $html = $DB->get_field('page', 'content', ['id' => $cm->instance]);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertEquals($before, $DB->count_records('grade_items', ['courseid' => $course->id]));
    }

    public function test_executable_module_type_is_not_accepted(): void {
        [$course, $a] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        group_publisher::create($course->id, $a->id, 1, 'quiz', 'Denied', 'SYNTHETIC');
    }

    public function test_credential_bearing_url_is_rejected(): void {
        [$course, $a] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        group_publisher::create($course->id, $a->id, 1, 'url', 'Denied', 'SYNTHETIC', '', 'https://user:secret@example.invalid');
    }

    public function test_resource_promotes_only_current_users_file_with_integrity(): void {
        [$course, $a, $b, $trainer] = $this->fixture();
        $draft = file_get_unused_draft_itemid();
        $data = '%PDF-1.4 synthetic test fixture, not an academic document';
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($trainer->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draft, 'filepath' => '/', 'filename' => 'synthetic.pdf'], $data);
        $id = group_publisher::create($course->id, $a->id, 1, 'resource', 'Synthetic file', 'SYNTHETIC', '', '', $draft);
        $files = get_file_storage()->get_area_files(\context_module::instance($id)->id, 'mod_resource', 'content', 0, 'id', false);
        $this->assertCount(1, $files);
        $this->assertSame($data, reset($files)->get_content());
    }

    public function test_another_users_draft_is_denied(): void {
        [$course, $a, $b, $trainer, $student] = $this->fixture();
        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($student->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draft, 'filepath' => '/', 'filename' => 'synthetic.pdf'], '%PDF-1.4 fixture');
        $this->expectException(\invalid_parameter_exception::class);
        group_publisher::create($course->id, $a->id, 1, 'resource', 'Denied', 'SYNTHETIC', '', '', $draft);
    }
}
