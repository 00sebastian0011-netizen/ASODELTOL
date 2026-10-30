<?php
namespace local_asodeltolstaff;

#[\PHPUnit\Framework\Attributes\CoversClass(identity_registry::class)]
final class identity_registry_test extends \advanced_testcase {
    public function test_preflight_is_non_pii_and_duplicate_is_rejected(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        identity_registry::bind($a->id, 'CC', ' 1.234-567 ', 'create');
        $check = identity_registry::preflight('cc', '1234567');
        $this->assertFalse($check['available']);
        $this->assertArrayNotHasKey('document', $check);
        $this->assertStringNotContainsString('1234567', json_encode($check));
        $this->expectException(\invalid_parameter_exception::class);
        identity_registry::bind($b->id, 'CC', '1234567', 'import');
    }

    public function test_import_preflight_rejects_in_file_duplicate_without_writes(): void {
        global $DB;
        $this->resetAfterTest();
        $result = identity_registry::import_preflight([
            ['doctype' => 'CE', 'document' => 'ABC-99'],
            ['doctype' => 'ce', 'document' => 'abc99'],
        ]);
        $this->assertTrue($result[0]['available']);
        $this->assertFalse($result[1]['available']);
        $this->assertSame(0, $DB->count_records('local_asstaff_identity'));
    }

    public function test_assignment_rolls_back_when_document_is_taken(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        identity_registry::bind($a->id, 'TI', '9001', 'create');
        try {
            identity_registry::assign_group($b->id, $group->id, 'TI', '9001');
            $this->fail('Duplicate assignment was accepted');
        } catch (\invalid_parameter_exception $e) {
            $this->assertFalse($DB->record_exists('groups_members', ['groupid' => $group->id, 'userid' => $b->id]));
        }
    }

    public function test_import_preflight_runs_before_any_account_write(): void {
        global $DB;
        $this->resetAfterTest();
        $rows = [
            ['user' => ['username'=>'synthetic-1', 'firstname'=>'A', 'lastname'=>'A', 'email'=>'a@example.invalid'], 'doctype'=>'CC', 'document'=>'11'],
            ['user' => ['username'=>'synthetic-2', 'firstname'=>'B', 'lastname'=>'B', 'email'=>'b@example.invalid'], 'doctype'=>'CC', 'document'=>'11'],
        ];
        try { identity_registry::import_users($rows); $this->fail('Duplicate import accepted'); }
        catch (\invalid_parameter_exception $e) { $this->assertFalse($DB->record_exists('user', ['username'=>'synthetic-1'])); }
    }
}
