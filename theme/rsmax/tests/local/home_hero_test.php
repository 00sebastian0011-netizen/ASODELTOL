<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_rsmax\local;

/**
 * Tests of the opening of the site home.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\home_hero::class)]
final class home_hero_test extends \advanced_testcase {
    /**
     * Without settings the opening carries the name of the site; a visitor is sent to log in.
     */
    public function test_export_for_a_visitor(): void {
        global $SITE;
        $this->resetAfterTest();
        set_config('forcelogin', 0);

        $data = home_hero::export(new \stdClass());

        $this->assertSame(format_string($SITE->fullname), $data['title']);
        $this->assertSame('', $data['text']);
        $this->assertTrue($data['cansearch']);
        $this->assertStringContainsString('/login/', $data['secondurl']);
        $this->assertStringEndsWith('/course/index.php', $data['coursesurl']);
    }

    /**
     * What is written in the settings is shown escaped; somebody logged in is sent to the dashboard.
     */
    public function test_export_with_settings(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $data = home_hero::export((object) [
            'homeherotitle' => 'Learn <script>alert(1)</script>',
            'homeherotext' => "At your pace\n<b>always</b>",
        ]);

        $this->assertStringNotContainsString('<script', $data['title']);
        $this->assertStringContainsString('Learn', $data['title']);
        $this->assertStringNotContainsString('<b>', $data['text']);
        $this->assertStringContainsString('At your pace', $data['text']);
        $this->assertStringEndsWith('/my/', $data['secondurl']);
    }

    /**
     * A site that asks everybody to log in does not offer its search to a visitor.
     */
    public function test_search_follows_forcelogin(): void {
        $this->resetAfterTest();
        set_config('forcelogin', 1);
        $this->assertFalse(home_hero::export(new \stdClass())['cansearch']);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(home_hero::export(new \stdClass())['cansearch']);
    }
}
