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

/**
 * Theme configuration.
 *
 * RSMAX is a child of Boost. It only replaces the page shell used by the drawers layout
 * (navigation bar, block regions, course progress and footer); every other layout, template
 * and renderer comes from Boost.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'rsmax';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->usefallback = true;
$THEME->scss = function ($theme) {
    return theme_rsmax_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_rsmax_get_pre_scss';
$THEME->extrascsscallback = 'theme_rsmax_get_extra_scss';
$THEME->precompiledcsscallback = 'theme_boost_get_precompiled_css';
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->enable_dock = false;
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = ['notitle' => true];

// Pages built from blocks (site home, dashboard, courses) get the landing regions as well.
$standard = ['side-pre'];
$landing = array_merge($standard, THEME_RSMAX_LANDING_REGIONS);

$layouts = [
    'base' => [],
    'standard' => $standard,
    'course' => $landing,
    'coursecategory' => $landing,
    'incourse' => $standard,
    'frontpage' => $landing,
    'admin' => $standard,
    'mycourses' => $landing,
    'mydashboard' => $landing,
    'mypublic' => $standard,
    'report' => $standard,
];
$options = [
    'course' => ['langmenu' => true],
    'frontpage' => ['nonavbar' => true],
    'mycourses' => ['nonavbar' => true],
    'mydashboard' => ['nonavbar' => true, 'langmenu' => true],
];

$THEME->layouts = [];
foreach ($layouts as $name => $regions) {
    $THEME->layouts[$name] = ['file' => 'drawers.php', 'regions' => $regions];
    if ($regions) {
        $THEME->layouts[$name]['defaultregion'] = 'side-pre';
    }
    if (isset($options[$name])) {
        $THEME->layouts[$name]['options'] = $options[$name];
    }
}
// The course page keeps one more region: the blocks of the page where people join the course.
// It is only drawn on the course page while editing; theme_rsmax\local\enrol_landing shows it there.
$THEME->layouts['course']['regions'][] = 'enrol-page';

$THEME->sheets = array_merge((array)($THEME->sheets ?? []), ["custom-login", "institutional-notices"]);
