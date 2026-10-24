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
 * Tests of where the AI assistant is put.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\assistant::class)]
final class assistant_test extends \advanced_testcase {
    /**
     * The tests need the block, which is a separate product.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!assistant::installed()) {
            $this->markTestSkipped('block_openaiagent is not installed.');
        }
        $this->resetAfterTest();
        // The site under test was installed with the block, so it already has its assistant.
        global $DB;
        foreach ($DB->get_records('block_instances', ['blockname' => assistant::BLOCK]) as $instance) {
            blocks_delete_instance($instance);
        }
        unset_config('assistantsetup', 'theme_rsmax');
        unset_config('assistantsiteblock', 'theme_rsmax');
    }

    /**
     * A site installed with the theme and the block has its assistant from the start.
     */
    public function test_installed_site(): void {
        global $DB;

        $this->resetAllData();
        $this->resetAfterTest();
        $block = assistant::site_block();
        $this->assertNotNull($block);
        $this->assertSame('site-index', $block->pagetypepattern);
        $this->assertContains(assistant::BLOCK, course_blocks::chosen());
        $this->assertSame(1, $DB->count_records('block_instances', ['blockname' => assistant::BLOCK]));
    }

    /**
     * The first time, new courses get the assistant and the home page gets the one of the site.
     */
    public function test_ensure(): void {
        global $DB;

        set_config('coursedefaultblocks', 'calendar_upcoming', 'theme_rsmax');
        $home = \core\context\course::instance(SITEID);
        $this->assertFalse($DB->record_exists('block_instances', ['blockname' => assistant::BLOCK]));

        assistant::ensure();

        $this->assertSame('calendar_upcoming,openaiagent', get_config('theme_rsmax', 'coursedefaultblocks'));
        $block = assistant::site_block();
        $this->assertNotNull($block);
        $this->assertEquals($home->id, $block->parentcontextid);
        $this->assertSame('site-index', $block->pagetypepattern);
        $this->assertEquals(0, $block->showinsubcontexts);
        // Open to visitors, which the block leaves off by default.
        $this->assertEquals(1, unserialize(base64_decode($block->configdata))->visitors);

        // A course created now has its own assistant; the one of the site does not reach it.
        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);
        $this->assertSame(1, $DB->count_records('block_instances', [
            'blockname' => assistant::BLOCK,
            'parentcontextid' => $context->id,
        ]));

        // Done once: an administrator who takes the block out of new courses is not overruled.
        set_config('coursedefaultblocks', 'calendar_upcoming', 'theme_rsmax');
        assistant::ensure();
        $this->assertSame('calendar_upcoming', get_config('theme_rsmax', 'coursedefaultblocks'));
        $this->assertSame(1, $DB->count_records('block_instances', [
            'blockname' => assistant::BLOCK,
            'parentcontextid' => $home->id,
        ]));
    }

    /**
     * An assistant the site already has on its home page is kept, with its own settings.
     */
    public function test_setup_site_block_keeps_existing(): void {
        global $DB;

        $home = \core\context\course::instance(SITEID);
        $page = new \moodle_page();
        $page->set_context($home);
        $page->set_pagetype('site-index');
        $page->set_url('/');
        $page->blocks->add_region('side-pre', false);
        $page->blocks->add_block(assistant::BLOCK, 'side-pre', 0, false, 'site-index');
        $existing = $DB->get_record('block_instances', ['blockname' => assistant::BLOCK, 'parentcontextid' => $home->id]);

        $block = assistant::setup_site_block();

        $this->assertEquals($existing->id, $block->id);
        $this->assertSame('', (string) $block->configdata);
        $this->assertSame(1, $DB->count_records('block_instances', ['blockname' => assistant::BLOCK]));

        // A block that was deleted is no longer the assistant of the site.
        blocks_delete_instance($block);
        $this->assertNull(assistant::site_block());
    }

    /**
     * The assistant of the site goes to the catalogue and the enrolment pages, not into courses.
     *
     * @param string $pagetype Type of the page.
     * @param bool $expected Whether the assistant of the site is brought to it.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('carried_to_provider')]
    public function test_carried_to(string $pagetype, bool $expected): void {
        $page = new \moodle_page();
        $page->set_pagetype($pagetype);
        $this->assertSame($expected, assistant::carried_to($page));
    }

    /**
     * The assistant of a course follows into its activities, but not into an exam under way.
     */
    public function test_course_assistant(): void {
        global $DB;

        assistant::ensure();
        $course = $this->getDataGenerator()->create_course();
        $block = assistant::course_block($course->id);
        $this->assertNotNull($block);
        $this->assertEquals(\core\context\course::instance($course->id)->id, $block->parentcontextid);

        $page = new \moodle_page();
        $page->set_course($course);
        $pagetypes = ['mod-forum-view' => true, 'grade-report-user-index' => true, 'mod-quiz-view' => true,
            'mod-quiz-attempt' => false, 'mod-quiz-summary' => false, 'enrol-index' => false];
        foreach ($pagetypes as $pagetype => $expected) {
            $page->set_pagetype($pagetype);
            $this->assertSame($expected, assistant::follows_to($page), $pagetype);
        }
        $home = new \moodle_page();
        $home->set_pagetype('site-index');
        $this->assertFalse(assistant::follows_to($home));

        // Hidden on the course page by somebody: it is not brought anywhere else.
        $DB->insert_record('block_positions', [
            'blockinstanceid' => $block->id,
            'contextid' => $block->parentcontextid,
            'pagetype' => 'course-view-topics',
            'subpage' => '',
            'visible' => 0,
            'region' => 'side-pre',
            'weight' => 0,
        ]);
        $this->assertNull(assistant::course_block($course->id));
    }

    /**
     * Page types and whether they get the assistant of the site.
     *
     * @return array
     */
    public static function carried_to_provider(): array {
        return [
            'catalogue' => ['course-index', true],
            'category' => ['course-index-category', true],
            'enrolment' => ['enrol-index', true],
            'course' => ['course-view-topics', false],
            'activity' => ['mod-forum-view', false],
            'dashboard' => ['my-index', false],
            'home, where the block already is' => ['site-index', false],
        ];
    }
}
