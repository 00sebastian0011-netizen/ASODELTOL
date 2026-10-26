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
 * Theme settings: colours, typography, header, footer, login, courses and advanced.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once(__DIR__ . '/lib.php');

    $settings = new theme_boost_admin_settingspage_tabs('themesettingrsmax', get_string('configtitle', 'theme_rsmax'));

    $name = fn(string $key): string => get_string($key, 'theme_rsmax');
    $desc = fn(string $key): string => get_string($key . '_desc', 'theme_rsmax');
    // Every setting changes the compiled CSS or cached markup, so they all reset the theme caches.
    $add = function (admin_settingpage $page, admin_setting $setting): void {
        $setting->set_updatedcallback('theme_reset_all_caches');
        $page->add($setting);
    };
    $colour = fn(string $key, string $default): admin_setting =>
        new admin_setting_configcolourpicker('theme_rsmax/' . $key, $name($key), $desc($key), $default);
    $checkbox = fn(string $key, int $default): admin_setting =>
        new admin_setting_configcheckbox('theme_rsmax/' . $key, $name($key), $desc($key), $default);
    $text = fn(string $key, string $type = PARAM_TEXT): admin_setting =>
        new admin_setting_configtext('theme_rsmax/' . $key, $name($key), $desc($key), '', $type);
    $select = function (string $key, array $options, string $default) use ($name, $desc): admin_setting {
        $choices = [];
        foreach ($options as $option) {
            $choices[$option] = get_string($key . ':' . $option, 'theme_rsmax');
        }
        return new admin_setting_configselect('theme_rsmax/' . $key, $name($key), $desc($key), $default, $choices);
    };
    $file = fn(string $key, array $types): admin_setting => new admin_setting_configstoredfile(
        'theme_rsmax/' . $key,
        $name($key),
        $desc($key),
        $key,
        0,
        ['maxfiles' => 1, 'accepted_types' => $types]
    );
    $heading = fn(string $key): admin_setting =>
        new admin_setting_heading('theme_rsmax/' . $key, $name($key), $desc($key));

    // Colours and shapes.
    $page = new admin_settingpage('theme_rsmax_brand', $name('brandsettings'));
    // Adds to a page the colour settings that belong to it.
    $addcolours = function (admin_settingpage $page, string $group) use ($add, $colour): void {
        foreach (THEME_RSMAX_COLOURS as $key => $spec) {
            if ($spec['page'] == $group) {
                $add($page, $colour($key, $spec['default']));
            }
        }
    };
    $add($page, $heading('paletteinfo'));
    $palettes = ['custom' => get_string('palette:custom', 'theme_rsmax')];
    foreach (array_keys(THEME_RSMAX_PALETTES) as $palette) {
        $palettes[$palette] = get_string('palette:' . $palette, 'theme_rsmax');
    }
    $setting = new admin_setting_configselect('theme_rsmax/palette', $name('palette'), $desc('palette'), 'custom', $palettes);
    $setting->set_updatedcallback('theme_rsmax_palette_chosen');
    $page->add($setting);
    $addcolours($page, 'brand');
    $add($page, $select('buttontext', ['auto', 'light', 'dark'], 'auto'));
    $widths = [];
    foreach (THEME_RSMAX_CONTENT_WIDTHS as $width) {
        $widths[$width] = $width . ' px';
    }
    $add($page, new admin_setting_configselect(
        'theme_rsmax/contentwidth',
        $name('contentwidth'),
        $desc('contentwidth'),
        1280,
        $widths
    ));
    $add($page, $select('roundness', array_keys(THEME_RSMAX_ROUNDNESS), 'soft'));
    $add($page, $checkbox('shadows', 1));
    // Light and dark modes are switched on in Boost, which is where the code that reads the setting lives. Say so
    // here, with the names Boost gives the tab and the setting, because nobody looks for it in another theme.
    $colourmodes = get_config('theme_boost', 'enablecolourmodes') ? 'colourmodeson' : 'colourmodesoff';
    $page->add(new admin_setting_heading(
        'theme_rsmax/colourmodes',
        $name('colourmodes'),
        get_string($colourmodes, 'theme_rsmax', [
            'url' => (new moodle_url('/admin/settings.php', ['section' => 'themesettingboost']))->out(),
            'tab' => get_string('experimentalsettings', 'theme_boost'),
            'setting' => get_string('enablecolourmodes', 'theme_boost'),
        ])
    ));
    $settings->add($page);

    // Site home and landing blocks.
    $page = new admin_settingpage('theme_rsmax_home', $name('homesettings'));
    $add($page, $checkbox('homehero', 1));
    $add($page, $text('homeherotitle'));
    $add($page, new admin_setting_configtextarea(
        'theme_rsmax/homeherotext',
        $name('homeherotext'),
        $desc('homeherotext'),
        '',
        PARAM_TEXT
    ));
    if (!array_filter(array_keys(core_component::get_plugin_list('block')), fn($block) => str_starts_with($block, 'pluginia_'))) {
        // Shown only where the landing blocks are not installed.
        $add($page, $heading('premiuminfo'));
    }
    $add($page, $heading('homeinfo'));
    $addcolours($page, 'home');
    $settings->add($page);

    // Typography.
    $page = new admin_settingpage('theme_rsmax_typography', $name('typographysettings'));
    $add($page, $select('fontbody', ['inter', 'system', 'noto', 'custom'], 'inter'));
    $add($page, $file('customfontbody', ['.woff2']));
    $add($page, $select('fontheadings', ['same', 'inter', 'system', 'noto', 'custom'], 'same'));
    $add($page, $file('customfontheadings', ['.woff2']));
    $sizes = [];
    foreach ([14, 15, 16, 17, 18] as $size) {
        $sizes[$size] = $size . ' px';
    }
    $add($page, new admin_setting_configselect('theme_rsmax/fontsize', $name('fontsize'), $desc('fontsize'), 16, $sizes));
    $add($page, new admin_setting_configselect(
        'theme_rsmax/headingweight',
        $name('headingweight'),
        $desc('headingweight'),
        700,
        [500 => '500', 600 => '600', 700 => '700', 800 => '800']
    ));
    $settings->add($page);

    // Header.
    $page = new admin_settingpage('theme_rsmax_header', $name('headersettings'));
    // The logos and the icon of the site are Moodle's own settings: shown here too, so the whole
    // look of the site is changed from one place. They are the same settings, not copies.
    $add($page, $heading('logoinfo'));
    $add($page, new admin_setting_configstoredfile(
        'core_admin/logo',
        new lang_string('logo', 'admin'),
        new lang_string('logo_desc', 'admin'),
        'logo',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.png']]
    ));
    $add($page, new admin_setting_configstoredfile(
        'core_admin/logocompact',
        new lang_string('logocompact', 'admin'),
        new lang_string('logocompact_desc', 'admin'),
        'logocompact',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.png']]
    ));
    $add($page, new admin_setting_configstoredfile(
        'core_admin/favicon',
        new lang_string('favicon', 'admin'),
        new lang_string('favicon_desc', 'admin'),
        'favicon',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['image']]
    ));
    $add($page, $select('navbarstyle', ['light', 'dark', 'brand'], 'light'));
    $heights = [];
    foreach ([28, 32, 36, 40, 48, 56] as $height) {
        $heights[$height] = $height . ' px';
    }
    $add($page, new admin_setting_configselect('theme_rsmax/logoheight', $name('logoheight'), $desc('logoheight'), 36, $heights));
    $add($page, $checkbox('logowhite', 1));
    $add($page, $checkbox('navbarsearch', 1));
    $add($page, $text('navbarbuttontext'));
    $add($page, $text('navbarbuttonurl', PARAM_RAW_TRIMMED));
    $add($page, $select('navbarbuttonaudience', ['visitors', 'everyone'], 'visitors'));
    $settings->add($page);

    // Footer.
    $page = new admin_settingpage('theme_rsmax_footer', $name('footersettings'));
    $add($page, $select('footerstyle', ['light', 'dark', 'brand'], 'dark'));
    $addcolours($page, 'footer');
    $add($page, new admin_setting_confightmleditor('theme_rsmax/footertext', $name('footertext'), $desc('footertext'), ''));
    for ($column = 1; $column <= THEME_RSMAX_FOOTER_COLUMNS; $column++) {
        $add($page, new admin_setting_configtext(
            'theme_rsmax/footercol' . $column . 'title',
            get_string('footercoltitle', 'theme_rsmax', $column),
            '',
            '',
            PARAM_TEXT
        ));
        $add($page, new admin_setting_configtextarea(
            'theme_rsmax/footercol' . $column . 'links',
            get_string('footercollinks', 'theme_rsmax', $column),
            $name('footercollinks_desc'),
            '',
            PARAM_RAW_TRIMMED
        ));
    }
    $add($page, $text('footercopyright'));
    foreach (THEME_RSMAX_SOCIAL as $network => $icon) {
        $add($page, new admin_setting_configtext(
            'theme_rsmax/social' . $network,
            $name('social' . $network),
            $name('social_desc'),
            '',
            PARAM_URL
        ));
    }
    $add($page, $checkbox('footerhelp', 1));
    $settings->add($page);

    // Login.
    $page = new admin_settingpage('theme_rsmax_login', $name('loginsettings'));
    $add($page, $file('loginbackgroundimage', ['web_image']));
    $settings->add($page);

    // Dashboard.
    $page = new admin_settingpage('theme_rsmax_dashboard', $name('dashboardsettings'));
    $add($page, $checkbox('dashboardsummary', 1));
    $settings->add($page);

    // Courses.
    $page = new admin_settingpage('theme_rsmax_course', $name('coursesettings'));
    $add($page, $checkbox('courseheader', 1));
    $add($page, $checkbox('courseutilitiesenabled', 1));
    $add($page, new admin_setting_configtextarea(
        'theme_rsmax/coursequicklinks',
        $name('coursequicklinks'),
        $desc('coursequicklinks'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $add($page, $checkbox('courseprogress', 1));
    $add($page, $checkbox('sectionprogress', 1));
    $widths = [];
    foreach (THEME_RSMAX_INDEX_WIDTHS as $width) {
        $widths[$width] = $width . ' px';
    }
    $add($page, new admin_setting_configselect('theme_rsmax/indexwidth', $name('indexwidth'), $desc('indexwidth'), 320, $widths));
    $add($page, $checkbox('navigationbarallformats', 1));
    $add($page, $heading('enrolinfo'));
    $add($page, $checkbox('enrollanding', 1));
    $add($page, $select('enrolcontents', \theme_rsmax\local\enrol_landing::CONTENTS, 'sections'));
    $add($page, $heading('blocksinfo'));
    $blocks = [];
    foreach (core_plugin_manager::instance()->get_enabled_plugins('block') as $block) {
        $blocks[$block] = get_string('pluginname', 'block_' . $block);
    }
    core_collator::asort($blocks);
    $setting = new admin_setting_configmultiselect(
        'theme_rsmax/coursedefaultblocks',
        $name('coursedefaultblocks'),
        $desc('coursedefaultblocks'),
        array_values(array_intersect(\theme_rsmax\local\course_blocks::DEFAULTS, array_keys($blocks))),
        $blocks
    );
    $setting->set_updatedcallback('theme_rsmax_apply_default_course_blocks');
    $page->add($setting);
    $addcolours($page, 'courses');
    $settings->add($page);

    // AI assistant.
    $page = new admin_settingpage('theme_rsmax_assistant', $name('assistantsettings'));
    $add($page, $heading('assistantinfo'));
    $add($page, $checkbox('assistantfloating', 1));
    $add($page, $text('assistantlabel'));
    $add($page, $checkbox('assistantcoursepages', 1));
    $add($page, $checkbox('assistantsitepages', 1));
    $settings->add($page);

    // Advanced.
    $page = new admin_settingpage('theme_rsmax_advanced', $name('advancedsettings'));
    $add($page, new admin_setting_scsscode('theme_rsmax/scsspre', $name('rawscsspre'), $desc('rawscsspre'), '', PARAM_RAW));
    $add($page, new admin_setting_scsscode('theme_rsmax/scss', $name('rawscss'), $desc('rawscss'), '', PARAM_RAW));
    $settings->add($page);
}
