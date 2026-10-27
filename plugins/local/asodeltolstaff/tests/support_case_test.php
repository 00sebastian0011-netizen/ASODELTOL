<?php
namespace local_asodeltolstaff;

#[\PHPUnit\Framework\Attributes\CoversClass(support::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_asodeltolstaff\privacy\support_data::class)]
final class support_case_test extends \advanced_testcase {
    private function requester(): \stdClass {
        $this->resetAfterTest();
        set_config('supportuserid', get_admin()->id, 'local_asodeltolstaff');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        return $user;
    }

    public function test_reference_persists_and_duplicate_request_is_rejected(): void {
        global $DB;
        $user = $this->requester();
        $sink = $this->redirectMessages();
        $id = support::send('Synthetic request', 'Private synthetic body');
        $case = support::get_case($id);
        $this->assertEquals($user->id, $case->requesterid);
        $this->assertSame('open', $case->status);
        $this->assertStringContainsString('caseid=' . $id, $sink->get_messages()[0]->contexturl);
        try {
            support::send('Duplicate synthetic request', 'Private synthetic body');
            $this->fail('Duplicate request accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('supportwait', $e->errorcode);
        }
        $this->assertSame(1, $DB->count_records('local_asstaff_case'));
        $this->assertCount(1, $sink->get_messages());
    }

    public function test_cau_email_is_optional_and_does_not_replace_the_internal_case(): void {
        global $DB;
        $this->requester();
        set_config('supportemail', '', 'local_asodeltolstaff');
        $this->redirectMessages();
        $id = support::send('Synthetic CAU request', 'Synthetic CAU body');
        $case = $DB->get_record('local_asstaff_case', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(get_admin()->id, (int) $case->recipientid);
        $this->assertSame('open', $case->status);
    }

    public function test_another_user_cannot_view_or_respond(): void {
        global $DB;
        $this->requester();
        $this->redirectMessages();
        $id = support::send('Private case', 'Private body');
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([], support::visible_cases());
        foreach (['view', 'reply'] as $action) {
            try {
                $action === 'view' ? support::get_case($id) : support::respond($id, 'Denied', true);
                $this->fail('Unrelated user accessed case');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
        $this->assertSame(0, $DB->count_records('local_asstaff_caseevent'));
    }

    public function test_response_resolution_and_notifications_are_visible_to_requester(): void {
        global $DB;
        $user = $this->requester();
        $sink = $this->redirectMessages();
        $id = support::send('Synthetic issue', 'Synthetic description');
        $this->setAdminUser();
        support::respond($id, 'Synthetic first response');
        $this->assertSame('replied', support::get_case($id)->status);
        try {
            support::respond($id, 'Synthetic first response');
            $this->fail('Duplicate response accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('supportwait', $e->errorcode);
        }
        support::respond($id, 'Synthetic solution confirmed in QA', true);
        $this->assertSame('resolved', support::get_case($id)->status);
        $messages = $sink->get_messages();
        $this->assertCount(3, $messages);
        $this->assertEquals($user->id, $messages[2]->useridto);
        $this->setUser($user);
        $this->assertSame('resolved', support::get_case($id)->status);
        $this->assertSame(2, $DB->count_records('local_asstaff_caseevent', ['caseid' => $id]));
        $this->expectException(\required_capability_exception::class);
        support::respond($id, 'Requester cannot resolve cases');
    }

    public function test_privacy_is_scoped_and_removes_only_the_approved_requester(): void {
        global $DB;
        $first = $this->requester();
        $this->redirectMessages();
        $firstid = support::send('First user case', 'First user body');
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $secondid = support::send('Other user case', 'Other user body');
        $this->assertSame([$firstid], array_keys(\local_asodeltolstaff\privacy\support_data::cases($first->id)));
        $contexts = \local_asodeltolstaff\privacy\provider::get_contexts_for_userid($first->id);
        $this->assertContains((int)\context_system::instance()->id, array_map('intval', $contexts->get_contextids()));
        \local_asodeltolstaff\privacy\support_data::delete_user($first->id);
        $this->assertFalse($DB->record_exists('local_asstaff_case', ['id' => $firstid]));
        $this->assertTrue($DB->record_exists('local_asstaff_case', ['id' => $secondid]));
    }

    public function test_privacy_anonymises_responder_without_deleting_requester_case(): void {
        global $DB;
        $this->requester();
        $this->redirectMessages();
        $id = support::send('Synthetic case', 'Requester content');
        $this->setAdminUser();
        $adminid = get_admin()->id;
        support::respond($id, 'Responder content', true);
        \local_asodeltolstaff\privacy\support_data::delete_user($adminid);
        $case = $DB->get_record('local_asstaff_case', ['id' => $id]);
        $event = $DB->get_record('local_asstaff_caseevent', ['caseid' => $id]);
        $this->assertSame('Requester content', $case->body);
        $this->assertEquals(0, $case->recipientid);
        $this->assertEquals(0, $event->actorid);
        $this->assertSame('', $event->body);
    }
}
