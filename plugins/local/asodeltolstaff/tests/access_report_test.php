<?php
namespace local_asodeltolstaff;

/** Access events are reported independently, never converted into time worked. */
#[\PHPUnit\Framework\Attributes\CoversClass(access_report::class)]
final class access_report_test extends \advanced_testcase {
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        get_string_manager()->reset_caches(true);
        $g = $this->getDataGenerator();
        $course = $g->create_course(['startdate' => 1700000000, 'enddate' => 1800000000]);
        $a = $g->create_group(['courseid' => $course->id]);
        $b = $g->create_group(['courseid' => $course->id]);
        $first = $g->create_and_enrol($course, 'editingteacher', ['firstname' => 'Synthetic', 'lastname' => 'A']);
        $second = $g->create_and_enrol($course, 'editingteacher', ['firstname' => 'Synthetic', 'lastname' => 'B']);
        groups_add_member($a->id, $first->id);
        groups_add_member($b->id, $second->id);
        $context = \context_system::instance();
        foreach ([[$first, 'user_loggedin'], [$first, 'user_loggedin'], [$first, 'user_loggedout'], [$second, 'user_loggedin']] as $index => [$user, $event]) {
            $DB->insert_record('logstore_standard_log', (object) [
                'eventname' => '\\core\\event\\' . $event, 'component' => 'core',
                'action' => $event === 'user_loggedin' ? 'loggedin' : 'loggedout', 'target' => 'user',
                'objecttable' => 'user', 'objectid' => $user->id, 'crud' => 'r', 'edulevel' => 0,
                'contextid' => $context->id, 'contextlevel' => CONTEXT_SYSTEM, 'contextinstanceid' => 0,
                'userid' => $user->id, 'courseid' => 0, 'timecreated' => 1700000100 + $index,
            ]);
        }
        office_hours::create($course->id, $a->id, $first->id, 1700000200, 1700000200 + HOURSECS, '', 'https://evidence.example.invalid/action');
        return [$course, $a, $b, $first, $second];
    }

    public function test_actual_sources_and_missing_logout_remain_distinct(): void {
        [$course] = $this->fixture();
        $rows = access_report::rows($course->id);
        $this->assertCount(6, $rows);
        $this->assertSame('2023-11-14T22:13:20Z', $rows[0]['coursestartutc']);
        $logins = array_values(array_filter($rows, static fn(array $r): bool => $r['rowtype'] === 'site_login'));
        $this->assertCount(3, $logins);
        foreach ($logins as $row) {
            $this->assertSame('', $row['logoututc']);
            $this->assertNotSame('', $row['loginutc']);
            $this->assertStringStartsWith('logstore_standard_log:', $row['sourcerecord']);
        }
        $slots = array_values(array_filter($rows, static fn(array $r): bool => $r['rowtype'] === 'consultation_slot'));
        $this->assertCount(1, $slots);
        $this->assertSame('2023-11-14T22:16:40Z', $slots[0]['officehoursstartutc']);
    }

    public function test_trainer_never_exports_another_trainers_global_access(): void {
        [$course, $a, $b, $first] = $this->fixture();
        $this->setUser($first);
        $rows = access_report::rows($course->id);
        $this->assertCount(5, $rows);
        foreach ($rows as $row) {
            $this->assertNotSame('Synthetic B', $row['trainer']);
        }
    }

    public function test_course_export_grant_does_not_grant_global_log_access(): void {
        [$course] = $this->fixture();
        $auditor = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $this->setUser($auditor);
        $this->expectException(\required_capability_exception::class);
        access_report::rows($course->id);
    }
}
