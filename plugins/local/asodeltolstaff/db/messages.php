<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'supportrequest' => ['defaults' => ['popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED]],
    'supportreply' => ['defaults' => ['popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED]],
    'groupannouncement' => ['defaults' => ['popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED]],
    'activitypublished' => ['defaults' => ['popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED]],
];
