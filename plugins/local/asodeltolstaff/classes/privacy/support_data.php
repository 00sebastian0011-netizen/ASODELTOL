<?php
namespace local_asodeltolstaff\privacy;

/** Support data lives in the system context; notifications remain covered by core_message. */
final class support_data {
    public static function cases(int $userid): array {
        global $DB;
        return $DB->get_records_sql('SELECT DISTINCT c.* FROM {local_asstaff_case} c
            LEFT JOIN {local_asstaff_caseevent} e ON e.caseid = c.id
            WHERE c.requesterid = :requester OR c.recipientid = :recipient OR e.actorid = :actor ORDER BY c.id',
            ['requester' => $userid, 'recipient' => $userid, 'actor' => $userid]);
    }

    public static function export(int $userid): void {
        global $DB;
        $cases = self::cases($userid);
        $events = [];
        foreach ($cases as $case) {
            $events = array_merge($events, array_values($DB->get_records('local_asstaff_caseevent', ['caseid' => $case->id], 'id')));
        }
        \core_privacy\local\request\writer::with_context(\context_system::instance())
            ->export_data(['local_asodeltolstaff', 'support'], (object)['cases' => array_values($cases), 'events' => $events]);
    }

    public static function delete_user(int $userid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $cases = $DB->get_records('local_asstaff_case', ['requesterid' => $userid], '', 'id');
        if ($cases) {
            $DB->delete_records_list('local_asstaff_caseevent', 'caseid', array_keys($cases));
            $DB->delete_records('local_asstaff_case', ['requesterid' => $userid]);
        }
        $DB->set_field('local_asstaff_case', 'recipientid', 0, ['recipientid' => $userid]);
        // Preserve another person's case while removing the departing responder's data.
        $DB->set_field('local_asstaff_caseevent', 'body', '', ['actorid' => $userid]);
        $DB->set_field('local_asstaff_caseevent', 'actorid', 0, ['actorid' => $userid]);
        $transaction->allow_commit();
    }
}
