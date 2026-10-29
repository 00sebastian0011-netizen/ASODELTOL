<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Navigation integration for local_asodeltolstaff.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a course navigation entry only for authorised users.
 *
 * @param navigation_node $navigation Course navigation.
 * @param stdClass $course Course.
 * @param context_course $context Course context.
 */
function local_asodeltolstaff_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('local/asodeltolstaff:viewsessions', $context)) {
        return;
    }
    $navigation->add(
        get_string('stafftracking', 'local_asodeltolstaff'),
        new moodle_url('/local/asodeltolstaff/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_asodeltolstaff'
    );
}

/**
 * URL for an active, compulsory privacy policy, readable without login.
 *
 * Prefer the unique privacy policy. If there is a single compulsory policy of
 * another type, use that; never invent a URL or expose an unpublished version.
 *
 * @return moodle_url|null
 */
function local_asodeltolstaff_signup_policy_url(): ?moodle_url {
    global $CFG;

    if (($CFG->sitepolicyhandler ?? '') !== 'tool_policy') {
        return null;
    }

    $versions = \tool_policy\api::list_current_versions(\tool_policy\policy_version::AUDIENCE_LOGGEDIN);
    $compulsory = array_values(array_filter($versions, static function($version): bool {
        return (int) $version->optional === \tool_policy\policy_version::AGREEMENT_COMPULSORY;
    }));
    $privacy = array_values(array_filter($compulsory, static function($version): bool {
        return (int) $version->type === \tool_policy\policy_version::TYPE_PRIVACY;
    }));

    if (count($privacy) === 1) {
        $version = $privacy[0];
    } else if (count($compulsory) === 1) {
        $version = $compulsory[0];
    } else {
        return null;
    }

    // The native document viewer allows anonymous access to active versions.
    return new moodle_url('/admin/tool/policy/view.php', ['versionid' => (int) $version->id]);
}

/**
 * Show one separate, real checkbox and a public link on the signup form.
 *
 * The native tool_policy signup_form() is invoked AFTER this callback, so
 * never add or remove Moodle's own "policyagreed" field here.
 *
 * @param MoodleQuickForm $mform
 */
function local_asodeltolstaff_extend_signup_form($mform): void {
    // Accounts are provisioned manually. Native tool_policy handles the only
    // compulsory consent screen on the user's first login.
}

/**
 * Server-side validation; client-side checkbox state is not trusted.
 *
 * @param array $data
 * @return array
 */
function local_asodeltolstaff_validate_extend_signup_form(array $data): array {
    return [];
}

/**
 * Use Moodle's native user-created observer to record versioned acceptance.
 *
 * @param stdClass $data
 */
function local_asodeltolstaff_post_signup_requests(stdClass $data): void {
    // No-op: self-registration is disabled and consent is recorded by the
    // native tool_policy handler after login.
}

/**
 * Check institutional profile access immediately after require_login().
 *
 * This callback runs before Moodle produces the profile page and after
 * the native compulsory tool_policy check. It is not an HTML output hook.
 *
 * The site must enable forceloginforprofiles so core user/profile.php and
 * user/view.php always call require_login() for authenticated viewers.
 */
function local_asodeltolstaff_control_view_profile(
    stdClass $user,
    ?stdClass $course = null,
    ?context $usercontext = null
): int {
    return \local_asodeltolstaff\profile_privacy::control_view_profile($user, $course, $usercontext);
}

function local_asodeltolstaff_after_require_login(): void {
    \local_asodeltolstaff\profile_privacy::enforce_own_profile();
}

/**
 * Compatibility enforcement for profile scripts that skip require_login().
 *
 * Moodle 5.3 invokes this legacy callback from the standard-head hook before
 * registered callbacks. Keeping it alongside db/hooks.php also covers hosts
 * whose long-lived PHP-FPM workers retain the previous hook discovery map.
 */
function local_asodeltolstaff_before_standard_html_head(): string {
    \local_asodeltolstaff\profile_privacy::enforce_own_profile();
    return \local_asodeltolstaff\satisfaction_survey::course_completion_prompt_head_html();
}
