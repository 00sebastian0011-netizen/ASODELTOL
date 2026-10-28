<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/**
 * Institutional management of student residence municipality and credentials (REQ-USR-01..05).
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_credentials {
    /** @var string Verified municipality status. */
    public const MUNICIPALITY_VERIFIED = 'verified';
    /** @var string Doubtful or unidentified municipality pending verification. */
    public const MUNICIPALITY_PENDING = 'pending_verification';

    /** @var string No initial credential issued yet. */
    public const DELIVERY_PENDING = 'pending';
    /** @var string Initial credential generated and awaiting private delivery. */
    public const DELIVERY_GENERATED = 'generated';
    /** @var string Credential delivered through a controlled private channel. */
    public const DELIVERY_DELIVERED = 'delivered';
    /** @var string Credential reset by an authorised administrator. */
    public const DELIVERY_RESET = 'reset';

    /** @var string[] Approved controlled channels for credential delivery. */
    public const CHANNELS = [
        'private_institutional',
        'in_person_verified',
        'verified_messaging',
    ];

    /**
     * Require site administration authority before inspecting or modifying profiles/credentials.
     */
    public static function require_authority(): void {
        require_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * REQ-USR-05 (1): Inspect existing user accounts, auth plugins and current policy state.
     *
     * @param int $courseid Optional course scope.
     * @return array
     */
    public static function preflight_check(int $courseid = 0): array {
        global $CFG, $DB;
        self::require_authority();

        $users = self::target_students($courseid, 0);
        $authcounts = [];
        foreach ($users as $user) {
            $auth = (string) ($user->auth ?: 'manual');
            $authcounts[$auth] = ($authcounts[$auth] ?? 0) + 1;
        }

        $hidden = array_filter(array_map('trim', explode(',', (string) ($CFG->hiddenuserfields ?? ''))));
        $cityhidden = in_array('city', $hidden, true);
        $citylocked = (string) get_config('auth_manual', 'field_lock_city') === 'locked';

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);
        $passwordlocked = false;
        if ($studentroleid) {
            $perm = $DB->get_field('role_capabilities', 'permission', [
                'roleid' => (int) $studentroleid,
                'contextid' => \context_system::instance()->id,
                'capability' => 'moodle/user:changeownpassword',
            ], IGNORE_MISSING);
            $passwordlocked = $perm !== false && (int) $perm === CAP_PREVENT;
        }

        return [
            'courseid' => $courseid,
            'studentcount' => count($users),
            'authcounts' => $authcounts,
            'cityhidden' => $cityhidden,
            'citylocked' => $citylocked,
            'passwordlocked' => $passwordlocked,
            'hasbackup' => (string) get_config('local_asodeltolstaff', 'usr_policy_backup') !== '',
        ];
    }

    /**
     * REQ-USR-05 (2): Back up affected profile and role configuration before applying changes.
     *
     * @return array
     */
    public static function backup_policy_state(): array {
        global $CFG, $DB;
        self::require_authority();

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);
        $existingperm = null;
        if ($studentroleid) {
            $perm = $DB->get_field('role_capabilities', 'permission', [
                'roleid' => (int) $studentroleid,
                'contextid' => \context_system::instance()->id,
                'capability' => 'moodle/user:changeownpassword',
            ], IGNORE_MISSING);
            if ($perm !== false) {
                $existingperm = (int) $perm;
            }
        }

        $snapshot = [
            'timestamp' => time(),
            'hiddenuserfields' => (string) ($CFG->hiddenuserfields ?? ''),
            'auth_manual_field_lock_city' => (string) (get_config('auth_manual', 'field_lock_city') ?: ''),
            'student_changeownpassword' => $existingperm,
        ];
        set_config('usr_policy_backup', json_encode($snapshot, JSON_UNESCAPED_SLASHES), 'local_asodeltolstaff');
        return $snapshot;
    }

    /**
     * REQ-USR-05 (2): Restore backed-up configuration if a rollback is required.
     *
     * @return bool
     */
    public static function restore_policy_state(): bool {
        global $DB;
        self::require_authority();

        $raw = (string) get_config('local_asodeltolstaff', 'usr_policy_backup');
        if ($raw === '') {
            return false;
        }
        $snapshot = json_decode($raw, true);
        if (!is_array($snapshot)) {
            return false;
        }

        set_config('hiddenuserfields', (string) ($snapshot['hiddenuserfields'] ?? ''));
        set_config('field_lock_city', (string) ($snapshot['auth_manual_field_lock_city'] ?? 'unlocked'), 'auth_manual');

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);
        if ($studentroleid) {
            $sysctx = \context_system::instance();
            if (array_key_exists('student_changeownpassword', $snapshot)
                    && $snapshot['student_changeownpassword'] !== null) {
                assign_capability('moodle/user:changeownpassword', (int) $snapshot['student_changeownpassword'],
                    (int) $studentroleid, $sysctx->id, true);
            } else {
                unassign_capability('moodle/user:changeownpassword', (int) $studentroleid, $sysctx->id);
            }
            accesslib_clear_all_caches_for_unit_testing();
        }
        return true;
    }

    /**
     * REQ-USR-01 & REQ-USR-03: Hide and lock city field for students and centralise password control.
     *
     * @return array Summary of applied settings.
     */
    public static function apply_institutional_policy(): array {
        global $CFG, $DB;
        self::require_authority();

        if ((string) get_config('local_asodeltolstaff', 'usr_policy_backup') === '') {
            self::backup_policy_state();
        }

        $hidden = array_filter(array_map('trim', explode(',', (string) ($CFG->hiddenuserfields ?? ''))));
        if (!in_array('city', $hidden, true)) {
            $hidden[] = 'city';
        }
        $hiddenvalue = implode(',', $hidden);
        set_config('hiddenuserfields', $hiddenvalue);
        set_config('field_lock_city', 'locked', 'auth_manual');

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);
        if ($studentroleid) {
            assign_capability('moodle/user:changeownpassword', CAP_PREVENT,
                (int) $studentroleid, \context_system::instance()->id, true);
            accesslib_clear_all_caches_for_unit_testing();
        }

        return [
            'hiddenuserfields' => $hiddenvalue,
            'field_lock_city' => 'locked',
            'student_changeownpassword' => 'prevented',
        ];
    }

    /**
     * REQ-USR-01: Update student municipality of residence without altering enrolments, grades or other data.
     *
     * @param int $userid Target user ID.
     * @param string $municipality Verified municipality name (empty allowed only when pending verification).
     * @param string $status Verification status (verified or pending_verification).
     * @return \stdClass Updated non-sensitive ledger entry.
     */
    public static function update_municipality(
        int $userid,
        string $municipality,
        string $status = self::MUNICIPALITY_VERIFIED
    ): \stdClass {
        global $CFG, $DB, $USER;
        self::require_authority();

        if (!in_array($status, [self::MUNICIPALITY_VERIFIED, self::MUNICIPALITY_PENDING], true)) {
            throw new \invalid_parameter_exception('Invalid municipality verification status');
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if (isguestuser($user)) {
            throw new \invalid_parameter_exception('Guest account cannot be modified');
        }

        $cleaned = trim(clean_param($municipality, PARAM_TEXT));
        if ($status === self::MUNICIPALITY_VERIFIED && $cleaned === '') {
            throw new \invalid_parameter_exception('Verified municipality cannot be empty');
        }

        require_once($CFG->dirroot . '/user/lib.php');
        // Update exclusively the city column when verified or explicitly provided; doubtful values stay unverified.
        $newcity = $status === self::MUNICIPALITY_VERIFIED ? $cleaned : ($cleaned !== '' ? $cleaned : (string) $user->city);
        user_update_user((object) [
            'id' => (int) $user->id,
            'city' => $newcity,
        ], false, false);

        $entry = self::ledger_entry((int) $user->id);
        $entry->municipalitystatus = $status;
        $entry->municipalityupdated = time();
        $entry->municipalityactorid = (int) $USER->id;
        self::save_ledger_entry((int) $user->id, $entry);
        return $entry;
    }

    /**
     * REQ-USR-02 & REQ-USR-03: Generate a cryptographically secure, non-deducible random password.
     *
     * @param int $length Password length (minimum 12).
     * @return string
     */
    public static function generate_random_password(int $length = 14): string {
        $length = max(12, $length);
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '@#%*!+-=';
        $all = $upper . $lower . $digits . $symbols;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    /**
     * REQ-USR-02 & REQ-USR-03: Reject deductible name-based patterns (e.g. sena+firstname+5678$) or shared secrets.
     *
     * @param string $password Candidate password.
     * @param \stdClass $user Target user record.
     * @return bool True if the password matches a forbidden deductible pattern.
     */
    public static function is_deducible_password(string $password, \stdClass $user): bool {
        $lower = \core_text::strtolower(trim($password));
        if ($lower === '' || strlen($lower) < 10) {
            return true;
        }
        if (preg_match('/^sena.*5678\$$/i', $lower)) {
            return true;
        }
        foreach ([$user->username ?? '', $user->firstname ?? '', $user->lastname ?? '', $user->idnumber ?? ''] as $part) {
            $tokens = preg_split('/\s+/', \core_text::strtolower(trim((string) $part))) ?: [];
            foreach ($tokens as $token) {
                if (strlen($token) >= 3 && str_contains($lower, $token)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * REQ-USR-02, REQ-USR-03 & REQ-USR-04: Issue an individual initial or reset credential.
     *
     * The plaintext password is returned once in memory for private delivery and is NEVER stored.
     *
     * @param int $userid Target student ID.
     * @param string $mode 'initial' or 'reset'.
     * @param string|null $custompassword Optional password (must pass non-deducible validation).
     * @return array{userid:int,username:string,fullname:string,password:string,mode:string,timestamp:int}
     */
    public static function issue_credential(int $userid, string $mode = 'initial', ?string $custompassword = null): array {
        global $DB, $USER;
        self::require_authority();

        if (!in_array($mode, ['initial', 'reset'], true)) {
            throw new \invalid_parameter_exception('Invalid credential issue mode');
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if (isguestuser($user) || is_siteadmin($user->id)) {
            throw new \invalid_parameter_exception('Site administrators and guest accounts cannot be managed here');
        }

        if ($custompassword !== null) {
            if (self::is_deducible_password($custompassword, $user)) {
                throw new \invalid_parameter_exception('Deducible or name-based passwords are not allowed');
            }
            $password = $custompassword;
        } else {
            do {
                $password = self::generate_random_password(14);
            } while (self::is_deducible_password($password, $user));
        }

        update_internal_user_password($user, $password);
        unset_user_preference('auth_forcepasswordchange', (int) $user->id);

        $now = time();
        $entry = self::ledger_entry((int) $user->id);
        if ($mode === 'initial' && $entry->timegenerated === 0) {
            $entry->deliverystatus = self::DELIVERY_GENERATED;
            $entry->timegenerated = $now;
            $entry->generatedby = (int) $USER->id;
        } else {
            $entry->deliverystatus = self::DELIVERY_RESET;
            $entry->timereset = $now;
            $entry->resetby = (int) $USER->id;
            $entry->resetcount = (int) $entry->resetcount + 1;
            if ($entry->timegenerated === 0) {
                $entry->timegenerated = $now;
                $entry->generatedby = (int) $USER->id;
            }
        }
        self::save_ledger_entry((int) $user->id, $entry);

        return [
            'userid' => (int) $user->id,
            'username' => (string) $user->username,
            'fullname' => fullname($user),
            'password' => $password,
            'mode' => $mode,
            'timestamp' => $now,
        ];
    }

    /**
     * REQ-USR-04: Record that a credential was delivered through a controlled private channel.
     *
     * @param int $userid Target student ID.
     * @param string $channel Approved delivery channel key.
     * @return \stdClass Updated non-sensitive ledger entry.
     */
    public static function mark_delivered(int $userid, string $channel = 'private_institutional'): \stdClass {
        global $DB, $USER;
        self::require_authority();

        if (!in_array($channel, self::CHANNELS, true)) {
            throw new \invalid_parameter_exception('Invalid credential delivery channel');
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', MUST_EXIST);
        $entry = self::ledger_entry((int) $user->id);
        if ($entry->timegenerated === 0 && $entry->timereset === 0) {
            throw new \invalid_parameter_exception('Cannot mark delivery before generating a credential');
        }

        $entry->deliverystatus = self::DELIVERY_DELIVERED;
        $entry->timedelivered = time();
        $entry->deliveredby = (int) $USER->id;
        $entry->channel = $channel;
        self::save_ledger_entry((int) $user->id, $entry);
        return $entry;
    }

    /**
     * REQ-USR-04: Return the non-sensitive ledger entry for a user (never stores or returns passwords).
     *
     * @param int $userid
     * @return \stdClass
     */
    public static function ledger_entry(int $userid): \stdClass {
        global $DB;
        $raw = (string) get_config('local_asodeltolstaff', 'cred_ledger_' . $userid);
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        $city = (string) $DB->get_field('user', 'city', ['id' => $userid], IGNORE_MISSING);
        $defaultmunistatus = trim($city) !== '' ? self::MUNICIPALITY_VERIFIED : self::MUNICIPALITY_PENDING;

        return (object) [
            'userid' => $userid,
            'municipalitystatus' => is_array($decoded) && !empty($decoded['municipalitystatus'])
                ? (string) $decoded['municipalitystatus'] : $defaultmunistatus,
            'municipalityupdated' => is_array($decoded) ? (int) ($decoded['municipalityupdated'] ?? 0) : 0,
            'municipalityactorid' => is_array($decoded) ? (int) ($decoded['municipalityactorid'] ?? 0) : 0,
            'deliverystatus' => is_array($decoded) && !empty($decoded['deliverystatus'])
                ? (string) $decoded['deliverystatus'] : self::DELIVERY_PENDING,
            'channel' => is_array($decoded) ? (string) ($decoded['channel'] ?? '') : '',
            'timegenerated' => is_array($decoded) ? (int) ($decoded['timegenerated'] ?? 0) : 0,
            'generatedby' => is_array($decoded) ? (int) ($decoded['generatedby'] ?? 0) : 0,
            'timedelivered' => is_array($decoded) ? (int) ($decoded['timedelivered'] ?? 0) : 0,
            'deliveredby' => is_array($decoded) ? (int) ($decoded['deliveredby'] ?? 0) : 0,
            'timereset' => is_array($decoded) ? (int) ($decoded['timereset'] ?? 0) : 0,
            'resetby' => is_array($decoded) ? (int) ($decoded['resetby'] ?? 0) : 0,
            'resetcount' => is_array($decoded) ? (int) ($decoded['resetcount'] ?? 0) : 0,
        ];
    }

    /**
     * REQ-USR-04: Administrative table rows of accounts, courses, groups, municipality and delivery status.
     *
     * Never exposes plaintext passwords or password hashes.
     *
     * @param int $courseid Optional course filter.
     * @param int $groupid Optional group filter.
     * @return array
     */
    public static function directory_rows(int $courseid = 0, int $groupid = 0): array {
        global $DB;
        self::require_authority();

        $users = self::target_students($courseid, $groupid);
        if (!$users) {
            return [];
        }

        $courselabel = '';
        $groupmap = [];
        $memberships = [];
        if ($courseid > 0) {
            $course = get_course($courseid);
            $courselabel = (string) ($course->shortname ?: $course->fullname);
            $groups = groups_get_all_groups($courseid);
            foreach ($groups as $group) {
                $groupmap[$group->id] = (string) $group->name;
            }
            if ($groupmap) {
                foreach ($DB->get_records_list('groups_members', 'groupid', array_keys($groupmap), '', 'id,groupid,userid') as $gm) {
                    $memberships[$gm->userid][] = $groupmap[$gm->groupid];
                }
            }
        }

        $rows = [];
        foreach ($users as $user) {
            $ledger = self::ledger_entry((int) $user->id);
            $usergroups = $memberships[$user->id] ?? [];
            $rows[] = [
                'userid' => (int) $user->id,
                'username' => (string) $user->username,
                'idnumber' => (string) $user->idnumber,
                'student' => fullname($user),
                'email' => (string) $user->email,
                'course' => $courselabel,
                'group' => $usergroups ? implode(', ', $usergroups) : '',
                'municipality' => (string) $user->city,
                'municipalitystatus' => (string) $ledger->municipalitystatus,
                'deliverystatus' => (string) $ledger->deliverystatus,
                'channel' => (string) $ledger->channel,
                'timegeneratedutc' => self::utc((int) $ledger->timegenerated),
                'timedeliveredutc' => self::utc((int) $ledger->timedelivered),
                'timeresetutc' => self::utc((int) $ledger->timereset),
                'resetcount' => (int) $ledger->resetcount,
            ];
        }
        return $rows;
    }

    /**
     * Columns for the non-sensitive credential delivery export (REQ-USR-04).
     *
     * @return array<string,string>
     */
    public static function export_columns(): array {
        return [
            'username' => get_string('report_username', 'local_asodeltolstaff'),
            'idnumber' => get_string('idnumber'),
            'student' => get_string('report_student', 'local_asodeltolstaff'),
            'course' => get_string('course'),
            'group' => get_string('group', 'local_asodeltolstaff'),
            'municipality' => get_string('municipality', 'local_asodeltolstaff'),
            'municipalitystatus' => get_string('municipalitystatus', 'local_asodeltolstaff'),
            'deliverystatus' => get_string('credentialdeliverystatus', 'local_asodeltolstaff'),
            'channel' => get_string('deliverychannel', 'local_asodeltolstaff'),
            'timegeneratedutc' => get_string('timegeneratedutc', 'local_asodeltolstaff'),
            'timedeliveredutc' => get_string('timedeliveredutc', 'local_asodeltolstaff'),
            'timeresetutc' => get_string('timeresetutc', 'local_asodeltolstaff'),
        ];
    }

    /**
     * Persist non-sensitive ledger state in plugin configuration.
     *
     * @param int $userid
     * @param \stdClass $entry
     */
    private static function save_ledger_entry(int $userid, \stdClass $entry): void {
        $payload = [
            'municipalitystatus' => (string) $entry->municipalitystatus,
            'municipalityupdated' => (int) $entry->municipalityupdated,
            'municipalityactorid' => (int) $entry->municipalityactorid,
            'deliverystatus' => (string) $entry->deliverystatus,
            'channel' => (string) $entry->channel,
            'timegenerated' => (int) $entry->timegenerated,
            'generatedby' => (int) $entry->generatedby,
            'timedelivered' => (int) $entry->timedelivered,
            'deliveredby' => (int) $entry->deliveredby,
            'timereset' => (int) $entry->timereset,
            'resetby' => (int) $entry->resetby,
            'resetcount' => (int) $entry->resetcount,
        ];
        set_config('cred_ledger_' . $userid, json_encode($payload, JSON_UNESCAPED_SLASHES), 'local_asodeltolstaff');
    }

    /**
     * Resolve student records for a course/group or site-wide non-admin accounts.
     *
     * @param int $courseid
     * @param int $groupid
     * @return array<int,\stdClass>
     */
    private static function target_students(int $courseid, int $groupid): array {
        global $DB;
        $namefields = implode(',', array_map(
            static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()
        ));
        $fields = 'u.id,u.username,u.idnumber,u.email,u.city,u.auth,u.suspended,' . $namefields;

        if ($courseid > 0) {
            $context = \context_course::instance($courseid);
            $users = get_enrolled_users($context, 'local/asodeltolstaff:beattended', $groupid, $fields, 'u.lastname ASC, u.firstname ASC');
        } else {
            $select = 'deleted = 0 AND id <> :guestid';
            $params = ['guestid' => (int) get_site()->id];
            $guest = $DB->get_record('user', ['username' => 'guest'], 'id', IGNORE_MISSING);
            if ($guest) {
                $params['guestid'] = (int) $guest->id;
            }
            $rawfields = str_replace('u.', '', $fields);
            $users = $DB->get_records_select('user', $select, $params, 'lastname ASC, firstname ASC', $rawfields);
        }

        $filtered = [];
        foreach ($users as $user) {
            if (isguestuser($user) || is_siteadmin($user->id)) {
                continue;
            }
            $filtered[(int) $user->id] = $user;
        }
        return $filtered;
    }

    /**
     * Format Unix timestamp as ISO-8601 UTC or empty string.
     *
     * @param int $timestamp
     * @return string
     */
    private static function utc(int $timestamp): string {
        return $timestamp > 0 ? gmdate('Y-m-d\TH:i:s\Z', $timestamp) : '';
    }
}
