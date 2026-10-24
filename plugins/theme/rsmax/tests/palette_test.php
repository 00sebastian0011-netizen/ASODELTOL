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

namespace theme_rsmax;

/**
 * Tests for the ready-made palettes of the theme settings.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('theme_rsmax_apply_palette')]
final class palette_test extends \advanced_testcase {
    /**
     * Loads the library of the theme, where the palettes are.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/rsmax/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * The names of the palettes.
     *
     * @return array[]
     */
    public static function palette_provider(): array {
        global $CFG;
        require_once($CFG->dirroot . '/theme/rsmax/lib.php');
        $palettes = [];
        foreach (array_keys(THEME_RSMAX_PALETTES) as $palette) {
            $palettes[$palette] = [$palette];
        }
        return $palettes;
    }

    /**
     * Contrast of a colour with white, as WCAG measures it.
     *
     * @param string $colour Hexadecimal colour, such as #1e3a8a.
     * @return float
     */
    private static function contrast_with_white(string $colour): float {
        $luminance = 0;
        foreach ([0.2126, 0.7152, 0.0722] as $index => $weight) {
            $channel = hexdec(substr($colour, 1 + $index * 2, 2)) / 255;
            $luminance += $weight * ($channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4);
        }
        return 1.05 / ($luminance + 0.05);
    }

    /**
     * A palette only names settings the theme has, with values the theme accepts.
     *
     * @param string $palette Name of the palette.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('palette_provider')]
    public function test_palette_is_well_formed(string $palette): void {
        $values = THEME_RSMAX_PALETTES[$palette];
        foreach (['brandcolor', 'accentcolor', 'inkcolor', 'surfacecolor'] as $main) {
            $this->assertArrayHasKey($main, $values);
        }
        foreach ($values as $setting => $value) {
            if (in_array($setting, ['navbarstyle', 'footerstyle'])) {
                $this->assertContains($value, ['light', 'dark', 'brand']);
                continue;
            }
            $this->assertArrayHasKey($setting, THEME_RSMAX_COLOURS);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $value);
        }
        $this->assertTrue(get_string_manager()->string_exists('palette:' . $palette, 'theme_rsmax'));
        // The headers and the hero carry white text.
        foreach (['stagecolor', 'herocolor', 'indexcolor', 'footerbgcolor'] as $dark) {
            $colour = $values[$dark] ?? $values['inkcolor'];
            $this->assertGreaterThanOrEqual(4.5, self::contrast_with_white($colour), "$palette $dark");
        }
    }

    /**
     * Choosing a palette writes its values and leaves nothing of the palette before it.
     *
     * @param string $palette Name of the palette.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('palette_provider')]
    public function test_apply_palette(string $palette): void {
        $this->resetAfterTest();
        // What somebody, or another palette, left behind.
        set_config('stagecolor', '#123456', 'theme_rsmax');
        set_config('buttoncolor', '#654321', 'theme_rsmax');
        set_config('cardcolor', '#eeeeee', 'theme_rsmax');
        set_config('navbarstyle', 'brand', 'theme_rsmax');
        set_config('footerstyle', 'brand', 'theme_rsmax');
        set_config('goodcolor', '#111111', 'theme_rsmax');

        set_config('palette', $palette, 'theme_rsmax');
        theme_rsmax_apply_palette();

        $values = THEME_RSMAX_PALETTES[$palette];
        $expected = $values + ['bandcolor2' => $values['surfacecolor']] + THEME_RSMAX_PALETTE_DEFAULTS;
        foreach ($expected as $setting => $value) {
            $this->assertSame($value, get_config('theme_rsmax', $setting), $setting);
        }
        // The colours of right and wrong answers are not part of a palette.
        $this->assertSame('#111111', get_config('theme_rsmax', 'goodcolor'));
    }

    /**
     * "Custom", or a name that is not a palette, changes nothing.
     */
    public function test_custom_changes_nothing(): void {
        $this->resetAfterTest();
        set_config('brandcolor', '#123456', 'theme_rsmax');
        set_config('navbarstyle', 'dark', 'theme_rsmax');
        foreach (['custom', 'nosuchpalette'] as $palette) {
            set_config('palette', $palette, 'theme_rsmax');
            theme_rsmax_apply_palette();
            $this->assertSame('#123456', get_config('theme_rsmax', 'brandcolor'));
            $this->assertSame('dark', get_config('theme_rsmax', 'navbarstyle'));
        }
    }
}
