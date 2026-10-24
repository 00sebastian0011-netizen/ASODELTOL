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
 * Tests for the addresses of embedded videos and for the colours of the custom scheme.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\local\video::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\block\landing_block::class)]
final class video_test extends \basic_testcase {
    /**
     * Addresses and what they become.
     *
     * @return array[]
     */
    public static function address_provider(): array {
        $youtube = 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ';
        return [
            'YouTube watch page' => ['https://www.youtube.com/watch?v=aqz-KE-bpKQ', $youtube],
            'YouTube with other parameters first' => ['https://www.youtube.com/watch?t=5&v=aqz-KE-bpKQ', $youtube],
            'YouTube short address' => ['https://youtu.be/aqz-KE-bpKQ?si=x', $youtube],
            'YouTube shorts' => ['https://youtube.com/shorts/aqz-KE-bpKQ', $youtube],
            'Vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'Vimeo player' => ['https://player.vimeo.com/video/76979871?h=1', 'https://player.vimeo.com/video/76979871'],
            'Video file' => ['https://example.com/media/intro.mp4', 'https://example.com/media/intro.mp4'],
            'Another site' => ['https://example.com/watch?v=aqz-KE-bpKQ', null],
            'Not a web address' => ['javascript:alert(1)', null],
            'A site path' => ['/course/view.php?id=2', null],
            'Empty' => ['', null],
        ];
    }

    /**
     * Only YouTube, Vimeo and video files are embedded, always through their player address.
     *
     * @param string $url Address written in the settings.
     * @param string|null $expected Address embedded, null when it is not embedded.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('address_provider')]
    public function test_embed(string $url, ?string $expected): void {
        $embed = video::embed($url);
        $this->assertSame($expected, $embed['src'] ?? null);
        if ($embed) {
            $this->assertNotSame($embed['isframe'], $embed['isfile']);
        }
    }

    /**
     * Custom colours must be plain hexadecimal colours.
     */
    public function test_clean_colour(): void {
        global $CFG;
        // Block classes are not autoloaded: Moodle loads their base class when it builds a page.
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        $class = \theme_rsmax\block\landing_block::class;

        $this->assertSame('#00a8d6', $class::clean_colour(' #00A8D6 '));
        $this->assertSame('#fff', $class::clean_colour('#fff'));
        $this->assertSame('', $class::clean_colour('red'));
        $this->assertSame('', $class::clean_colour('#00a8d6;background:url(x)'));
        $this->assertSame('', $class::clean_colour('#12345'));
    }
}
