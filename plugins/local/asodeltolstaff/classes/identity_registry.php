<?php
namespace local_asodeltolstaff;

/** Atomic, non-PII uniqueness registry for institutional documents. */
final class identity_registry {
    private const TYPES = ['CC', 'CE', 'TI', 'PA', 'PEP', 'PPT', 'NIT'];

    public static function fingerprint(string $type, string $number): array {
        $type = strtoupper(trim($type));
        $number = preg_replace('/[^\pL\pN]/u', '', \core_text::strtoupper(trim($number)));
        if (!in_array($type, self::TYPES, true) || $number === '' || \core_text::strlen($number) > 40) {
            throw new \invalid_parameter_exception('Invalid document type or number');
        }
        return [$type, hash('sha256', $type . "\0" . $number)];
    }

    /** Preflight result deliberately contains no document number or user PII. */
    public static function preflight(string $type, string $number, int $userid = 0): array {
        global $DB;
        [$type, $hash] = self::fingerprint($type, $number);
        $record = $DB->get_record('local_asstaff_identity', ['doctype' => $type, 'dochash' => $hash]);
        return ['available' => !$record || (int)$record->userid === $userid,
            'doctype' => $type, 'fingerprint' => substr($hash, 0, 12)];
    }

    /** Bind under a unique DB index. Concurrent duplicates have one winner. */
    public static function bind(int $userid, string $type, string $number, string $source): int {
        global $DB;
        if (!in_array($source, ['create', 'import', 'assign'], true)) {
            throw new \invalid_parameter_exception('Invalid identity source');
        }
        $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', MUST_EXIST);
        [$type, $hash] = self::fingerprint($type, $number);
        $transaction = $DB->start_delegated_transaction();
        try {
            $own = $DB->get_record('local_asstaff_identity', ['userid' => $userid]);
            if ($own && ($own->doctype !== $type || !hash_equals($own->dochash, $hash))) {
                throw new \invalid_parameter_exception('User is already bound to another document');
            }
            if ($own) {
                $transaction->allow_commit();
                return (int)$own->id;
            }
            $id = $DB->insert_record('local_asstaff_identity', (object)[
                'userid' => $userid, 'doctype' => $type, 'dochash' => $hash,
                'source' => $source, 'timecreated' => time(),
            ]);
            $transaction->allow_commit();
            return (int)$id;
        } catch (\dml_write_exception $e) {
            $transaction->rollback(new \invalid_parameter_exception('Document is already registered'));
        }
    }

    /** Assignment and identity reservation are one transaction. */
    public static function assign_group(int $userid, int $groupid, string $type, string $number): void {
        global $DB;
        $group = $DB->get_record('groups', ['id' => $groupid], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        self::bind($userid, $type, $number, 'assign');
        if (!groups_add_member($group, $userid)) {
            $transaction->rollback(new \moodle_exception('erroraddremoveuser', 'group'));
        }
        $transaction->allow_commit();
    }

    /** Create an account and reserve its document in the same application transaction. */
    public static function create_user_with_document(array $userdata, string $type, string $number): \stdClass {
        global $DB;
        self::preflight($type, $number);
        $transaction = $DB->start_delegated_transaction();
        try {
            $user = user_create((object)$userdata, false, false);
            self::bind((int)$user, $type, $number, 'create');
            $transaction->allow_commit();
            return get_user((int)$user);
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /** Validate every row, then perform an all-or-nothing synthetic/import batch. */
    public static function import_users(array $rows): array {
        global $DB;
        $preflight = self::import_preflight($rows);
        foreach ($preflight as $item) {
            if (!$item['available']) {
                throw new \invalid_parameter_exception('Import contains a duplicate document at row ' . $item['row']);
            }
        }
        $transaction = $DB->start_delegated_transaction();
        $users = [];
        try {
            foreach ($rows as $row) {
                $users[] = self::create_user_with_document($row['user'], $row['doctype'], $row['document']);
            }
            $transaction->allow_commit();
            return $users;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /** Validate a complete import before any user is written. */
    public static function import_preflight(array $rows): array {
        $seen = [];
        $result = [];
        foreach ($rows as $index => $row) {
            [$type, $hash] = self::fingerprint((string)$row['doctype'], (string)$row['document']);
            $check = self::preflight($type, (string)$row['document']);
            $duplicate = isset($seen[$type . ':' . $hash]);
            $result[] = ['row' => $index + 1, 'available' => $check['available'] && !$duplicate,
                'doctype' => $type, 'fingerprint' => substr($hash, 0, 12)];
            $seen[$type . ':' . $hash] = true;
        }
        return $result;
    }
}
