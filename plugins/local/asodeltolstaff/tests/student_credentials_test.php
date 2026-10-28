<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff;

#[\PHPUnit\Framework\Attributes\CoversClass(student_credentials::class)]
final class student_credentials_test extends \advanced_testcase {
    public function test_municipality_policy_and_isolated_update_preserve_enrolment(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course();
        $group = $g->create_group(['courseid' => $course->id, 'name' => 'G01']);
        $student = $g->create_and_enrol($course, 'student', ['city' => '', 'firstname' => 'Carlos', 'lastname' => 'Paez']);
        groups_add_member($group->id, $student->id);

        $preflight = student_credentials::preflight_check($course->id);
        $this->assertSame(1, $preflight['studentcount']);

        $applied = student_credentials::apply_institutional_policy();
        $this->assertStringContainsString('city', $applied['hiddenuserfields']);
        $this->assertSame('locked', get_config('auth_manual', 'field_lock_city'));
        $this->assertFalse(has_capability('moodle/user:changeownpassword', \context_system::instance(), $student->id));

        // Doubtful municipality stays pending verification without inventing city value.
        $pending = student_credentials::update_municipality(
            (int) $student->id,
            '',
            student_credentials::MUNICIPALITY_PENDING
        );
        $this->assertSame(student_credentials::MUNICIPALITY_PENDING, $pending->municipalitystatus);
        $this->assertSame('', (string) $DB->get_field('user', 'city', ['id' => $student->id], MUST_EXIST));

        // Verified municipality updates city while keeping enrolments and group memberships intact.
        $verified = student_credentials::update_municipality(
            (int) $student->id,
            'Ibague',
            student_credentials::MUNICIPALITY_VERIFIED
        );
        $this->assertSame(student_credentials::MUNICIPALITY_VERIFIED, $verified->municipalitystatus);
        $this->assertSame('Ibague', (string) $DB->get_field('user', 'city', ['id' => $student->id], MUST_EXIST));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $student->id, '', true));
        $this->assertTrue(groups_is_member($group->id, $student->id));

        // Restore policy from backup (REQ-USR-05).
        $this->assertTrue(student_credentials::restore_policy_state());
    }

    public function test_individual_credentials_reject_deducible_patterns_and_track_delivery_without_storing_passwords(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $course = $g->create_course(['shortname' => 'AF8-2026']);
        $group = $g->create_group(['courseid' => $course->id, 'name' => 'G01']);
        $student = $g->create_and_enrol($course, 'student', ['username' => 'est001', 'firstname' => 'Maria', 'lastname' => 'Lopez']);
        groups_add_member($group->id, $student->id);

        $this->assertTrue(student_credentials::is_deducible_password('senaMaria5678$', $student));
        $this->assertTrue(student_credentials::is_deducible_password('Maria2026!@#', $student));

        $issued = student_credentials::issue_credential((int) $student->id, 'initial');
        $this->assertSame((int) $student->id, $issued['userid']);
        $this->assertFalse(student_credentials::is_deducible_password($issued['password'], $student));
        $this->assertNotFalse(authenticate_user_login($student->username, $issued['password']));

        $rawledger = (string) get_config('local_asodeltolstaff', 'cred_ledger_' . $student->id);
        $this->assertStringNotContainsString($issued['password'], $rawledger);

        $delivered = student_credentials::mark_delivered((int) $student->id, 'private_institutional');
        $this->assertSame(student_credentials::DELIVERY_DELIVERED, $delivered->deliverystatus);
        $this->assertGreaterThan(0, $delivered->timedelivered);

        $reset = student_credentials::issue_credential((int) $student->id, 'reset');
        $this->assertNotSame($issued['password'], $reset['password']);
        $this->assertFalse(authenticate_user_login($student->username, $issued['password']));
        $this->assertNotFalse(authenticate_user_login($student->username, $reset['password']));

        $rows = student_credentials::directory_rows($course->id);
        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('password', $rows[0]);
        $this->assertSame(student_credentials::DELIVERY_RESET, $rows[0]['deliverystatus']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $student->id, '', true));
    }

    public function test_students_cannot_access_credential_or_municipality_administration(): void {
        $this->resetAfterTest();
        $g = $this->getDataGenerator();
        $student = $g->create_user();
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        student_credentials::update_municipality((int) $student->id, 'Espinal');
    }
}
