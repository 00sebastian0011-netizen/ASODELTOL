<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for local_asodeltolstaff\manager.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asodeltolstaff;

use advanced_testcase;
use context_course;

/**
 * Manager tests.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
final class manager_test extends advanced_testcase {
    /** Moodle resolves local capability labels without the local/ prefix. */
    public function test_capability_labels_exist(): void {
        $this->resetAfterTest();
        get_string_manager()->reset_caches(true);
        foreach (['beattended', 'export', 'manageown', 'validate', 'viewall', 'viewevidence', 'viewsessions', 'publishown'] as $capability) {
            $this->assertTrue(get_string_manager()->string_exists('asodeltolstaff:' . $capability, 'local_asodeltolstaff'));
        }
    }

    /** Export must reject users without the export capability. */
    public function test_export_denies_unprivileged_user(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        manager::get_report_rows((int) $course->id);
    }

    /** A viewer in one group cannot open a session from another group. */
    public function test_session_view_denies_other_group(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $own = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $other = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        groups_add_member($own->id, $student->id);
        $context = context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('local/asodeltolstaff:viewsessions', CAP_ALLOW, $roleid, $context->id, true);
        $this->setUser($student);
        accesslib_clear_all_caches_for_unit_testing();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('cannotmanagegroup', 'local_asodeltolstaff'));
        manager::require_session_view((object) ['courseid' => $course->id, 'groupid' => $other->id]);
    }

    /** Empty reports remain valid and contain no unrelated rows. */
    public function test_empty_course_report_has_no_rows(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $this->assertSame([], manager::get_report_rows((int) $course->id));
    }

    /**
     * Report export loads related records in a bounded number of database reads.
     *
     * @return void
     */
    public function test_report_rows_batches_related_record_queries(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $trainer = $this->getDataGenerator()->create_user();
        $students = [];
        for ($index = 0; $index < 20; $index++) {
            $students[] = $this->getDataGenerator()->create_user();
        }

        $sessionids = [];
        $now = time();
        foreach ($students as $index => $student) {
            $sessionids[] = $DB->insert_record('local_asstaff_session', (object) [
                'courseid' => $course->id,
                'sectionnum' => 0,
                'groupid' => $group->id,
                'trainerid' => $trainer->id,
                'sessioncode' => 'BATCH-' . $index,
                'scheduledstart' => $now + ($index * HOURSECS),
                'scheduledend' => $now + (($index + 1) * HOURSECS),
                'reportedstart' => null,
                'reportedend' => null,
                'validatedminutes' => null,
                'validationstatus' => manager::VALIDATION_PENDING,
                'evidenceref' => '',
                'createdby' => $trainer->id,
                'validatedby' => null,
                'timecreated' => $now,
                'timemodified' => $now,
                'timevalidated' => null,
            ]);
        }

        foreach ($sessionids as $index => $sessionid) {
            $DB->insert_record('local_asstaff_attendance', (object) [
                'sessionid' => $sessionid,
                'studentid' => $students[$index]->id,
                'status' => 'reported_present',
                'reportedby' => $trainer->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $DB->insert_record('local_asstaff_attendance', (object) [
            'sessionid' => $sessionids[0],
            'studentid' => $students[1]->id,
            'status' => 'reported_absent',
            'reportedby' => $trainer->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $context = context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        foreach (['export', 'viewsessions', 'viewall'] as $capability) {
            assign_capability(
                'local/asodeltolstaff:' . $capability,
                CAP_ALLOW,
                $roleid,
                $context->id,
                true
            );
        }
        role_assign($roleid, $trainer->id, $context->id);
        $this->setUser($trainer);
        accesslib_clear_all_caches_for_unit_testing();

        $readsbefore = $DB->perf_get_reads();
        $rows = manager::get_report_rows((int) $course->id);
        $reads = $DB->perf_get_reads() - $readsbefore;

        $this->assertCount(21, $rows);
        $this->assertSame('BATCH-0', $rows[0]['sessioncode']);
        $this->assertSame(gmdate('Y-m-d\TH:i:s\Z', $now), $rows[0]['scheduledstart']);
        $this->assertSame(gmdate('Y-m-d\TH:i:s\Z', $now + HOURSECS), $rows[0]['scheduledend']);
        $this->assertSame('', $rows[0]['reportedstart']);
        $this->assertSame('', $rows[0]['reportedend']);
        $this->assertSame((string) ($course->idnumber ?: $course->shortname), $rows[0]['trainingactioncode']);
        $this->assertSame((string) $course->fullname, $rows[0]['trainingaction']);
        $this->assertNotSame('', $rows[0]['trainer']);
        $this->assertNotSame('', $rows[0]['student']);
        $this->assertSame('reported_present', $rows[0]['attendance']);
        $this->assertSame('BATCH-0', $rows[1]['sessioncode']);
        $this->assertNotSame('', $rows[1]['student']);
        $this->assertSame('reported_absent', $rows[1]['attendance']);
        $this->assertLessThanOrEqual(
            24,
            $reads,
            'Related report rows should be fetched in batches rather than with per-row queries.'
        );
    }
}
