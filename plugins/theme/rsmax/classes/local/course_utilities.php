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
 * Course front-page notice and links to actual, permission-filtered Moodle activities.
 *
 * Display-only code. This class neither records consent nor changes course data.
 *
 * @package theme_rsmax
 */
final class course_utilities {
    /** Only these six known categories may be linked from the course panel. */
    private const LINKS = [
        'announcements' => ['coursequickannouncements', 'fa-bell'],
        'recordings' => ['coursequickrecordings', 'fa-video'],
        'materials' => ['coursequickmaterials', 'fa-folder'],
        'workshops' => ['coursequickworkshops', 'fa-clipboard-list'],
        'live' => ['coursequicklive', 'fa-calendar'],
        'support' => ['coursequicksupport', 'fa-headset'],
    ];

    /**
     * An explicit administrator-supplied override must remain on this Moodle site.
     *
     * @param string $path
     * @return bool
     */
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

    /**
     * Parse optional per-course link overrides. All links must point within Moodle.
     *
     * @param string $lines One entry per line: courseid|key|/local-path
     * @param int $courseid
     * @return array<string, string>
     */
    public static function configured_for_course(string $lines, int $courseid): array {
        $overrides = [];
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) !== 3 || !ctype_digit($parts[0]) || (int) $parts[0] !== $courseid) {
                continue;
            }
            [$id, $key, $url] = $parts;
            if (!isset(self::LINKS[$key]) || !self::safe_local_path($url)) {
                continue;
            }
            $overrides[$key] = $url;
        }
        return $overrides;
    }

    /**
     * Return only authorized destinations and transparently disabled placeholders.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function export(\stdClass $course): array {
        global $CFG, $DB;

        $destinations = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->cms as $cm) {
            if (empty($cm->uservisible) || empty($cm->url)) {
                continue;
            }

            $key = null;
            if ($cm->modname === 'forum' && $DB->get_field('forum', 'type', ['id' => $cm->instance]) === 'news') {
                $key = 'announcements';
            } else if ($cm->modname === 'folder') {
                $key = 'materials';
            } else if ($cm->modname === 'assign') {
                $key = 'workshops';
            } else if (in_array($cm->modname, ['bigbluebuttonbn', 'zoom'], true)) {
                $key = 'live';
            } else if ($cm->modname === 'url'
                    && preg_match('/grabaci[oó]n|sesiones grabadas|recordings?/iu', $cm->name)) {
                // A Moodle URL activity is only a pointer; it does not prove a recording exists.
                $key = 'recordings';
            }

            if ($key !== null && !isset($destinations[$key])) {
                $destinations[$key] = $cm->url->out(false);
            }
        }

        // Site administrators may nominate an existing Moodle page as a destination. Moodle
        // remains responsible for the final capability check at that page.
        foreach (self::configured_for_course((string) get_config('theme_rsmax', 'coursequicklinks'),
                (int) $course->id) as $key => $path) {
            $destinations[$key] = (new \moodle_url($path))->out(false);
        }

        if (!isset($destinations['support']) && (int) get_config('local_asodeltolstaff', 'version') > 0
                && class_exists('\\local_asodeltolstaff\\support')
                && has_capability('moodle/site:sendmessage', \context_system::instance())
                && \local_asodeltolstaff\support::recipient()) {
            $destinations['support'] = (new \moodle_url('/local/asodeltolstaff/support.php'))->out(false);
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

        // Never use a fabricated acceptance URL, or imply that a policy was signed.
        $haspolicy = ($CFG->sitepolicyhandler ?? '') === 'tool_policy'
            && (int) get_config('tool_policy', 'version') > 0;
        return [
            'items' => $items,
            'policyurl' => $haspolicy
                ? (new \moodle_url('/admin/tool/policy/user.php'))->out(false)
                : false,
        ];
    }
}
