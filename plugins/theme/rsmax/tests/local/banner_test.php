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
 * Tests of the banners of a course and of its sections.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\banner::class)]
final class banner_test extends \advanced_testcase {
    /**
     * Stores a picture as a banner.
     *
     * @param \stdClass $course The course.
     * @param string $area File area.
     * @param int $itemid Item id.
     * @param string $name File name.
     */
    private function add_banner(\stdClass $course, string $area, int $itemid, string $name): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'theme_rsmax',
            'filearea' => $area,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $name,
        ], 'picture');
        banner::purge($course->id);
    }

    /**
     * A page takes the banner of its section, and the one of the course only where the course says.
     */
    public function test_for_page(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $modinfo = get_fast_modinfo($course);
        $first = (int) $modinfo->get_section_info(1)->id;
        $second = (int) $modinfo->get_section_info(2)->id;

        $this->assertSame('', banner::for_page($course->id, 0, true));

        $this->add_banner($course, banner::AREA_COURSE, 0, 'course.png');
        $this->add_banner($course, banner::AREA_SECTION, $first, 'first.png');

        // The course page always shows the banner of the course.
        $this->assertStringContainsString('/coursebanner/0/', banner::for_page($course->id, 0, true));
        $this->assertStringEndsWith('/course.png', banner::for_page($course->id, 0, true));
        // A section with a banner shows its own; one without shows none until the course shares its banner.
        $this->assertStringEndsWith('/first.png', banner::for_page($course->id, $first));
        $this->assertSame('', banner::for_page($course->id, $second));
        $this->assertSame('', banner::for_page($course->id));

        banner::set_scope($course->id, banner::SCOPE_ALL);
        $this->assertStringEndsWith('/course.png', banner::for_page($course->id, $second));
        $this->assertStringEndsWith('/course.png', banner::for_page($course->id));
        $this->assertStringEndsWith('/first.png', banner::for_page($course->id, $first));

        // Anything that is not "everywhere" is the course page only, and the row is updated, not repeated.
        banner::set_scope($course->id, 7);
        $this->assertSame('', banner::for_page($course->id, $second));
        $this->assertSame(banner::SCOPE_HOME, banner::get($course->id)['scope']);
    }

    /**
     * Deleting a section removes its banner; deleting the course removes its options.
     */
    public function test_cleaning(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $section = get_fast_modinfo($course)->get_section_info(2);
        $this->add_banner($course, banner::AREA_SECTION, (int) $section->id, 'second.png');
        banner::set_scope($course->id, banner::SCOPE_ALL);
        $this->assertCount(1, banner::get($course->id)['sections']);

        course_delete_section($course, $section);
        $this->assertSame([], banner::get($course->id)['sections']);

        $this->assertTrue($DB->record_exists('theme_rsmax_course', ['courseid' => $course->id]));
        delete_course($course, false);
        $this->assertFalse($DB->record_exists('theme_rsmax_course', ['courseid' => $course->id]));
    }

    /**
     * A backup of the course carries its banners, and a restore puts each one on the new section.
     */
    public function test_backup_and_restore(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $second = (int) get_fast_modinfo($course)->get_section_info(2)->id;
        $this->add_banner($course, banner::AREA_COURSE, 0, 'course.png');
        $this->add_banner($course, banner::AREA_SECTION, $second, 'second.png');
        banner::set_scope($course->id, banner::SCOPE_ALL);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $copy = banner::get($newid);
        $newsecond = (int) get_fast_modinfo($newid)->get_section_info(2)->id;
        $this->assertNotEquals($second, $newsecond);
        $this->assertSame(banner::SCOPE_ALL, $copy['scope']);
        $this->assertStringEndsWith('/course.png', $copy['course']);
        $this->assertSame([$newsecond], array_keys($copy['sections']));
        $this->assertStringEndsWith('/second.png', $copy['sections'][$newsecond]);
    }
}
