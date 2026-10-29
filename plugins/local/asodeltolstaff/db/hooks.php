<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_asodeltolstaff\hook_callbacks::class, 'extend_primary'],
    ],
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\local_asodeltolstaff\hook_callbacks::class, 'enforce_profile_privacy'],
    ],
];
