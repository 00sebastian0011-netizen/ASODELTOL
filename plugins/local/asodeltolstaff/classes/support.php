<?php
namespace local_asodeltolstaff;

/** Internal support notification; no invented SLA or external recipient. */
final class support {
    public static function recipient(): ?\stdClass {
        $id = (int) get_config('local_asodeltolstaff', 'supportuserid');
        if (!$id || !is_siteadmin($id)) {
            return null;
        }
        $user = \core_user::get_user($id);
        return $user && !$user->deleted && !$user->suspended ? $user : null;
    }

    public static function send(string $subject, string $body): int {
        global $USER;
        require_login();
        require_capability('moodle/site:sendmessage', \context_system::instance());
        \core_user::require_active_user($USER);
        $subject = trim(clean_param($subject, PARAM_TEXT));
        $body = trim(clean_param($body, PARAM_TEXT));
        if ($subject === '' || $body === '' || \core_text::strlen($subject) > 160 || \core_text::strlen($body) > 4000) {
            throw new \invalid_parameter_exception('Support subject/body invalid');
        }
        if (!$recipient = self::recipient()) {
            throw new \moodle_exception('supportunconfigured', 'local_asodeltolstaff');
        }
        // A native per-user lock prevents duplicate concurrent submissions. The
        // native notification history is also the bounded rate-limit source.
        $factory = \core\lock\lock_config::get_lock_factory('local_asodeltolstaff');
        $lock = $factory->get_lock('support_' . $USER->id, 2);
        if (!$lock) {
            throw new \moodle_exception('supportwait', 'local_asodeltolstaff');
        }
        try {
            global $DB;
            if ($DB->record_exists_select('notifications',
                    'component = :component AND eventtype = :type AND useridfrom = :userid AND timecreated > :cutoff',
                    ['component' => 'local_asodeltolstaff', 'type' => 'supportrequest', 'userid' => $USER->id, 'cutoff' => time() - 60])) {
                throw new \moodle_exception('supportwait', 'local_asodeltolstaff');
            }
            $message = new \core\message\message();
            $message->component = 'local_asodeltolstaff';
            $message->name = 'supportrequest';
            $message->userfrom = $USER;
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/message/index.php', ['id' => $USER->id]))->out(false);
            $message->contexturlname = get_string('supportchannel', 'local_asodeltolstaff');
            $id = message_send($message);
            if (!$id) {
                throw new \moodle_exception('supportfailed', 'local_asodeltolstaff');
            }
            return (int) $id;
        } finally {
            $lock->release();
        }
    }
}
