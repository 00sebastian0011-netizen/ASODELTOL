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

use stdClass;

/**
 * Where the AI assistant (block_openaiagent) is, when the site has it.
 *
 * A course has its own assistant, for the people of the course: the block is one of those a new
 * course starts with, and the theme brings it from the course page to the rest of the course.
 * Outside the courses there is one assistant of the site, a block of the home page that the
 * theme also brings to the catalogue, the category pages and the pages where somebody decides
 * to enrol. It is never shown inside a course, so each page has one assistant.
 *
 * The block decides by itself who may talk to it; nothing here grants or checks that.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assistant {
    /** Name of the block, without the block_ prefix. */
    public const BLOCK = 'openaiagent';

    /** Region of the home page that holds the assistant of the site. */
    public const REGION = 'side-pre';

    /** Beginning of the page types, other than the home, where the assistant of the site is shown. */
    public const PAGES = ['course-index', 'enrol-'];

    /** Beginning of the page types of a course where its assistant is not brought: an exam under way. */
    public const NOT_DURING = ['mod-quiz-attempt', 'mod-quiz-summary'];

    /**
     * Whether the block is installed on the site.
     *
     * @return bool
     */
    public static function installed(): bool {
        return get_config('block_' . self::BLOCK, 'version') !== false
            && \core_component::get_plugin_directory('block', self::BLOCK) !== null;
    }

    /**
     * Arranges the assistant the first time the theme and the block are both on the site.
     *
     * The block is a product of its own and may be installed before or after the theme, so this
     * is called from the installation of the theme and from the pages, where it costs nothing
     * once done.
     */
    public static function ensure(): void {
        if (get_config('theme_rsmax', 'assistantsetup') || !self::installed()) {
            return;
        }
        // Marked first: two pages asking at once must not create two assistants.
        set_config('assistantsetup', 1, 'theme_rsmax');
        course_blocks::installed(self::BLOCK);
        self::setup_site_block();
    }

    /**
     * Returns the block of the assistant of the site.
     *
     * @return stdClass|null Record of block_instances, null when the site has none.
     */
    public static function site_block(): ?stdClass {
        global $DB;

        $id = (int) get_config('theme_rsmax', 'assistantsiteblock');
        if (!$id) {
            return null;
        }
        return $DB->get_record('block_instances', ['id' => $id, 'blockname' => self::BLOCK]) ?: null;
    }

    /**
     * Gives the site its assistant: a block on the home page, open to visitors.
     *
     * An assistant the site already has, on the home page or on every page, is kept as it is.
     *
     * @return stdClass|null The block, null when it could not be created.
     */
    public static function setup_site_block(): ?stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/blocklib.php');

        if ($block = self::site_block()) {
            return $block;
        }
        $home = \core\context\course::instance(SITEID);
        $contexts = [$home->id, \core\context\system::instance()->id];
        [$insql, $params] = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);
        $existing = $DB->get_records_select(
            'block_instances',
            "blockname = :name AND parentcontextid $insql",
            $params + ['name' => self::BLOCK],
            'id',
            '*',
            0,
            1
        );
        $block = reset($existing) ?: null;
        if (!$block) {
            $page = new \moodle_page();
            $page->set_context($home);
            $page->set_pagetype('site-index');
            $page->set_url('/');
            $page->blocks->add_region(self::REGION, false);
            $page->blocks->add_block(self::BLOCK, self::REGION, 0, false, 'site-index');
            $created = $DB->get_records(
                'block_instances',
                ['blockname' => self::BLOCK, 'parentcontextid' => $home->id],
                'id DESC',
                '*',
                0,
                1
            );
            $block = reset($created) ?: null;
            if ($block) {
                // The block keeps visitors out until its own settings say otherwise.
                $block->configdata = base64_encode(serialize((object) ['visitors' => 1]));
                $DB->set_field('block_instances', 'configdata', $block->configdata, ['id' => $block->id]);
            }
        }
        if ($block) {
            set_config('assistantsiteblock', $block->id, 'theme_rsmax');
        }
        return $block;
    }

    /**
     * Whether the assistant of the site is brought to this page.
     *
     * @param \moodle_page $page The page being shown.
     * @return bool
     */
    public static function carried_to(\moodle_page $page): bool {
        foreach (self::PAGES as $start) {
            if (str_starts_with((string) $page->pagetype, $start)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the assistant of the course is brought to this page.
     *
     * Moodle shows the blocks of a new course on the course page only, so the assistant would
     * be gone as soon as somebody opens an activity.
     *
     * @param \moodle_page $page The page being shown.
     * @return bool
     */
    public static function follows_to(\moodle_page $page): bool {
        if (empty($page->course->id) || $page->course->id == SITEID || self::carried_to($page)) {
            return false;
        }
        foreach (self::NOT_DURING as $start) {
            if (str_starts_with((string) $page->pagetype, $start)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the block of the assistant of a course.
     *
     * @param int $courseid Id of the course.
     * @return stdClass|null Record of block_instances; null when the course has none, or when
     *      somebody hid it on the course page.
     */
    public static function course_block(int $courseid): ?stdClass {
        global $DB;

        $context = \core\context\course::instance($courseid);
        $blocks = $DB->get_records(
            'block_instances',
            ['blockname' => self::BLOCK, 'parentcontextid' => $context->id],
            'id',
            '*',
            0,
            1
        );
        $block = reset($blocks) ?: null;
        if ($block && $DB->record_exists('block_positions', ['blockinstanceid' => $block->id, 'visible' => 0])) {
            return null;
        }
        return $block;
    }

    /**
     * Returns the ids of the assistants Moodle put on the page that this person gets to see.
     *
     * A block that shows nothing to them (the assistant of a category closed to visitors, say)
     * does not count: the one of the site can take its place.
     *
     * @param \moodle_page $page The page being shown.
     * @return int[]
     */
    private static function on_page(\moodle_page $page): array {
        $ids = [];
        foreach ($page->blocks->get_regions() as $region) {
            foreach ($page->blocks->get_blocks_for_region($region) as $block) {
                if ($block->name() == self::BLOCK && !empty($block->get_content()->text)) {
                    $ids[] = (int) $block->instance->id;
                }
            }
        }
        return $ids;
    }

    /**
     * Prepares the assistant of a page: brings the one of the site or the one of the course
     * where it belongs and starts the floating button.
     *
     * @param \moodle_page $page The page being shown.
     * @param \core_renderer $output Renderer.
     * @return array With "block" (HTML of the assistant brought to the page, empty when there
     *      is none) and "floating" (whether the assistant opens from a floating button).
     */
    public static function for_page(\moodle_page $page, \core_renderer $output): array {
        $none = ['block' => '', 'floating' => false];
        if (!self::installed()) {
            return $none;
        }
        self::ensure();

        $settings = $page->theme->settings;
        $floating = !isset($settings->assistantfloating) || !empty($settings->assistantfloating);
        $sitepages = !isset($settings->assistantsitepages) || !empty($settings->assistantsitepages);
        $coursepages = !isset($settings->assistantcoursepages) || !empty($settings->assistantcoursepages);

        $onpage = self::on_page($page);
        $html = '';
        $brought = null;
        // One assistant on each page: a category that has its own keeps it.
        if (!$onpage && $sitepages && self::carried_to($page)) {
            $brought = self::site_block();
        } else if (!$onpage && $coursepages && self::follows_to($page)) {
            $brought = self::course_block((int) $page->course->id);
        }
        if ($brought) {
            // What Moodle adds to a block when it loads the blocks of a page itself.
            $brought->region = self::REGION;
            $brought->weight = $brought->defaultweight;
            $brought->visible = 1;
            $brought->blockpositionid = null;
            $block = block_instance(self::BLOCK, $brought, $page);
            if ($block && has_capability('moodle/block:view', $block->context)) {
                $content = $block->get_content_for_output($output);
                $html = $content ? $output->block($content, self::REGION) : '';
            }
        }
        if (!$onpage && $html === '') {
            return $none;
        }
        if ($floating) {
            $label = format_string($settings->assistantlabel ?? '', true, ['context' => \core\context\system::instance()]);
            $page->requires->js_call_amd('theme_rsmax/assistant', 'init', [[
                'site' => (int) get_config('theme_rsmax', 'assistantsiteblock'),
                'label' => $label !== '' ? $label : get_string('assistantlabeldefault', 'theme_rsmax'),
                // The name read aloud has to contain the text on the button.
                'open' => $label !== '' ? $label : get_string('assistantopen', 'theme_rsmax'),
            ]]);
        }
        return ['block' => $html, 'floating' => $floating];
    }
}
