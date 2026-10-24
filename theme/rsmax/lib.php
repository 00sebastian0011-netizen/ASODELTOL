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
 * Theme callbacks.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Block regions that stack full-width content around the main region. */
const THEME_RSMAX_LANDING_REGIONS = ['fullwidth-top', 'above-content', 'below-content', 'fullwidth-bottom'];

/** Page types inside a course that keep the whole width of the window: each is one large table. */
const THEME_RSMAX_WIDE_PAGES = [
    'mod-assign-grading',
    'mod-assign-grader',
    'mod-quiz-edit',
    'mod-quiz-report',
    'mod-workshop-allocation',
];

/** File areas owned by the theme settings. */
const THEME_RSMAX_FILEAREAS = ['loginbackgroundimage', 'customfontbody', 'customfontheadings'];

/**
 * Colour settings: the settings page each one is on, the SCSS variable it sets and its default.
 * An empty default means the colour is derived from the others until one is chosen.
 */
const THEME_RSMAX_COLOURS = [
    'brandcolor' => ['page' => 'brand', 'variable' => 'primary', 'default' => '#00a8d6'],
    'accentcolor' => ['page' => 'brand', 'variable' => 'rsmax-accent', 'default' => '#2dd4bf'],
    'inkcolor' => ['page' => 'brand', 'variable' => 'rsmax-ink', 'default' => '#2b2624'],
    'surfacecolor' => ['page' => 'brand', 'variable' => 'rsmax-surface', 'default' => '#f6f1ea'],
    'linkcolor' => ['page' => 'brand', 'variable' => 'link-color', 'default' => ''],
    'buttoncolor' => ['page' => 'brand', 'variable' => 'rsmax-button', 'default' => ''],
    'bandcolor1' => ['page' => 'home', 'variable' => 'rsmax-band-1', 'default' => '#ffffff'],
    'bandcolor2' => ['page' => 'home', 'variable' => 'rsmax-band-2', 'default' => '#f6f1ea'],
    'cardcolor' => ['page' => 'home', 'variable' => 'rsmax-card', 'default' => '#ffffff'],
    'herocolor' => ['page' => 'home', 'variable' => 'rsmax-hero-bg', 'default' => ''],
    'heroglowcolor' => ['page' => 'home', 'variable' => 'rsmax-hero-glow', 'default' => ''],
    'stagecolor' => ['page' => 'courses', 'variable' => 'rsmax-stage-bg', 'default' => ''],
    'papercolor' => ['page' => 'courses', 'variable' => 'rsmax-paper', 'default' => ''],
    'indexcolor' => ['page' => 'courses', 'variable' => 'rsmax-index-bg', 'default' => ''],
    'goodcolor' => ['page' => 'courses', 'variable' => 'rsmax-good', 'default' => '#4f8a63'],
    'partialcolor' => ['page' => 'courses', 'variable' => 'rsmax-partial', 'default' => '#c98a1f'],
    'badcolor' => ['page' => 'courses', 'variable' => 'rsmax-bad', 'default' => '#c4573f'],
    'footerbgcolor' => ['page' => 'footer', 'variable' => 'rsmax-footer-bg', 'default' => ''],
    'footertextcolor' => ['page' => 'footer', 'variable' => 'rsmax-footer-text', 'default' => ''],
];

/** Corner styles: radius of small things, of boxes, of large boxes and of buttons. */
const THEME_RSMAX_ROUNDNESS = [
    'square' => ['0', '0', '0', '0'],
    'soft' => ['.375rem', '.5rem', '.75rem', '.5rem'],
    'round' => ['.5rem', '.875rem', '1.25rem', '.875rem'],
    'pill' => ['.5rem', '.875rem', '1.25rem', '50rem'],
];

/** Font stacks offered in the settings. "noto" is the Boost default, "custom" an uploaded font. */
const THEME_RSMAX_FONTS = [
    'inter' => '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
    'system' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", Arial, sans-serif',
    'noto' => '"Noto Sans", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
];

/** Social networks of the footer: setting suffix => Font Awesome brand icon. */
const THEME_RSMAX_SOCIAL = [
    'facebook' => 'fa-facebook-f',
    'xtwitter' => 'fa-x-twitter',
    'linkedin' => 'fa-linkedin-in',
    'youtube' => 'fa-youtube',
    'instagram' => 'fa-instagram',
    'tiktok' => 'fa-tiktok',
];

/** Widths, in pixels, the content column can have on large screens. 830 is the Boost default. */
const THEME_RSMAX_CONTENT_WIDTHS = [830, 960, 1120, 1280];

/** Widths, in pixels, of the course index on large screens. 285 is the Boost default. */
const THEME_RSMAX_INDEX_WIDTHS = [285, 320, 360];

/**
 * Colours that changed when the theme moved to a warm palette: setting => the default it had.
 * Sites that never chose another colour follow the new default.
 */
const THEME_RSMAX_OLD_COLOURS = ['inkcolor' => '#0a0a0a', 'surfacecolor' => '#f5f5f5', 'bandcolor2' => '#f3f6f9'];

/**
 * Ready-made palettes. Each one writes the four colours the whole site is drawn from (brand,
 * accent, dark and background) and the settings that give the site its character: the colour of
 * the course and activity headers, of the home hero and of the course index, and the style of the
 * menu and of the footer. What a palette does not name goes back to its default, so nothing of the
 * palette before it is left behind. "custom" leaves everything as it is.
 *
 * The headers carry white text: their colour must be deep enough for it (4.5:1 or more).
 */
const THEME_RSMAX_PALETTES = [
    'warm' => [
        'brandcolor' => '#00a8d6', 'accentcolor' => '#2dd4bf', 'inkcolor' => '#2b2624', 'surfacecolor' => '#f6f1ea',
    ],
    'slate' => [
        'brandcolor' => '#2563eb', 'accentcolor' => '#38bdf8', 'inkcolor' => '#1e293b', 'surfacecolor' => '#f1f5f9',
        'stagecolor' => '#1e3a8a', 'herocolor' => '#172554', 'indexcolor' => '#1e293b', 'footerbgcolor' => '#0f172a',
    ],
    'forest' => [
        'brandcolor' => '#2f7d5b', 'accentcolor' => '#e0b34a', 'inkcolor' => '#1f2a24', 'surfacecolor' => '#f3f1e7',
        'stagecolor' => '#235741', 'herocolor' => '#1b4332', 'heroglowcolor' => '#e0b34a', 'indexcolor' => '#1f2a24',
        'footerbgcolor' => '#1b4332',
    ],
    'ocean' => [
        'brandcolor' => '#0f766e', 'accentcolor' => '#fb923c', 'inkcolor' => '#134e4a', 'surfacecolor' => '#f0f7f6',
        'stagecolor' => '#115e59', 'herocolor' => '#134e4a', 'heroglowcolor' => '#fb923c', 'indexcolor' => '#134e4a',
        'navbarstyle' => 'brand',
    ],
    'navy' => [
        'brandcolor' => '#c2410c', 'accentcolor' => '#f5b94a', 'inkcolor' => '#172554', 'surfacecolor' => '#f5f3ee',
        'stagecolor' => '#1e3a8a', 'herocolor' => '#172554', 'heroglowcolor' => '#c2410c', 'indexcolor' => '#172554',
        'navbarstyle' => 'dark',
    ],
    'plum' => [
        'brandcolor' => '#9d3f8f', 'accentcolor' => '#f0a6ca', 'inkcolor' => '#2a1f2d', 'surfacecolor' => '#f7f1f4',
        'stagecolor' => '#5a2a5c', 'herocolor' => '#3d1f42', 'heroglowcolor' => '#f0a6ca', 'indexcolor' => '#2a1f2d',
        'footerstyle' => 'brand',
    ],
    'sand' => [
        'brandcolor' => '#b4532a', 'accentcolor' => '#84a98c', 'inkcolor' => '#3b2f2a', 'surfacecolor' => '#faf6f0',
        'stagecolor' => '#8a4b2a', 'herocolor' => '#6f3d24', 'heroglowcolor' => '#e9c46a', 'indexcolor' => '#3b2f2a',
        'footerstyle' => 'light',
    ],
    'graphite' => [
        'brandcolor' => '#18181b', 'accentcolor' => '#f59e0b', 'inkcolor' => '#18181b', 'surfacecolor' => '#f4f4f5',
        'linkcolor' => '#b45309', 'buttoncolor' => '#f59e0b', 'stagecolor' => '#27272a', 'heroglowcolor' => '#f59e0b',
        'footerstyle' => 'light',
    ],
];

/**
 * What a palette may write, with the value each setting takes when the palette does not name it.
 * The second band of the landing pages is not here: it follows the background of the palette.
 */
const THEME_RSMAX_PALETTE_DEFAULTS = [
    'linkcolor' => '', 'buttoncolor' => '', 'stagecolor' => '', 'papercolor' => '', 'indexcolor' => '',
    'herocolor' => '', 'heroglowcolor' => '', 'footerbgcolor' => '', 'footertextcolor' => '',
    'bandcolor1' => '#ffffff', 'cardcolor' => '#ffffff', 'navbarstyle' => 'light', 'footerstyle' => 'dark',
];

/** Columns of links in the footer. */
const THEME_RSMAX_FOOTER_COLUMNS = 3;

/**
 * Returns the main SCSS: our variables, the Boost preset and our components.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_rsmax_get_main_scss_content($theme) {
    global $CFG;

    $scss = file_get_contents(__DIR__ . '/scss/pre.scss');
    $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/post.scss');
    return $scss;
}

/**
 * Returns the SCSS variables that come from the theme settings.
 *
 * Every value is checked before it is written: colours must be hexadecimal and the rest are
 * picked from fixed lists, so nothing typed in the settings reaches the SCSS as it is.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_rsmax_get_pre_scss($theme) {
    $settings = $theme->settings;
    $scss = '';

    foreach (THEME_RSMAX_COLOURS as $setting => $spec) {
        $value = $settings->{$setting} ?? '';
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            $scss .= '$' . $spec['variable'] . ': ' . $value . ";\n";
        }
    }

    $roundness = THEME_RSMAX_ROUNDNESS[$settings->roundness ?? 'soft'] ?? THEME_RSMAX_ROUNDNESS['soft'];
    $scss .= '$border-radius-sm: ' . $roundness[0] . ";\n";
    $scss .= '$border-radius: ' . $roundness[1] . ";\n";
    $scss .= '$border-radius-lg: ' . $roundness[2] . ";\n";
    $scss .= '$btn-border-radius: ' . $roundness[3] . ";\n";
    $scss .= '$btn-border-radius-sm: ' . $roundness[3] . ";\n";
    $scss .= '$btn-border-radius-lg: ' . $roundness[3] . ";\n";
    if (in_array($settings->buttontext ?? '', ['light', 'dark', 'auto'])) {
        // Auto is also the default of the theme: whichever of the two reads on the button.
        $scss .= '$rsmax-button-text: ' . $settings->buttontext . ";\n";
    }
    if (isset($settings->logowhite) && empty($settings->logowhite)) {
        $scss .= "\$rsmax-logo-white: false;\n";
    }
    if (isset($settings->shadows) && empty($settings->shadows)) {
        $scss .= "\$rsmax-shadows: false;\n";
    }

    $custom = ['custom' => '"RSMAX Body", ' . THEME_RSMAX_FONTS['system']];
    $body = ($custom + THEME_RSMAX_FONTS)[$settings->fontbody ?? 'inter'] ?? THEME_RSMAX_FONTS['inter'];
    $scss .= '$font-family-sans-serif: ' . $body . ";\n";
    $headings = $settings->fontheadings ?? 'same';
    if ($headings == 'custom') {
        $scss .= '$headings-font-family: "RSMAX Headings", ' . THEME_RSMAX_FONTS['system'] . ";\n";
    } else if (isset(THEME_RSMAX_FONTS[$headings])) {
        $scss .= '$headings-font-family: ' . THEME_RSMAX_FONTS[$headings] . ";\n";
    }
    $size = (int) ($settings->fontsize ?? 16);
    if ($size >= 14 && $size <= 18) {
        $scss .= '$font-size-base: ' . ($size / 16) . "rem;\n";
    }
    $weight = (int) ($settings->headingweight ?? 700);
    if (in_array($weight, [500, 600, 700, 800])) {
        $scss .= '$headings-font-weight: ' . $weight . ";\n";
    }
    $contentwidth = (int) ($settings->contentwidth ?? 1280);
    if (in_array($contentwidth, THEME_RSMAX_CONTENT_WIDTHS)) {
        $scss .= '$course-content-maxwidth: ' . $contentwidth . "px;\n";
    }
    $indexwidth = (int) ($settings->indexwidth ?? 320);
    if (in_array($indexwidth, THEME_RSMAX_INDEX_WIDTHS)) {
        $scss .= '$drawer-left-width: ' . $indexwidth . "px;\n";
    }
    $logoheight = (int) ($settings->logoheight ?? 36);
    if ($logoheight >= 20 && $logoheight <= 80) {
        $scss .= '$rsmax-logo-height: ' . $logoheight . "px;\n";
    }

    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\$behatsite: true;\n";
    }
    if (!empty($settings->scsspre)) {
        $scss .= $settings->scsspre . "\n";
    }
    return $scss;
}

/**
 * Returns the SCSS appended after everything else: uploaded fonts and images, and the admin's own SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_rsmax_get_extra_scss($theme) {
    $scss = '';
    foreach (['customfontbody' => 'RSMAX Body', 'customfontheadings' => 'RSMAX Headings'] as $setting => $family) {
        $url = $theme->setting_file_url($setting, $setting);
        if (!empty($url)) {
            $scss .= '@font-face { font-family: "' . $family . '"; src: url("' . $url . '") format("woff2");'
                . " font-weight: 100 900; font-display: swap; }\n";
        }
    }
    $url = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($url)) {
        $scss .= 'body.pagelayout-login #page { background-image: url("' . $url . '"); background-size: cover;'
            . " background-position: center; }\n";
    }
    if (!empty($theme->settings->scss)) {
        $scss .= $theme->settings->scss . "\n";
    }
    return $scss;
}

/**
 * Turns the lines of a footer column setting ("Text|/link", one per line) into links.
 *
 * @param string $lines Setting value.
 * @param context $context Context used to filter the texts.
 * @return array[] List of text and url; lines without a valid link are skipped.
 */
function theme_rsmax_parse_links(string $lines, context $context): array {
    $links = [];
    foreach (preg_split('/\R/u', $lines) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) < 2 || $parts[0] === '') {
            continue;
        }
        $url = \theme_rsmax\local\clean::link($parts[1]);
        if ($url !== '') {
            $links[] = ['text' => format_string($parts[0], true, ['context' => $context]), 'url' => $url];
        }
    }
    return $links;
}

/**
 * Serves the files uploaded in the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_rsmax_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, THEME_RSMAX_FILEAREAS)) {
        $theme = theme_config::load('rsmax');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    // Banners of a course and of its sections: for whoever may enter the course.
    $areas = [\theme_rsmax\local\banner::AREA_COURSE, \theme_rsmax\local\banner::AREA_SECTION];
    if ($context->contextlevel == CONTEXT_COURSE && in_array($filearea, $areas) && count($args) == 3) {
        require_course_login($course, true, null, false, true);
        // The second part of the path is the time of the file: it only keeps old copies out of caches.
        $file = get_file_storage()->get_file($context->id, 'theme_rsmax', $filearea, (int) $args[0], '/', $args[2]);
        if ($file && !$file->is_directory()) {
            send_stored_file($file, DAYSECS * 30, 0, false, $options);
        }
    }
    send_file_not_found();
}

/**
 * Adds the page of the banners to the menu of a course, for those who can edit it.
 *
 * @param navigation_node $navigation The settings of the course.
 * @param stdClass $course The course.
 * @param context $context Its context.
 */
function theme_rsmax_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    global $PAGE;

    // Moodle calls every installed theme: the banners only exist while this one, or a child of it, is in use.
    if ($PAGE->theme->name != 'rsmax' && !in_array('rsmax', $PAGE->theme->parents)) {
        return;
    }
    if ($course->id == SITEID || !has_capability('moodle/course:update', $context)) {
        return;
    }
    $navigation->add(
        get_string('banners', 'theme_rsmax'),
        new \core\url('/theme/rsmax/banners.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'rsmaxbanners',
        new pix_icon('i/messagecontentimage', ''),
    );
}

/**
 * Makes new courses start with the blocks chosen in the theme settings. Called when the setting changes.
 */
function theme_rsmax_apply_default_course_blocks(): void {
    \theme_rsmax\local\course_blocks::apply();
}

/**
 * Called when the palette changes in the theme settings.
 *
 * The form that carries the palette carries every colour too, and Moodle writes them one by one
 * after this is called: applied now, the palette would be overwritten by the colours the form
 * still shows. It is applied when the request ends, once the whole form has been saved.
 */
function theme_rsmax_palette_chosen(): void {
    \core_shutdown_manager::register_function('theme_rsmax_apply_palette');
}

/**
 * Writes the settings of the palette chosen in the theme settings.
 *
 * A palette replaces the four main colours, the colours and styles it names, and puts back to its
 * default whatever it does not name, so the whole site follows. "Custom" changes nothing.
 */
function theme_rsmax_apply_palette(): void {
    $palette = get_config('theme_rsmax', 'palette');
    if (!isset(THEME_RSMAX_PALETTES[$palette])) {
        return;
    }
    $values = THEME_RSMAX_PALETTES[$palette];
    $values += ['bandcolor2' => $values['surfacecolor']] + THEME_RSMAX_PALETTE_DEFAULTS;
    foreach ($values as $setting => $value) {
        set_config($setting, $value, 'theme_rsmax');
    }
    theme_reset_all_caches();
}
