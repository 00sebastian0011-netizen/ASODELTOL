<?php
namespace local_asodeltolstaff;

/** REQ-13-02: validated persistence for the nine training-action fields. */
final class course_action {
    public static function save(\stdClass $data): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $course = get_course((int)$data->id);
        $context = \context_course::instance($course->id);
        require_capability('moodle/course:update', $context);
        foreach (['idnumber', 'fullname', 'summary', 'lang', 'adminroleid', 'auditorroleid'] as $field) {
            if (trim((string)($data->{$field} ?? '')) === '') {
                throw new \invalid_parameter_exception('Missing required field: ' . $field);
            }
        }
        if ((int)$data->enddate <= (int)$data->startdate) {
            throw new \invalid_parameter_exception('End date must be later than start date');
        }
        $transaction = $DB->start_delegated_transaction();
        try {
            update_course((object)[
                'id' => $course->id, 'idnumber' => trim($data->idnumber),
                'fullname' => trim($data->fullname), 'summary' => $data->summary,
                'summaryformat' => FORMAT_HTML, 'visible' => (int)$data->visible,
                'startdate' => (int)$data->startdate, 'enddate' => (int)$data->enddate,
                'lang' => trim($data->lang),
            ]);
            metadata_fields::configure_for_context($context);
            $handler = \core_customfield\handler::get_handler('core_course', 'course');
            $handler->instance_form_save((object)[
                'id' => $course->id,
                'customfield_asodeltol_adminroleid' => trim($data->adminroleid),
                'customfield_asodeltol_auditorroleid' => trim($data->auditorroleid),
            ]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return get_course($course->id);
    }
}
