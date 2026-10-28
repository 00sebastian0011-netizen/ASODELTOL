<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_rsmax\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Course-specific, permission-aware links to actual Moodle content.
 *
 * The editor stores only activity IDs belonging to its course. No uploaded content,
 * credentials, consent events, or enrolments are stored in the theme.
 *
 * @package theme_rsmax
 */
final class course_utilities {
    private const LINKS = [
        'announcements' => ['coursequickannouncements', 'fa-bell'],
        'recordings' => ['coursequickrecordings', 'fa-video'],
        'materials' => ['coursequickmaterials', 'fa-folder'],
        'workshops' => ['coursequickworkshops', 'fa-clipboard-list'],
        'live' => ['coursequicklive', 'fa-calendar'],
        'support' => ['coursequicksupport', 'fa-headset'],
    ];

    /** Allowed Moodle module types per quick link. */
    private const TYPES = [
        'announcements' => ['forum'],
        'recordings' => ['url', 'resource', 'page', 'folder'],
        'materials' => ['folder', 'resource', 'page', 'book', 'url'],
        'workshops' => ['assign', 'quiz', 'h5pactivity', 'lesson', 'workshop', 'forum'],
        'live' => ['bigbluebuttonbn', 'zoom', 'url'],
        'support' => ['url', 'page'],
    ];

    public static function keys(): array {
        return array_keys(self::LINKS);
    }

    public static function can_manage(int $courseid): bool {
        return isloggedin() && !isguestuser()
            && has_capability('moodle/course:manageactivities', \context_course::instance($courseid));
    }

    private static function config_key(int $courseid): string {
        return 'coursequicklinks_' . $courseid;
    }

    /**
     * Returns null when this course has never been configured through the visual editor.
     *
     * 0 = detect automatically, -1 = explicitly disable, positive = course module ID.
     */
    public static function choices_for_course(int $courseid): ?array {
        $raw = get_config('theme_rsmax', self::config_key($courseid));
        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            // Invalid stored configuration must fail closed; no untrusted URL fallback.
            return array_fill_keys(self::keys(), -1);
        }
        $out = [];
        foreach (self::keys() as $key) {
            $value = $decoded[$key] ?? -1;
            $out[$key] = is_int($value) ? $value : -1;
        }
        return $out;
    }

    /**
     * Is this an existing, visible, type-compatible course activity?
     */
    private static function eligible_module($cm, string $key): bool {
        return isset(self::TYPES[$key]) && !empty($cm->url) && !empty($cm->visible)
            && empty($cm->deletioninprogress)
            && in_array($cm->modname, self::TYPES[$key], true);
    }

    /**
     * Options for native Moodle Forms API; entries are scoped to this course.
     */
    public static function options_for_course(\stdClass $course): array {
        $modinfo = get_fast_modinfo($course);
        $options = [];
        foreach (self::LINKS as $key => [$label, $icon]) {
            if ($key === 'support') {
                // CAU is institutional, not an activity selected by each course.
                $options[$key] = [0 => get_string('coursequickautomatic', 'theme_rsmax')];
                continue;
            }
            $choices = [
                0 => get_string('coursequickautomatic', 'theme_rsmax'),
                -1 => get_string('coursequickdisabled', 'theme_rsmax'),
            ];
            foreach ($modinfo->cms as $cm) {
                if (!self::eligible_module($cm, $key) || empty($cm->uservisible)) {
                    continue;
                }
                // Moodle Forms escapes option text; no raw markup or URL is accepted.
                $choices[(int) $cm->id] = format_string($cm->name);
            }
            $options[$key] = $choices;
        }
        return $options;
    }

    /**
     * Reject changed form values, foreign course modules, hidden or unsuitable modules.
     */
    public static function validate_choices(\stdClass $course, array $submitted): array {
        $modinfo = get_fast_modinfo($course);
        $out = [];
        foreach (self::keys() as $key) {
            $raw = $submitted[$key] ?? 0;
            if (!is_int($raw) && (!is_string($raw) || !preg_match('/^-?\d+$/D', $raw))) {
                throw new \invalid_parameter_exception('Invalid link choice: ' . $key);
            }
            $id = (int) $raw;
            if ($key === 'support' && $id !== 0) {
                throw new \invalid_parameter_exception('CAU is centrally configured');
            }
            if ($id < -1 || ($id > 0 && (!isset($modinfo->cms[$id])
                    || !self::eligible_module($modinfo->cms[$id], $key)
                    || empty($modinfo->cms[$id]->uservisible)))) {
                throw new \invalid_parameter_exception('Activity unavailable in this course: ' . $key);
            }
            $out[$key] = $id;
        }
        return $out;
    }

    /**
     * Persist only the six verified module selections; Moodle has no schema migration.
     */
    public static function save(\stdClass $course, array $submitted): void {
        require_capability('moodle/course:manageactivities', \context_course::instance($course->id));
        $choices = self::validate_choices($course, $submitted);
        set_config(self::config_key((int) $course->id),
            json_encode($choices, JSON_THROW_ON_ERROR), 'theme_rsmax');
    }

    /** Legacy site-admin URL input remains supported until it is migrated per course. */
    public static function safe_local_path(string $path): bool {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')
                || preg_match('/[\x00-\x20\\\\]/', $path)
                || preg_match('/%(?:2f|5c|0a|0d)/i', $path)) {
            return false;
        }
        $parts = parse_url($path);
        return $parts !== false && isset($parts['path']) && !isset($parts['host']) && !isset($parts['scheme'])
            && !str_contains($parts['path'], '..')
            && (bool) preg_match('~^/[a-zA-Z0-9_./%-]+$~', $parts['path']);
    }

    public static function configured_for_course(string $lines, int $courseid): array {
        $overrides = [];
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) !== 3 || !ctype_digit($parts[0]) || (int) $parts[0] !== $courseid) {
                continue;
            }
            [$id, $key, $url] = $parts;
            if ($key === 'support') {
                continue; // Legacy per-course CAU URLs cannot replace the institutional service.
            }
            if (isset(self::LINKS[$key]) && self::safe_local_path($url)) {
                $overrides[$key] = $url;
            }
        }
        return $overrides;
    }

    private static function support_url(): ?string {
        if (!(int) get_config('local_asodeltolstaff', 'version')
                || !class_exists('\\local_asodeltolstaff\\support')
                || !has_capability('moodle/site:sendmessage', \context_system::instance())
                || !\local_asodeltolstaff\support::recipient()) {
            return null;
        }
        return (new \moodle_url('/local/asodeltolstaff/support.php'))->out(false);
    }

    /**
     * Official Moodle policy link for the current viewer, or false when not active.
     * A banner is not evidence of accepting any data processing policy.
     */
    public static function policy_url(): string|false {
        global $CFG;
        $haspolicy = ($CFG->sitepolicyhandler ?? '') === 'tool_policy'
            && class_exists('\\tool_policy\\api')
            && !empty(\tool_policy\api::get_current_versions_ids(
                \tool_policy\policy_version::AUDIENCE_LOGGEDIN
            ));
        return $haspolicy
            ? (new \moodle_url('/admin/tool/policy/user.php'))->out(false)
            : false;
    }

    /**
     * Resolve default/legacy single-module IDs per quick-link category for a course.
     *
     * @return array<string, int>
     */
    public static function default_cmids(\stdClass $course): array {
        global $DB;
        $modinfo = get_fast_modinfo($course);
        $detected = [];

        foreach ($modinfo->cms as $cm) {
            if (empty($cm->uservisible) || empty($cm->visible) || empty($cm->url)) {
                continue;
            }
            $key = null;
            if ($cm->modname === 'forum' && $DB->get_field('forum', 'type', ['id' => $cm->instance]) === 'news') {
                $key = 'announcements';
            } else if ($cm->modname === 'folder') {
                $key = 'materials';
            } else if ($cm->modname === 'assign') {
                $key = 'workshops';
            } else if (in_array($cm->modname, ['bigbluebuttonbn', 'zoom'], true)
                    || ($cm->modname === 'url' && preg_match('/sincr[oó]nic|sesiones en vivo|live/iu', $cm->name))) {
                $key = 'live';
            } else if ($cm->modname === 'url'
                    && preg_match('/grabaci[oó]n|sesiones grabadas|recordings?/iu', $cm->name)) {
                $key = 'recordings';
            }
            if ($key !== null && !isset($detected[$key])) {
                $detected[$key] = (int) $cm->id;
            }
        }

        $choices = self::choices_for_course((int) $course->id);
        if ($choices !== null) {
            foreach ($choices as $key => $id) {
                if ($key === 'support') {
                    continue;
                }
                if ($id === -1) {
                    unset($detected[$key]);
                } else if ($id > 0) {
                    $cm = $modinfo->cms[$id] ?? null;
                    if ($cm !== null && self::eligible_module($cm, $key) && !empty($cm->uservisible)) {
                        $detected[$key] = (int) $cm->id;
                    } else {
                        unset($detected[$key]);
                    }
                }
            }
        }
        return $detected;
    }

    /**
     * Resolve links for the current viewer; activity availability is checked per viewer.
     * A course editor cannot make a restricted activity visible to another group.
     */
    public static function export(\stdClass $course): array {
        global $DB;
        $modinfo = get_fast_modinfo($course);
        $destinations = [];

        // The default buttons detect the first valid activity of each known category.
        foreach ($modinfo->cms as $cm) {
            if (empty($cm->uservisible) || empty($cm->visible) || empty($cm->url)) {
                continue;
            }
            $key = null;
            if ($cm->modname === 'forum' && $DB->get_field('forum', 'type', ['id' => $cm->instance]) === 'news') {
                $key = 'announcements';
            } else if ($cm->modname === 'folder') {
                $key = 'materials';
            } else if ($cm->modname === 'assign') {
                $key = 'workshops';
            } else if (in_array($cm->modname, ['bigbluebuttonbn', 'zoom'], true)
                    || ($cm->modname === 'url' && preg_match('/sincr[oó]nic|sesiones en vivo|live/iu', $cm->name))) {
                $key = 'live';
            } else if ($cm->modname === 'url'
                    && preg_match('/grabaci[oó]n|sesiones grabadas|recordings?/iu', $cm->name)) {
                $key = 'recordings';
            }
            if ($key !== null && !isset($destinations[$key])) {
                $destinations[$key] = $cm->url->out(false);
            }
        }

        $supporturl = self::support_url();
        if ($supporturl !== null) {
            $destinations['support'] = $supporturl;
        }

        $choices = self::choices_for_course((int) $course->id);
        if ($choices !== null) {
            foreach ($choices as $key => $id) {
                if ($key === 'support') {
                    continue; // Keep the one CAU destination selected by support_url().
                }
                if ($id === -1) {
                    unset($destinations[$key]);
                } else if ($id > 0) {
                    // Fail closed if the selected module is deleted, moved, hidden or restricted.
                    $cm = $modinfo->cms[$id] ?? null;
                    if ($cm !== null && self::eligible_module($cm, $key) && !empty($cm->uservisible)) {
                        $destinations[$key] = $cm->url->out(false);
                    } else {
                        unset($destinations[$key]);
                    }
                }
            }
        } else {
            // Legacy entries are accepted only if they resolve to a real module in THIS course.
            foreach (self::configured_for_course((string) get_config('theme_rsmax', 'coursequicklinks'),
                    (int) $course->id) as $key => $path) {
                if ($key === 'support') {
                    continue;
                }
                if (!preg_match('~^/mod/[a-z0-9_]+/view\.php\?id=(\d+)$~i', $path, $matches)) {
                    continue;
                }
                $cm = $modinfo->cms[(int) $matches[1]] ?? null;
                if ($cm !== null && self::eligible_module($cm, $key) && !empty($cm->uservisible)) {
                    $destinations[$key] = $cm->url->out(false);
                }
            }
        }

        $items = [];
        foreach (self::LINKS as $key => [$labelkey, $icon]) {
            $items[] = [
                'key' => $key,
                'label' => get_string($labelkey, 'theme_rsmax'),
                'icon' => $icon,
                'url' => $destinations[$key] ?? false,
            ];
        }

        // Explicit multi-target selections override only their own course's legacy shortcuts.
        $items = course_quicklink_groups::apply($course, $items, $modinfo);

        // Keep this field for legacy callers; display now belongs to site notice.
        return [
            'items' => $items,
            'policyurl' => self::policy_url(),
            'manageurl' => self::can_manage((int) $course->id)
                ? (new \moodle_url('/theme/rsmax/quicklinks.php', ['courseid' => $course->id]))->out(false)
                : false,
        ];
    }
}
