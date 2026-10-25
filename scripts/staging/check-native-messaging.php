<?php
// Existing synthetic identities only; local messages persist as QA evidence.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/message/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
if ($CFG->wwwroot !== 'http://127.0.0.1:8053' || empty($CFG->noemailever)
        || !empty(get_config('message_airnotifier', 'accesskey'))) {
    throw new RuntimeException('Local staging with external delivery disabled is required');
}
$course = $DB->get_record('course', ['idnumber' => 'ASODELTOL-AF8'], '*', MUST_EXIST);
$category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
if ($course->visible || $category->visible) {
    throw new RuntimeException('Hidden staging required');
}
$admin = get_admin();
$restored = false;
$restore = function() use ($admin, $course, $category, &$restored): void {
    if ($restored) {
        return;
    }
    \core\session\manager::set_user($admin);
    course_change_visibility($course->id, false);
    \core_course_category::get($category->id)->hide();
    $restored = true;
};
register_shutdown_function(function() use ($restore, &$restored): void {
    if (!$restored) {
        try { $restore(); } catch (\Throwable $e) { fwrite(STDERR, 'STAGING_LOCKDOWN_FAILED' . PHP_EOL); }
    }
});
$result = null;
try {
\core\session\manager::set_user($admin);
\core_course_category::get($category->id)->show();
course_change_visibility($course->id, true);
$actors = [];
foreach (['af8.synthetic.g01', 'af8.synthetic.auditor'] as $username) {
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
    if (!str_ends_with($user->email, '@example.invalid')
            || !is_enrolled(context_course::instance($course->id), $user, '', true)) {
        throw new RuntimeException('Existing active synthetic enrollment required');
    }
    $actors[] = $user;
}
[$learner, $auditor] = $actors;
$context = context_course::instance($course->id);
\core\session\manager::set_user($auditor);
if (!has_capability('moodle/grade:viewall', $context) || has_capability('moodle/grade:edit', $context)) {
    throw new RuntimeException('Read-only observer capabilities not demonstrated');
}
$conversation = \core_message\api::create_conversation(
    \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL, [$learner->id, $auditor->id]
);
$ids = [];
foreach ([[$learner, 'QUESTION'], [$auditor, 'REPLY']] as [$actor, $action]) {
    \core\session\manager::set_user($actor);
    if (!\core_message\api::can_send_message_to_conversation($actor->id, $conversation->id)) {
        throw new RuntimeException('Native messaging permission denied; no permission was changed');
    }
    $text = 'ASODELTOL-A14-SYNTHETIC-20261024 ' . $action;
    $id = $DB->get_field('messages', 'id', ['conversationid' => $conversation->id,
        'useridfrom' => $actor->id, 'smallmessage' => $text]);
    if (!$id) {
        $message = \core_message\api::send_message_to_conversation($actor->id, $conversation->id, $text, FORMAT_PLAIN);
        $id = $message->id;
    }
    $ids[] = (int) $id;
}
$received = [];
foreach ([$learner, $auditor] as $actor) {
    \core\session\manager::set_user($actor);
    $messages = \core_message\api::get_conversation_messages($actor->id, $conversation->id)['messages'];
    $actual = array_map(static fn(stdClass $message): int => (int) $message->id, $messages);
    if (array_diff($ids, $actual)) {
        throw new RuntimeException('Recipient retrieval did not include both synthetic messages');
    }
    $received[] = true;
}
$result = ['status' => 'PASS', 'environment' => $CFG->wwwroot,
    'requirements' => ['REQ-20-04', 'REQ-21-03'], 'existing_synthetic_actors' => true,
    'auditor_read_only' => true, 'native_send_and_recipient_retrieval' => $received,
    'message_ids' => $ids, 'external_email_disabled' => true,
    'tested_while_course_available' => true];
} finally {
    $restore();
}
if ($DB->get_field('course', 'visible', ['id' => $course->id])
        || $DB->get_field('course_categories', 'visible', ['id' => $category->id])) {
    throw new RuntimeException('Final visibility check failed');
}
$result['course_hidden'] = true;
$result['category_hidden'] = true;
echo 'ASODELTOL_MESSAGES=' . json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
