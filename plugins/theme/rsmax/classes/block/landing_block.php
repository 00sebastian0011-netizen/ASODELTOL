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

namespace theme_rsmax\block;

use core\url;
use stdClass;

/**
 * Base class of the Pluginia landing blocks.
 *
 * A landing block shows its own heading, can be added several times to any page and renders the
 * template "content" of its plugin with the data returned by {@see self::export_data()}.
 *
 * Blocks made of a list of things written by hand (slides, features, testimonials...) declare the
 * fields of one item in {@see self::item_fields()}; the settings form repeats them and
 * {@see self::items()} returns the items ready for the template.
 *
 * Images are kept in file areas of the block context: the ones listed in
 * {@see self::file_areas()} plus one per image field of the items.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class landing_block extends \block_base {
    /** Colour schemes a block can use, from plain to brand coloured. */
    public const SCHEMES = ['default', 'muted', 'brand', 'dark', 'custom'];

    /** Colours of the custom scheme: setting => CSS variable. */
    public const CUSTOMCOLOURS = [
        'custombg' => '--pluginia-bg',
        'customtext' => '--pluginia-fg',
        'customaccent' => '--pluginia-accent',
    ];

    /** Who a block is shown to: everybody, signed in users only, or visitors (and guests) only.
     *  The choice is applied as permissions, see apply_audience(). */
    public const AUDIENCES = ['all', 'users', 'visitors'];

    /** Seconds between automatic advances a carousel can use; 0 turns it off. */
    public const AUTOPLAY = [0, 3, 5, 8, 12];

    /** Types an item field can have. */
    public const ITEM_TYPES = ['text', 'textarea', 'url', 'icon', 'image', 'yesno'];

    /** File manager options of the images of the blocks. */
    public const IMAGE_OPTIONS = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']];

    /** Most items a block will show. */
    public const MAXITEMS = 24;

    /**
     * Sets the block title from the plugin name.
     */
    public function init() {
        $this->title = get_string('pluginname', $this->component());
    }

    /**
     * Returns the data for the "content" template, or null when there is nothing to show.
     *
     * @return array|null
     */
    abstract protected function export_data(): ?array;

    /**
     * Returns the file areas of this block that hold one image for the whole block.
     *
     * @return array Area name => file manager options.
     */
    public static function file_areas(): array {
        return [];
    }

    /**
     * Returns the fields of one item, for blocks made of a list of items.
     *
     * The first field is the one that decides whether an item exists: items without it are ignored.
     *
     * @return array Field name => one of ITEM_TYPES.
     */
    public static function item_fields(): array {
        return [];
    }

    /**
     * Returns the name of the file area that holds an image field of the items.
     *
     * @param string $field Item field.
     * @return string
     */
    public static function item_area(string $field): string {
        return 'item' . $field;
    }

    /**
     * Returns every file area of the block: the block ones and the ones of the items.
     *
     * @return string[]
     */
    public static function all_file_areas(): array {
        $areas = array_keys(static::file_areas());
        foreach (static::item_fields() as $field => $type) {
            if ($type == 'image') {
                $areas[] = static::item_area($field);
            }
        }
        return $areas;
    }

    /**
     * Returns the frankenstyle name of the block plugin.
     *
     * @return string
     */
    protected function component(): string {
        return 'block_' . $this->name();
    }

    /**
     * Landing blocks can go on any page.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * A page can have several instances with different content.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * The block prints its own heading.
     *
     * @return bool
     */
    public function hide_header() {
        return true;
    }

    /**
     * Returns a setting of this instance.
     *
     * @param string $name Setting name.
     * @param mixed $default Value when the instance has not been configured.
     * @return mixed
     */
    protected function setting(string $name, $default = '') {
        return $this->config->{$name} ?? $default;
    }

    /**
     * Returns a setting that must be one of a list of options.
     *
     * @param string $name Setting name.
     * @param array $options Valid values; the first one is the default.
     * @return mixed
     */
    protected function option(string $name, array $options) {
        $value = $this->setting($name, reset($options));
        return in_array($value, $options) ? $value : reset($options);
    }

    /**
     * Returns a plain text setting, filtered and escaped.
     *
     * @param string $name Setting name.
     * @param string $default Text when the setting is empty.
     * @return string
     */
    protected function text(string $name, string $default = ''): string {
        return $this->format_plain((string) $this->setting($name, $default));
    }

    /**
     * Filters and escapes a single line of text.
     *
     * @param string $value Text.
     * @return string
     */
    protected function format_plain(string $value): string {
        $value = trim($value);
        return $value === '' ? '' : format_string($value, true, ['context' => $this->context]);
    }

    /**
     * Filters and escapes a text of several lines, keeping the line breaks.
     *
     * @param string $value Text.
     * @return string HTML.
     */
    protected function format_lines(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        // Line by line, because format_text() does not run the filters on plain text: a multilang
        // text would be printed with its tags.
        $lines = array_map(fn($line) => $this->format_plain($line), preg_split('/\R/u', $value));
        return implode('<br>', $lines);
    }

    /**
     * Returns a link setting as a safe URL.
     *
     * @param string $name Setting name.
     * @return string Empty when there is no valid link.
     */
    protected function link(string $name): string {
        return self::clean_link((string) $this->setting($name));
    }

    /**
     * Cleans a link typed by whoever configured the block. Paths starting with / are local to the site.
     *
     * @param string $value Link.
     * @return string Empty when there is no valid link.
     */
    public static function clean_link(string $value): string {
        return \theme_rsmax\local\clean::link($value);
    }

    /**
     * Cleans a Font Awesome icon name such as "fa-rocket".
     *
     * @param string $value Icon name, with or without the "fa-" prefix.
     * @return string Empty when it is not a valid icon name.
     */
    public static function clean_icon(string $value): string {
        return \theme_rsmax\local\clean::icon($value);
    }

    /**
     * Returns the price of a course, taken from the first enabled enrolment method that has a cost.
     *
     * @param stdClass $course Course record.
     * @return string Formatted price, empty when the course is free.
     */
    public static function course_price(stdClass $course): string {
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if (!empty($instance->cost) && (float) $instance->cost > 0 && !empty($instance->currency)) {
                return \core_payment\helper::get_cost_as_string((float) $instance->cost, $instance->currency);
            }
        }
        return '';
    }

    /**
     * Returns the title of the block while it has no settings of its own.
     *
     * @return string Empty for no title.
     */
    protected function default_title(): string {
        return '';
    }

    /**
     * Returns the heading shared by all landing blocks.
     *
     * @return array
     */
    protected function heading_data(): array {
        $title = $this->text('title');
        $subtitle = $this->text('subtitle');
        // A block nobody has configured yet, such as those a new course starts with, is not left without a name.
        if ($title === '' && empty((array) $this->config)) {
            $title = $this->default_title();
        }
        return [
            'heading' => $title,
            'subheading' => $subtitle,
            'hasheading' => $title !== '' || $subtitle !== '',
            'scheme' => $this->option('scheme', self::SCHEMES),
            'instanceid' => $this->instance->id,
        ];
    }

    /**
     * Cleans a colour written by whoever configured the block.
     *
     * @param string $value Colour.
     * @return string A hexadecimal colour such as #00a8d6, or empty when it is not one.
     */
    public static function clean_colour(string $value): string {
        return \theme_rsmax\local\clean::colour($value);
    }

    /**
     * Returns the inline style with the colours of the custom scheme of this instance.
     *
     * @return string Empty when the instance does not use the custom scheme or has no colours.
     */
    protected function custom_style(): string {
        if ($this->option('scheme', self::SCHEMES) != 'custom') {
            return '';
        }
        $style = '';
        foreach (self::CUSTOMCOLOURS as $setting => $variable) {
            $colour = self::clean_colour((string) $this->setting($setting));
            if ($colour !== '') {
                $style .= $variable . ':' . $colour . ';';
            }
        }
        return $style;
    }

    /**
     * Returns the milliseconds between automatic advances of the carousel of this instance.
     *
     * @return int 0 when the carousel only moves by hand.
     */
    protected function autoplay(): int {
        $seconds = (int) $this->setting('autoplay', 0);
        return in_array($seconds, self::AUTOPLAY) ? $seconds * 1000 : 0;
    }

    /**
     * Returns the number of columns chosen for this instance.
     *
     * @param int $default Columns when not configured.
     * @param int $min Fewest columns allowed.
     * @param int $max Most columns allowed.
     * @return int
     */
    protected function columns(int $default = 3, int $min = 1, int $max = 6): int {
        return min(max((int) $this->setting('columns', $default), $min), $max);
    }

    /**
     * Returns the positions of the items the instance has, in order.
     *
     * A position is in use when any field has a value stored for it or when it has an image:
     * images live in file areas, not in the settings.
     *
     * @return int[]
     */
    public function item_indexes(): array {
        $indexes = [];
        foreach (static::item_fields() as $field => $type) {
            if ($type == 'image') {
                $files = get_file_storage()->get_area_files(
                    $this->context->id,
                    $this->component(),
                    static::item_area($field),
                    false,
                    'itemid',
                    false
                );
                foreach ($files as $file) {
                    $indexes[(int) $file->get_itemid()] = true;
                }
            } else {
                foreach (array_keys((array) $this->setting('item_' . $field, [])) as $index) {
                    $indexes[(int) $index] = true;
                }
            }
        }
        ksort($indexes);
        return array_keys($indexes);
    }

    /**
     * Returns the items of the block, ready for the template.
     *
     * Each item has its fields cleaned by type, plus "position" (starting at 1), "index" and, for
     * every field, a "has<field>" flag.
     *
     * @return array[]
     */
    protected function items(): array {
        $fields = static::item_fields();
        if (!$fields) {
            return [];
        }
        $first = array_key_first($fields);

        $items = [];
        foreach ($this->item_indexes() as $index) {
            $index = (int) $index;
            $item = ['index' => $index];
            foreach ($fields as $field => $type) {
                $raw = ((array) $this->setting('item_' . $field, []))[$index] ?? '';
                $item[$field] = match ($type) {
                    'textarea' => $this->format_lines((string) $raw),
                    'url' => self::clean_link((string) $raw),
                    'icon' => self::clean_icon((string) $raw),
                    'image' => $this->image_url(static::item_area($field), $index),
                    'yesno' => !empty($raw),
                    default => $this->format_plain((string) $raw),
                };
                $item['has' . $field] = !empty($item[$field]);
            }
            if (!empty($item[$first])) {
                $item['position'] = count($items) + 1;
                $item['isfirst'] = empty($items);
                $items[] = $item;
            }
            if (count($items) >= self::MAXITEMS) {
                break;
            }
        }
        return $items;
    }

    /**
     * Returns the URL of the first image in one of the block file areas.
     *
     * @param string $area File area.
     * @param int $itemid Item id, for areas with one image per item.
     * @return string Empty when there is no image.
     */
    protected function image_url(string $area, int $itemid = 0): string {
        $files = get_file_storage()->get_area_files(
            $this->context->id,
            $this->component(),
            $area,
            $itemid,
            'sortorder, filename',
            false
        );
        $file = reset($files);
        if (!$file) {
            return '';
        }
        return url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * Returns the roles the audience setting acts on.
     *
     * Visitors are whoever holds the guest role: people who have not signed in get it through the
     * site's "role for visitors", and so does the guest account. Signed in users all hold the
     * site's "default role for all users".
     *
     * @return array "visitors" and "users", each a list of role ids.
     */
    public static function audience_roles(): array {
        global $CFG;

        $visitors = [(int) get_guest_role()->id];
        if (!empty($CFG->notloggedinroleid)) {
            $visitors[] = (int) $CFG->notloggedinroleid;
        }
        return [
            'visitors' => array_values(array_unique($visitors)),
            'users' => empty($CFG->defaultuserroleid) ? [] : [(int) $CFG->defaultuserroleid],
        ];
    }

    /**
     * Applies the audience of the block as permissions: who may not see it is prohibited from
     * viewing the block, in the block itself.
     *
     * Using Moodle's own permission means the result shows, and can be changed, in the Permissions
     * page of the block, and that Moodle hides the block without any check of ours. "Prohibit" is
     * used rather than "Prevent" because users hold several roles on a page and any of them
     * allowing the view would otherwise win. Administrators always see every block.
     *
     * @param string $audience One of AUDIENCES.
     */
    public function apply_audience(string $audience): void {
        $roles = self::audience_roles();
        $prohibited = match ($audience) {
            'users' => $roles['visitors'],
            'visitors' => $roles['users'],
            default => [],
        };
        foreach (array_merge($roles['visitors'], $roles['users']) as $roleid) {
            if (in_array($roleid, $prohibited)) {
                assign_capability('moodle/block:view', CAP_PROHIBIT, $roleid, $this->context->id, true);
            } else {
                // Only our own prohibition is lifted; other overrides of the role stay as they are.
                $current = get_capabilities_from_role_on_context((object) ['id' => $roleid], $this->context);
                foreach ($current as $override) {
                    if ($override->capability == 'moodle/block:view' && $override->permission == CAP_PROHIBIT) {
                        unassign_capability('moodle/block:view', $roleid, $this->context->id);
                    }
                }
            }
        }
    }

    /**
     * Renders the block.
     *
     * @return stdClass|null
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';
        if (empty($this->instance)) {
            return $this->content;
        }
        $editing = $this->page->user_is_editing();

        $data = $this->export_data();
        if ($data !== null) {
            $data += $this->heading_data();
            $html = $this->page->get_renderer('core')->render_from_template($this->component() . '/content', $data);
            $style = $this->custom_style();
            // The custom colours travel as CSS variables on a wrapper, so no template needs to know about them.
            $this->content->text = $style === ''
                ? $html
                : \html_writer::div($html, 'pluginia-custom', ['style' => $style]);
            $audience = $this->option('audience', self::AUDIENCES);
            if ($editing && $audience != 'all') {
                $note = get_string('block:audience:note', 'theme_rsmax', get_string('block:audience:' . $audience, 'theme_rsmax'));
                $this->content->text = \html_writer::div($note, 'pluginia-audience-note') . $this->content->text;
            }
        }
        return $this->content;
    }

    /**
     * Saves the instance settings, moving uploaded images from the draft areas to the block.
     *
     * @param stdClass $data Form data.
     * @param bool $nolongerused Not used.
     */
    public function instance_config_save($data, $nolongerused = false) {
        foreach (static::file_areas() as $area => $options) {
            if (isset($data->{$area})) {
                file_save_draft_area_files($data->{$area}, $this->context->id, $this->component(), $area, 0, $options);
                unset($data->{$area});
            }
        }
        foreach (static::item_fields() as $field => $type) {
            if ($type != 'image' || !isset($data->{'item_' . $field})) {
                continue;
            }
            foreach ((array) $data->{'item_' . $field} as $index => $draftitemid) {
                file_save_draft_area_files(
                    $draftitemid,
                    $this->context->id,
                    $this->component(),
                    static::item_area($field),
                    (int) $index,
                    self::IMAGE_OPTIONS
                );
            }
            unset($data->{'item_' . $field});
        }
        // Buttons of the repeated items are not settings.
        unset($data->additems, $data->deleteitem);
        parent::instance_config_save($data, $nolongerused);

        $audience = $data->audience ?? 'all';
        $this->apply_audience(in_array($audience, self::AUDIENCES) ? $audience : 'all');
    }

    /**
     * Deletes the images of the instance.
     *
     * @return bool
     */
    public function instance_delete() {
        get_file_storage()->delete_area_files($this->context->id, $this->component());
        return true;
    }

    /**
     * Copies the images when the block is duplicated.
     *
     * @param int $fromid Id of the instance that was copied.
     * @return bool
     */
    public function instance_copy($fromid) {
        $fromcontext = \core\context\block::instance($fromid);
        $fs = get_file_storage();
        foreach (static::all_file_areas() as $area) {
            $files = $fs->get_area_files($fromcontext->id, $this->component(), $area, false, 'id', false);
            foreach ($files as $file) {
                $fs->create_file_from_storedfile(['contextid' => $this->context->id], $file);
            }
        }
        return true;
    }

    /**
     * Serves an image of a landing block. Called from the pluginfile callback of each block.
     *
     * Images are decoration chosen by whoever configured the block, so they follow the visibility
     * of the page the block is on: site pages are public unless the site forces login, and blocks
     * inside a course need access to the course.
     *
     * @param string $component Block plugin.
     * @param string[] $areas File areas of the block, as returned by all_file_areas().
     * @param stdClass $course Course.
     * @param \context $context Context the file belongs to.
     * @param string $filearea File area.
     * @param array $args Remaining path.
     * @param bool $forcedownload Whether to force the download.
     * @param array $options Options for send_stored_file().
     */
    public static function serve_file(
        string $component,
        array $areas,
        $course,
        \context $context,
        string $filearea,
        array $args,
        bool $forcedownload,
        array $options
    ): void {
        global $CFG, $USER;

        if ($context->contextlevel != CONTEXT_BLOCK || !in_array($filearea, $areas, true)) {
            send_file_not_found();
        }
        $parent = $context->get_parent_context();
        if ($parent->contextlevel == CONTEXT_COURSE && $parent->instanceid != SITEID) {
            require_course_login($course);
        } else if ($parent->contextlevel == CONTEXT_USER) {
            // A block of somebody's dashboard is theirs alone.
            require_login();
            if ($parent->instanceid != $USER->id) {
                send_file_not_found();
            }
        } else if (!empty($CFG->forcelogin)) {
            require_login();
        }
        if ($parent->contextlevel == CONTEXT_COURSECAT && !\core_course_category::get($parent->instanceid, IGNORE_MISSING)) {
            // A category this person cannot see.
            send_file_not_found();
        }
        // The audience of a block is the permission to view it: its pictures follow it.
        if (!has_capability('moodle/block:view', $context)) {
            send_file_not_found();
        }

        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = get_file_storage()->get_file($context->id, $component, $filearea, $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
        \core\session\manager::write_close();
        send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
    }
}
