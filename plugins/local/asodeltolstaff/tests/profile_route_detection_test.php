<?php
// This file is part of Moodle - http://moodle.org/
namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression for the Moodle bootstrap route used by after_require_login.
 *
 * @covers \local_asodeltolstaff\profile_privacy
 */
final class profile_route_detection_test extends \basic_testcase {
    public function test_profile_path_detection_does_not_require_page_url(): void {
        global $SCRIPT;

        $previousscript = $SCRIPT;
        $method = new \ReflectionMethod(profile_privacy::class, 'is_profile_script');
        try {
            foreach ([
                '/user/view.php',
                '/user/profile.php',
                '/user/edit.php',
                '/user/editadvanced.php',
                '/public/user/profile.php',
            ] as $script) {
                $SCRIPT = $script;
                $this->assertTrue($method->invoke(null), $script . ' must be guarded');
            }
            $SCRIPT = '/user/index.php';
            $this->assertFalse($method->invoke(null), 'Unrelated routes must not be intercepted');
            $SCRIPT = null;
            $this->assertFalse($method->invoke(null), 'Unknown routes must not trigger profile handling');
        } finally {
            $SCRIPT = $previousscript;
        }
    }
}
