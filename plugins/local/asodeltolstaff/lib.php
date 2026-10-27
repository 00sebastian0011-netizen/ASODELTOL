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
 * Return the published native policy URL, never a guessed or pending URL.
 *
 * @return moodle_url|null
 */
function local_asodeltolstaff_signup_policy_url(): ?moodle_url {
    global $CFG;

    if (($CFG->sitepolicyhandler ?? '') !== 'tool_policy') {
        return null;
    }
    $manager = new \core_privacy\local\sitepolicy\manager();
    $url = $manager->get_embed_url(false);
    return $url instanceof moodle_url ? $url : null;
}

/**
 * Replace the native tool_policy signup control with one Spanish control.
 * The element keeps Moodle's policyagreed name so the native handler remains
 * the source of the versioned acceptance record.
 *
 * @param MoodleQuickForm $mform
 */
function local_asodeltolstaff_extend_signup_form($mform): void {
    $url = local_asodeltolstaff_signup_policy_url();
    if (!$url) {
        return;
    }

    $label = 'He leído y acepto la Política de Tratamiento de Datos Personales de ASODELTOL';
    $link = html_writer::link($url, 'Ver política', ['target' => '_blank', 'rel' => 'noopener']);
    if ($mform->elementExists('policyagreed')) {
        $mform->removeElement('policyagreed');
    }
    $mform->addElement('checkbox', 'policyagreed', '', $label . ' (' . $link . ')');
    $mform->setType('policyagreed', PARAM_BOOL);
    $mform->setDefault('policyagreed', 0);
    $mform->addRule('policyagreed', 'Debe aceptar la política para crear la cuenta.', 'required', null, 'client');
}

/**
 * Server-side validation; client-side checkbox state is not trusted.
 *
 * @param array $data
 * @return array
 */
function local_asodeltolstaff_validate_extend_signup_form(array $data): array {
    if (local_asodeltolstaff_signup_policy_url() && empty($data['policyagreed'])) {
        return ['policyagreed' => 'Debe aceptar la política para crear la cuenta.'];
    }
    return [];
}

/**
 * Ensure the native user-created event records acceptance in tool_policy.
 *
 * @param stdClass $data
 */
function local_asodeltolstaff_post_signup_requests(stdClass $data): void {
    if (!local_asodeltolstaff_signup_policy_url()) {
        return;
    }
    if (empty($data->policyagreed)) {
        throw new \moodle_exception('invalidparameter', 'error', '', 'policyagreed');
    }
    // auth_email creates the user after this callback; tool_policy's native
    // user-created observer persists the versioned acceptance from policyagreed.
    $data->policyagreed = 1;
}
