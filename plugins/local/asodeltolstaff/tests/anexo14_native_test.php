<?php
// Moodle native workflows tested exclusively in an isolated PHPUnit database.
namespace local_asodeltolstaff;

/** Native integration evidence; generated fixtures are not institutional data. */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class anexo14_native_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectMessages();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/message/lib.php');
    }

    /** REQ-13-01: full course lifecycle through native Moodle APIs. */
    public function test_req_13_01_course_lifecycle(): void {
        global $DB;
        $category = $this->getDataGenerator()->create_category();
        $course = create_course((object) [
            'category' => $category->id, 'fullname' => 'Synthetic action',
            'shortname' => 'SYNTHETIC-A14', 'idnumber' => 'SYNTHETIC-A14',
            'summary' => 'Synthetic isolated fixture', 'summaryformat' => FORMAT_PLAIN,
            'visible' => 0, 'format' => 'topics', 'numsections' => 1, 'newsitems' => 0,
        ]);
        $this->assertSame('Synthetic action', $DB->get_field('course', 'fullname', ['id' => $course->id]));
        $this->assertEquals(0, $DB->get_field('course', 'visible', ['id' => $course->id]));
        $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $course->fullname = 'Synthetic action edited';
        update_course($course);
        $this->assertSame('Synthetic action edited', $DB->get_field('course', 'fullname', ['id' => $course->id]));
        $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $this->assertTrue(delete_course($course, false));
        $this->assertFalse($DB->record_exists('course', ['id' => $course->id]));
    }

    /** REQ-14-01: group lifecycle including membership. */
    public function test_req_14_01_group_lifecycle(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['email' => 'group.synthetic@example.invalid']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $id = groups_create_group((object) ['courseid' => $course->id, 'name' => 'Synthetic G01',
            'idnumber' => 'SYN-G01', 'description' => 'Synthetic isolated group']);
        $this->assertSame('Synthetic G01', $DB->get_field('groups', 'name', ['id' => $id]));
        groups_update_group((object) ['id' => $id, 'courseid' => $course->id, 'name' => 'Synthetic G01 edited']);
        $this->assertSame('Synthetic G01 edited', $DB->get_field('groups', 'name', ['id' => $id]));
        $this->assertTrue(groups_add_member($id, $user->id));
        $this->assertTrue(groups_is_member($id, $user->id));
        $this->assertTrue(groups_delete_group($id));
        $this->assertFalse($DB->record_exists('groups', ['id' => $id]));
        $this->assertFalse($DB->record_exists('groups_members', ['groupid' => $id]));
    }

    /** REQ-15-01: native user lifecycle without passwords or personal data. */
    public function test_req_15_01_user_lifecycle(): void {
        global $CFG, $DB;
        $id = \core_user::create_user((object) [
            'auth' => 'nologin', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
            'username' => 'anexo14.synthetic.user', 'firstname' => 'Synthetic', 'lastname' => 'Fixture',
            'email' => 'anexo14.synthetic@example.invalid',
        ], false);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Synthetic', $user->firstname);
        $user->firstname = 'Edited synthetic';
        \core_user::update_user($user, false);
        $this->assertSame('Edited synthetic', $DB->get_field('user', 'firstname', ['id' => $id]));
        $this->assertTrue(delete_user($DB->get_record('user', ['id' => $id], '*', MUST_EXIST)));
        $this->assertEquals(1, $DB->get_field('user', 'deleted', ['id' => $id]));
    }

    /** REQ-16-01/02: individual and bulk selection assignment, reconciled. */
    public function test_req_16_01_02_individual_and_bulk_assignment(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $users = [];
        for ($index = 0; $index < 3; $index++) {
            $users[] = $this->getDataGenerator()->create_user(['email' => "bulk{$index}@example.invalid"]);
            $this->getDataGenerator()->enrol_user($users[$index]->id, $course->id, 'student');
        }
        groups_add_member($group->id, $users[0]->id);
        $this->assertTrue(groups_is_member($group->id, $users[0]->id));
        foreach ($users as $user) {
            $this->assertTrue(groups_add_member($group->id, $user->id));
        }
        $this->assertEquals(3, $DB->count_records('groups_members', ['groupid' => $group->id]));
        foreach ($users as $user) {
            $this->assertTrue(groups_is_member($group->id, $user->id));
            $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
        }
    }

    /** REQ-17-02: role fields and effective allow/deny capabilities. */
    public function test_req_17_02_custom_role_capabilities(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['email' => 'role.synthetic@example.invalid']);
        $roleid = create_role('Synthetic isolated role', 'anexo14synthetic', 'Synthetic description');
        $context = \context_course::instance($course->id);
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/course:update', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);
        accesslib_clear_all_caches_for_unit_testing();
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        $this->assertSame('anexo14synthetic', $role->shortname);
        $this->assertSame('Synthetic description', $role->description);
        $this->assertTrue(has_capability('moodle/course:view', $context));
        $this->assertFalse(has_capability('moodle/course:update', $context));
    }

    /** REQ-08-08: native access engine blocks UT2 until UT1 is complete. */
    public function test_req_08_08_sequential_access(): void {
        set_config('enablecompletion', 1);
        set_config('enableavailability', 1);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $learner = $generator->create_and_enrol($course, 'student');
        $first = $generator->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $condition = json_encode(['op' => '&', 'c' => [
            ['type' => 'completion', 'cm' => $first->cmid, 'e' => COMPLETION_COMPLETE],
        ], 'showc' => [false]]);
        $second = $generator->create_module('page', ['course' => $course->id, 'availability' => $condition]);
        $this->setUser($learner);
        $info = get_fast_modinfo($course, $learner->id);
        $this->assertTrue($info->get_cm($first->cmid)->uservisible);
        $this->assertFalse($info->get_cm($second->cmid)->uservisible);
        $completion = new \completion_info($course);
        $completion->update_state($info->get_cm($first->cmid), COMPLETION_COMPLETE, $learner->id);
        get_fast_modinfo($course, $learner->id, true);
        $this->assertTrue(get_fast_modinfo($course, $learner->id)->get_cm($second->cmid)->uservisible);
    }

    /** REQ-20-04/21-03: real native messaging, no external transport. */
    public function test_req_20_04_21_03_bidirectional_messaging(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $learner = $generator->create_and_enrol($course, 'student', ['email' => 'learner@example.invalid']);
        $observer = $generator->create_user(['email' => 'observer@example.invalid']);
        $roleid = create_role('Synthetic observer', 'syntheticobserver', 'Isolated read-only observer');
        $context = \context_course::instance($course->id);
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/grade:edit', CAP_PROHIBIT, $roleid, $context->id);
        $generator->enrol_user($observer->id, $course->id, $roleid);
        set_config('messaging', 1);
        foreach ([$learner, $observer] as $user) {
            set_user_preference('message_blocknoncontacts', \core_message\api::MESSAGE_PRIVACY_COURSEMEMBER, $user);
        }
        $conversation = \core_message\api::create_conversation(
            \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL, [$learner->id, $observer->id]
        );
        $this->setUser($learner);
        $this->assertTrue(\core_message\api::can_send_message_to_conversation($learner->id, $conversation->id));
        \core_message\api::send_message_to_conversation($learner->id, $conversation->id, 'Synthetic question', FORMAT_PLAIN);
        $this->setUser($observer);
        $this->assertFalse(has_capability('moodle/grade:edit', $context));
        $received = \core_message\api::get_conversation_messages($observer->id, $conversation->id);
        $this->assertStringContainsString('Synthetic question', $received['messages'][0]->text);
        \core_message\api::send_message_to_conversation($observer->id, $conversation->id, 'Synthetic reply', FORMAT_PLAIN);
        $this->setUser($learner);
        $received = \core_message\api::get_conversation_messages($learner->id, $conversation->id);
        $this->assertCount(2, $received['messages']);
        $this->assertSame([(int) $learner->id, (int) $observer->id], array_map(
            static fn(\stdClass $message): int => (int) $message->useridfrom, $received['messages']
        ));
    }

    /** Create a grade using the capacitador's native assignment API. */
    private function grade_fixture(): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['showgrades' => 1]);
        $trainer = $generator->create_and_enrol($course, 'editingteacher');
        $learner = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $activity = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $cm = get_coursemodule_from_id('assign', $activity->cmid, $course->id, false, MUST_EXIST);
        $this->setUser($trainer);
        $assignment = new \assign(\context_module::instance($cm->id), $cm, $course);
        $assignment->save_grade($learner->id, (object) ['grade' => 62, 'attemptnumber' => -1,
            'addattempt' => 0, 'workflowstate' => '', 'applytoall' => 0]);
        return [$course, $activity, $learner, $other];
    }

    /** REQ-19-04: learner sees the grade recorded by the trainer. */
    public function test_req_19_04_grade_visible_to_learner(): void {
        [$course, $activity, $learner] = $this->grade_fixture();
        $this->setUser($learner);
        $response = \gradereport_user\external\user::get_grade_items($course->id, $learner->id);
        $items = array_values(array_filter($response['usergrades'][0]['gradeitems'],
            static fn(array $item): bool => ($item['itemmodule'] ?? '') === 'assign'
                && (int) $item['iteminstance'] === (int) $activity->id));
        $this->assertCount(1, $items);
        $this->assertEquals(62, $items[0]['graderaw']);
    }

    /** Beneficiaries cannot retrieve another learner's grades by ID. */
    public function test_other_learner_grade_is_denied(): void {
        [$course, $activity, $learner, $other] = $this->grade_fixture();
        $this->setUser($other);
        $this->expectException(\moodle_exception::class);
        \gradereport_user\external\user::get_grade_items($course->id, $learner->id);
    }
}
