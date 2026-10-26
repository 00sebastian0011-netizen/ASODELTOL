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
}
