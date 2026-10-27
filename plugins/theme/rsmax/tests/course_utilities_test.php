<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_rsmax\tests;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for administrator-approved, local-only course quick-link overrides.
 *
 * @covers \theme_rsmax\local\course_utilities
 */
final class course_utilities_test extends \advanced_testcase {
    public function test_rejects_external_and_unsafe_urls(): void {
        $this->assertTrue(\theme_rsmax\local\course_utilities::safe_local_path('/mod/assign/view.php?id=4'));
        foreach ([
            'https://elsewhere.invalid/', '//elsewhere.invalid/', 'javascript:alert(1)',
            '/../../admin/index.php', '/mod/url/view.php%2f%2fevil.invalid',
            "/mod/assign/view.php\nInjected: yes", '/mod\\assign/view.php',
        ] as $value) {
            $this->assertFalse(\theme_rsmax\local\course_utilities::safe_local_path($value), $value);
        }
    }

    public function test_overrides_apply_only_to_matching_course_and_allowed_keys(): void {
        $config = implode("\n", [
            '15|recordings|/mod/url/view.php?id=42',
            '15|announcements|/mod/forum/view.php?id=11',
            '17|live|/mod/bigbluebuttonbn/view.php?id=15',
            '15|badcategory|/admin/index.php',
            '15|materials|https://outside.invalid/',
            '15|support|//outside.invalid/',
        ]);
        $this->assertSame([
            'recordings' => '/mod/url/view.php?id=42',
            'announcements' => '/mod/forum/view.php?id=11',
        ], \theme_rsmax\local\course_utilities::configured_for_course($config, 15));
        $this->assertSame([
            'live' => '/mod/bigbluebuttonbn/view.php?id=15',
        ], \theme_rsmax\local\course_utilities::configured_for_course($config, 17));
    }

    public function test_visual_editor_rejects_cross_course_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $first = $generator->create_course();
        $second = $generator->create_course();
        $folderone = $generator->create_module('folder', ['course' => $first->id]);
        $foldertwo = $generator->create_module('folder', ['course' => $second->id]);
        $cmone = get_coursemodule_from_instance('folder', $folderone->id, $first->id);
        $cmtwo = get_coursemodule_from_instance('folder', $foldertwo->id, $second->id);

        $valid = \theme_rsmax\local\course_utilities::validate_choices($first, [
            'materials' => $cmone->id,
        ]);
        $this->assertSame((int) $cmone->id, $valid['materials']);

        $this->expectException(\invalid_parameter_exception::class);
        \theme_rsmax\local\course_utilities::validate_choices($first, [
            'materials' => $cmtwo->id,
        ]);
    }

    public function test_saved_selection_is_scoped_to_one_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $courseone = $this->getDataGenerator()->create_course();
        $coursetwo = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $courseone->id]);
        $cm = get_coursemodule_from_instance('folder', $folder->id, $courseone->id);

        \theme_rsmax\local\course_utilities::save($courseone, [
            'announcements' => -1,
            'materials' => $cm->id,
            'workshops' => 0,
        ]);
        $saved = \theme_rsmax\local\course_utilities::choices_for_course((int) $courseone->id);
        $this->assertSame(-1, $saved['announcements']);
        $this->assertSame((int) $cm->id, $saved['materials']);
        $this->assertSame(0, $saved['workshops']);
        $this->assertNull(\theme_rsmax\local\course_utilities::choices_for_course((int) $coursetwo->id));
    }

    public function test_student_cannot_save_course_link_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $this->assertFalse(\theme_rsmax\local\course_utilities::can_manage((int) $course->id));

        $this->expectException(\required_capability_exception::class);
        \theme_rsmax\local\course_utilities::save($course, ['materials' => 0]);
    }

    public function test_invalid_stored_selection_fails_closed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        set_config('coursequicklinks_' . $course->id, '{"materials":"/another/course"}', 'theme_rsmax');
        $choices = \theme_rsmax\local\course_utilities::choices_for_course((int) $course->id);
        $this->assertSame(-1, $choices['materials']);
        $this->assertSame(-1, $choices['announcements']);
    }

    public function test_student_sees_real_links_in_multiple_enrolled_courses(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $first = $generator->create_course();
        $second = $generator->create_course();
        $generator->create_module('folder', ['course' => $first->id]);
        $generator->create_module('folder', ['course' => $second->id]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $first->id, 'student');
        $generator->enrol_user($student->id, $second->id, 'student');
        $this->setUser($student);
        foreach ([$first, $second] as $course) {
            $context = \theme_rsmax\local\course_utilities::export($course);
            $matches = array_values(array_filter($context['items'],
                static fn(array $item): bool => $item['key'] === 'materials'));
            $this->assertCount(1, $matches);
            $this->assertNotEmpty($matches[0]['url']);
            $this->assertFalse($context['manageurl']);
        }
    }

    public function test_cau_uses_shared_staff_support_even_with_legacy_override(): void {
        $this->resetAfterTest();
        if (!class_exists('\\local_asodeltolstaff\\support')) {
            $this->markTestSkipped('ASODELTOL Staff is not installed in this test environment.');
        }
        $admins = get_admins();
        if (!$admins) {
            $this->markTestSkipped('No valid Moodle site administrator available for CAU test.');
        }

        $recipient = reset($admins);
        set_config('supportuserid', (int) $recipient->id, 'local_asodeltolstaff');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        if (!has_capability('moodle/site:sendmessage', \context_system::instance())) {
            $this->markTestSkipped('Learner cannot access Moodle messaging in this test environment.');
        }

        // Previously disabling a course link must never disable the shared institutional CAU.
        set_config('coursequicklinks_' . $course->id, '{"support":-1}', 'theme_rsmax');
        $data = \theme_rsmax\local\course_utilities::export($course);
        $support = array_values(array_filter($data['items'],
            static fn(array $item): bool => $item['key'] === 'support'));
        $this->assertCount(1, $support);
        $this->assertSame((new \moodle_url('/local/asodeltolstaff/support.php'))->out(false), $support[0]['url']);

        $this->expectException(\invalid_parameter_exception::class);
        \theme_rsmax\local\course_utilities::validate_choices($course, ['support' => -1]);
    }
}
