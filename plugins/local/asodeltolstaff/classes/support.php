<?php
namespace local_asodeltolstaff;

/** Private support cases and native notifications; no invented SLA or external recipient. */
final class support {
    /** Fixed institutional help topics accepted by the CAU form. */
    public static function categories(): array {
        return [
            'pedagogical' => get_string('supportcategory_pedagogical', 'local_asodeltolstaff'),
            'evaluations' => get_string('supportcategory_evaluations', 'local_asodeltolstaff'),
            'pqrs' => get_string('supportcategory_pqrs', 'local_asodeltolstaff'),
            'certificates' => get_string('supportcategory_certificates', 'local_asodeltolstaff'),
            'platform' => get_string('supportcategory_platform', 'local_asodeltolstaff'),
        ];
    }

    /** Prefix a subject with its validated help topic without accepting free-form categories. */
    public static function categorized_subject(string $category, string $subject): string {
        $category = trim($category);
        $categories = self::categories();
        if (!array_key_exists($category, $categories)) {
            throw new \invalid_parameter_exception('Invalid support category');
        }
        return $categories[$category] . ': ' . $subject;
    }

    public static function recipient(): ?\stdClass {
        $id = (int) get_config('local_asodeltolstaff', 'supportuserid');
        if (!$id || !is_siteadmin($id)) {
            return null;
        }
        $user = \core_user::get_user($id);
        return $user && !$user->deleted && !$user->suspended ? $user : null;
    }

    public static function send(string $subject, string $body, ?string $category = null): int {
        global $USER;
        require_login();
        require_capability('moodle/site:sendmessage', \context_system::instance());
        \core_user::require_active_user($USER);
        $subject = trim(clean_param($subject, PARAM_TEXT));
        $body = trim(clean_param($body, PARAM_TEXT));
        if ($category !== null) {
            $subject = self::categorized_subject($category, $subject);
        }
        if ($subject === '' || $body === '' || \core_text::strlen($subject) > 160 || \core_text::strlen($body) > 4000) {
            throw new \invalid_parameter_exception('Support subject/body invalid');
        }
        if (!$recipient = self::recipient()) {
            throw new \moodle_exception('supportunconfigured', 'local_asodeltolstaff');
        }
        // A native per-user lock prevents duplicate concurrent submissions. The
        // durable case history is also the bounded rate-limit source.
        $factory = \core\lock\lock_config::get_lock_factory('local_asodeltolstaff');
        $lock = $factory->get_lock('support_' . $USER->id, 2);
        if (!$lock) {
            throw new \moodle_exception('supportwait', 'local_asodeltolstaff');
        }
        try {
            global $DB;
            if ($DB->record_exists_select('local_asstaff_case', 'requesterid = :userid AND timecreated > :cutoff',
                    ['userid' => $USER->id, 'cutoff' => time() - 60])) {
                throw new \moodle_exception('supportwait', 'local_asodeltolstaff');
            }
            $transaction = $DB->start_delegated_transaction();
            $caseid = $DB->insert_record('local_asstaff_case', (object)[
                'requesterid' => $USER->id, 'recipientid' => $recipient->id, 'subject' => $subject,
                'body' => $body, 'status' => 'open', 'timecreated' => time(), 'timemodified' => time()]);
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
            $message->contexturl = (new \moodle_url('/local/asodeltolstaff/support.php', ['caseid' => $caseid]))->out(false);
            $message->contexturlname = get_string('supportchannel', 'local_asodeltolstaff');
            $id = message_send($message);
            if (!$id) {
                throw new \moodle_exception('supportfailed', 'local_asodeltolstaff');
            }
            $DB->set_field('local_asstaff_case', 'notificationid', $id, ['id' => $caseid]);
            $transaction->allow_commit();
            self::send_cau_email($recipient, $caseid, $subject, $body);
            return (int) $caseid;
        } catch (\Throwable $error) {
            if (isset($transaction)) {
                $transaction->rollback($error);
            }
            throw $error;
        } finally {
            $lock->release();
        }
    }

    /** Send an optional CAU copy after the internal ASO case and notification are durable. */
    private static function send_cau_email(\stdClass $recipient, int $caseid, string $subject, string $body): bool {
        $email = trim((string) get_config('local_asodeltolstaff', 'supportemail'));
        if ($email === '') {
            return true;
        }
        if (!validate_email($email)) {
            debugging('Invalid configured ASODELTOL CAU notification address.', DEBUG_DEVELOPER);
            return false;
        }
        $target = (object) ['id' => -$caseid, 'email' => $email, 'firstname' => 'CAU', 'lastname' => 'ASODELTOL',
            'maildisplay' => 0, 'mailformat' => 1];
        $mailbody = get_string('supportreference', 'local_asodeltolstaff', $caseid) . ': ' . $body;
        return email_to_user($target, $recipient, $subject, $mailbody, $mailbody);
    }

    /** A support case is visible only to its requester or its assigned administrator. */
    public static function get_case(int $id): \stdClass {
        global $DB, $USER;
        require_login();
        \core_user::require_active_user($USER);
        $scope = 'id = :caseid AND (requesterid = :requester';
        $params = ['caseid' => $id, 'requester' => $USER->id];
        if (is_siteadmin($USER->id)) {
            $scope .= ' OR recipientid = :recipient';
            $params['recipient'] = $USER->id;
        }
        $case = $DB->get_record_select('local_asstaff_case', $scope . ')', $params);
        if (!$case) {
            throw new \required_capability_exception(\context_system::instance(), 'moodle/site:config', 'nopermissions', '');
        }
        return $case;
    }

    public static function can_reply(\stdClass $case): bool {
        global $USER;
        return (int)$case->recipientid === (int)$USER->id && is_siteadmin($USER->id);
    }

    public static function visible_cases(): array {
        global $DB, $USER;
        require_login();
        $sql = 'requesterid = :requester';
        $params = ['requester' => $USER->id];
        if (is_siteadmin($USER->id)) {
            $sql .= ' OR recipientid = :recipient';
            $params['recipient'] = $USER->id;
        }
        return $DB->get_records_select('local_asstaff_case', $sql, $params, 'id DESC', '*', 0, 50);
    }

    /** Reply and optional resolution are explicit actions; no institutional SLA is inferred. */
    public static function respond(int $caseid, string $body, bool $resolved = false): void {
        global $DB, $USER;
        $case = self::get_case($caseid);
        if (!self::can_reply($case)) {
            throw new \required_capability_exception(\context_system::instance(), 'moodle/site:config', 'nopermissions', '');
        }
        $body = trim(clean_param($body, PARAM_TEXT));
        if ($body === '' || \core_text::strlen($body) > 4000) {
            throw new \invalid_parameter_exception('Support reply invalid');
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_asodeltolstaff')->get_lock('supportcase_' . $caseid, 2);
        if (!$lock) {
            throw new \moodle_exception('supportwait', 'local_asodeltolstaff');
        }
        try {
            $case = self::get_case($caseid);
            if ($case->status === 'resolved') {
                throw new \moodle_exception('supportclosed', 'local_asodeltolstaff');
            }
            $recent = $DB->get_records('local_asstaff_caseevent', ['caseid' => $caseid, 'actorid' => $USER->id],
                'id DESC', '*', 0, 1);
            if ($recent && ($last = reset($recent))->body === $body && $last->timecreated > time() - 60) {
                throw new \moodle_exception('supportwait', 'local_asodeltolstaff');
            }
            $transaction = $DB->start_delegated_transaction();
            $DB->insert_record('local_asstaff_caseevent', (object)['caseid' => $caseid, 'actorid' => $USER->id,
                'action' => $resolved ? 'resolved' : 'replied', 'body' => $body, 'timecreated' => time()]);
            $DB->update_record('local_asstaff_case', (object)['id' => $caseid,
                'status' => $resolved ? 'resolved' : 'replied', 'timemodified' => time()]);
            $message = new \core\message\message();
            $message->component = 'local_asodeltolstaff';
            $message->name = 'supportreply';
            $message->userfrom = $USER;
            $message->userto = \core_user::get_user($case->requesterid, '*', MUST_EXIST);
            $message->subject = get_string('supportresponse', 'local_asodeltolstaff', $caseid);
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/local/asodeltolstaff/support.php', ['caseid' => $caseid]))->out(false);
            $message->contexturlname = get_string('supportchannel', 'local_asodeltolstaff');
            if (!message_send($message)) {
                throw new \moodle_exception('supportfailed', 'local_asodeltolstaff');
            }
            $transaction->allow_commit();
        } catch (\Throwable $error) {
            if (isset($transaction)) {
                $transaction->rollback($error);
            }
            throw $error;
        } finally {
            $lock->release();
        }
    }
}
