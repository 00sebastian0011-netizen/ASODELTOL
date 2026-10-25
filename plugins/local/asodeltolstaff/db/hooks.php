<?php
defined('MOODLE_INTERNAL') || die();
$callbacks = [[
    'hook' => \core\hook\navigation\primary_extend::class,
    'callback' => [\local_asodeltolstaff\hook_callbacks::class, 'extend_primary'],
]];
