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
 * Ordered quick-link groups pointing only to existing sections and course modules.
 *
 * No content, new links, enrolments, permissions or uploaded files are stored here.
 * Existing one-target configuration remains in course_utilities until this editor
 * is saved for a course. CAU is always central and is intentionally not a group.
 *
 * @package theme_rsmax
 */
final class course_quicklink_groups {
    private const KEYS = ['announcements', 'recordings', 'materials', 'workshops', 'live'];
    private const MAX_TARGETS = 100;

    public static function keys(): array {
        return self::KEYS;
    }

    private static function key(int $courseid): string {
        return 'coursequickgroups_' . $courseid;
    }

    /**
     * Null means the new editor has never saved this course, so legacy links apply.
     * Malformed saved configuration fails closed rather than exposing other links.
     *
     * @return array<string, string[]>|null
     */
    public static function selected(int $courseid): ?array {
        $raw = get_config('theme_rsmax', self::key($courseid));
        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }
        $config = json_decode((string) $raw, true);
        $selected = array_fill_keys(self::KEYS, []);
        if (!is_array($config) || ($config['version'] ?? null) !== 1) {
            return $selected;
        }
        foreach (self::KEYS as $name) {
            $values = $config[$name] ?? [];
            if (!is_array($values) || count($values) > self::MAX_TARGETS) {
                continue;
            }
            $seen = [];
            foreach ($values as $value) {
                if (!is_string($value) || !preg_match('/^(?:section|cm):[1-9][0-9]*$/D', $value)
                        || isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $selected[$name][] = $value;
            }
        }
        return $selected;
    }

    /** Load Moodle's canonical section-name helper before the editor renders. */
    private static function ensure_course_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /** The available editor options are built from this course, never user URLs. */
    public static function options(\stdClass $course): array {
        self::ensure_course_lib();
        $modinfo = get_fast_modinfo($course);
        $options = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int) $section->section < 1 || empty($section->visible) || empty($section->uservisible)) {
                continue;
            }
            $label = get_section_name($course, $section);
            $options['section:' . $section->id] = get_string('coursequicksection', 'theme_rsmax') . ': ' . $label;
            foreach ($modinfo->sections[(int) $section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid] ?? null;
                if (!self::module_available($cm)) {
                    continue;
                }
                $options['cm:' . $cm->id] = get_string('coursequickactivity', 'theme_rsmax')
                    . ': ' . $label . ' / ' . format_string($cm->name);
            }
        }
        return $options;
    }

    /** Visibility for the current viewer; do not expose hidden/restricted content. */
    private static function module_available($cm): bool {
        return $cm !== null && !empty($cm->visible) && !empty($cm->uservisible)
            && !empty($cm->url) && empty($cm->deletioninprogress);
    }

    /**
     * Reject forged identifiers, other-course resources and disabled sections.
     * Preserve the submitted order, which becomes the order of the displayed list.
     *
     * @return array<string, string[]>
     */
    public static function validate(\stdClass $course, array $submitted): array {
        $allowed = self::options($course);
        $result = [];
        foreach (self::KEYS as $name) {
            $values = $submitted[$name] ?? [];
            if (!is_array($values) || count($values) > self::MAX_TARGETS) {
                throw new \invalid_parameter_exception('Invalid quick-link group: ' . $name);
            }
            $unique = [];
            foreach ($values as $value) {
                if (!is_string($value) || !isset($allowed[$value]) || isset($unique[$value])) {
                    throw new \invalid_parameter_exception('Unavailable course destination: ' . $name);
                }
                $unique[$value] = true;
            }
            $result[$name] = array_keys($unique);
        }
        foreach ($submitted as $name => $_) {
            if (!in_array($name, self::KEYS, true)) {
                throw new \invalid_parameter_exception('Invalid quick-link category');
            }
        }
        return $result;
    }

    public static function save(\stdClass $course, array $submitted): void {
        require_capability('moodle/course:manageactivities', \context_course::instance($course->id));
        $choices = self::validate($course, $submitted);
        set_config(self::key((int) $course->id),
            json_encode(['version' => 1] + $choices, JSON_THROW_ON_ERROR), 'theme_rsmax');
    }

    /**
     * Resolve only selected content which is currently visible to this user.
     * Never invent resource URLs, bypass group restrictions or reveal hidden labels.
     *
     * @return array<array{url: string, label: string}>
     */
    private static function visible_targets(\stdClass $course, array $tokens, $modinfo): array {
        self::ensure_course_lib();
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[(int) $section->id] = $section;
        }
        $items = [];
        foreach ($tokens as $token) {
            [$type, $id] = explode(':', $token, 2);
            $id = (int) $id;
            if ($type === 'section') {
                $section = $sections[$id] ?? null;
                if ($section === null || (int) $section->section < 1 || empty($section->visible)
                        || empty($section->uservisible)) {
                    continue;
                }
                $items[] = [
                    'url' => (new \moodle_url('/course/section.php', ['id' => $section->id]))->out(false),
                    'label' => get_section_name($course, $section),
                ];
            } else if ($type === 'cm') {
                $cm = $modinfo->cms[$id] ?? null;
                if (!self::module_available($cm)) {
                    continue;
                }
                $items[] = ['url' => $cm->url->out(false), 'label' => format_string($cm->name)];
            }
        }
        return $items;
    }

    /**
     * Project new groups onto the existing six RSMax items.
     * Legacy/automatic links remain unchanged until the new editor is saved.
     */
    public static function apply(\stdClass $course, array $items, $modinfo): array {
        $saved = self::selected((int) $course->id);
        if ($saved === null) {
            return $items;
        }
        foreach ($items as &$item) {
            if ($item['key'] === 'support') {
                continue; // The shared CAU is always /local/asodeltolstaff/support.php.
            }
            $targets = self::visible_targets($course, $saved[$item['key']] ?? [], $modinfo);
            $item['targets'] = $targets;
            $item['multiple'] = count($targets) > 1;
            $item['url'] = count($targets) === 1 ? $targets[0]['url'] : false;
        }
        unset($item);
        return $items;
    }
}
