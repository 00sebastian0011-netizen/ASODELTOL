<?php
namespace local_asodeltolstaff;

#[\PHPUnit\Framework\Attributes\CoversClass(group_communication::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(support::class)]
final class communication_support_test extends \advanced_testcase {
    public function test_announcement_reaches_only_active_own_group(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);
        $g = $this->getDataGenerator();
        $course = $g->create_course(['groupmode' => SEPARATEGROUPS]);
        $group = $g->create_group(['courseid' => $course->id]);
        $other = $g->create_group(['courseid' => $course->id]);
        $trainer = $g->create_and_enrol($course, 'teacher');
        $learner = $g->create_and_enrol($course, 'student');
        $outsider = $g->create_and_enrol($course, 'student');
        groups_add_member($group->id, $trainer->id);
        groups_add_member($group->id, $learner->id);
        groups_add_member($other->id, $outsider->id);
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('local/asodeltolstaff:publishown', CAP_ALLOW, $teacherrole,
            \context_course::instance($course->id)->id);
        $this->setUser($trainer);
        accesslib_clear_all_caches_for_unit_testing();
        $sink = $this->redirectMessages();
        $cmid = group_communication::announce($course->id, $group->id, 1, 'Synthetic announcement', '<p>QA only</p>');
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($learner->id, $messages[0]->useridto);
        $this->assertTrue(get_fast_modinfo($course, $learner->id)->get_cm($cmid)->uservisible);
        $this->assertFalse(get_fast_modinfo($course, $outsider->id)->get_cm($cmid)->uservisible);
        $eventid = group_communication::schedule($course->id, $group->id, 'Synthetic schedule', 'QA only',
            time() + 3600, time() + 7200);
        $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
        $this->assertEquals($group->id, $event->groupid);
        $this->expectException(\moodle_exception::class);
        group_communication::schedule($course->id, $other->id, 'Denied', '', time() + 3600, time() + 7200);
    }

    public function test_support_notification_reaches_configured_admin(): void {
        $this->resetAfterTest();
        set_config('supportuserid', get_admin()->id, 'local_asodeltolstaff');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $sink = $this->redirectMessages();
        $this->assertGreaterThan(0, support::send('Synthetic support', 'Synthetic question'));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals(get_admin()->id, $messages[0]->useridto);
        $this->assertEquals($user->id, $messages[0]->useridfrom);
    }

    public function test_support_without_recipient_fails_without_delivery(): void {
        $this->resetAfterTest();
        unset_config('supportuserid', 'local_asodeltolstaff');
        $this->setUser($this->getDataGenerator()->create_user());
        $sink = $this->redirectMessages();
        try {
            support::send('Synthetic support', 'Synthetic question');
            $this->fail('Unconfigured support must reject requests');
        } catch (\moodle_exception $e) {
            $this->assertSame('supportunconfigured', $e->errorcode);
            $this->assertCount(0, $sink->get_messages());
        }
    }
}
