<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff;

/**
 * Final-grade messages use only Moodle's visible total and completion event.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(final_grade_notification::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class final_grade_notification_test extends \advanced_testcase {
    /**
     * Prepare isolated Moodle APIs and request-local notification state.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecompletion', 1);
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        final_grade_notification::reset_runtime_state_for_testing();
    }

    /**
     * Create a numeric grade through the native assignment API.
     *
     * @param bool $hidden Whether to hide the total course grade.
     * @return array Course, learner and completion object.
     */
    private function graded_completion_fixture(bool $hidden = false): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'enablecompletion' => 1,
            'showgrades' => 1,
            'fullname' => 'Synthetic final grade course',
        ]);
        $trainer = $generator->create_and_enrol($course, 'editingteacher');
        $learner = $generator->create_user(['lang' => 'es']);
        $generator->enrol_user($learner->id, $course->id, 'student');
        $activity = $generator->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $cm = get_coursemodule_from_id('assign', $activity->cmid, $course->id, false, MUST_EXIST);

        $this->setUser($trainer);
        $assignment = new \assign(\context_module::instance($cm->id), $cm, $course);
        $assignment->save_grade($learner->id, (object) [
            'grade' => 62,
            'attemptnumber' => -1,
            'addattempt' => 0,
            'workflowstate' => '',
            'applytoall' => 0,
        ]);
        if ($hidden) {
            $courseitem = \grade_item::fetch_course_item($course->id);
            $courseitem->set_hidden(1);
        }

        $completion = new \completion_completion([
            'course' => $course->id,
            'userid' => $learner->id,
        ]);
        return [$course, $learner, $completion];
    }

    /**
     * Return only this plugin's final-grade messages from a sink.
     *
     * @param \core\test\phpunit\message_sink $sink Message sink.
     * @return array Matching messages.
     */
    private function final_grade_messages(\core\test\phpunit\message_sink $sink): array {
        return array_values(array_filter($sink->get_messages(), static function(\stdClass $message): bool {
            return ($message->component ?? '') === 'local_asodeltolstaff'
                && ($message->eventtype ?? '') === 'coursegradefinalized';
        }));
    }

    public function test_completion_sends_visible_total_and_percentage_only_to_learner(): void {
        [$course, $learner, $completion] = $this->graded_completion_fixture();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $sink = $this->redirectMessages();

        $completion->mark_complete();

        $messages = $this->final_grade_messages($sink);
        $this->assertCount(1, $messages);
        $this->assertSame((int) $learner->id, (int) $messages[0]->useridto);
        $this->assertNotSame((int) $other->id, (int) $messages[0]->useridto);
        $this->assertStringContainsString('62', $messages[0]->fullmessage);
        $this->assertStringContainsString('%', $messages[0]->fullmessage);
        $this->assertStringNotContainsStringIgnoringCase('aprob', $messages[0]->fullmessage);
        $this->assertStringContainsString('id=' . $course->id, $messages[0]->contexturl);
        $this->assertStringContainsString('userid=' . $learner->id, $messages[0]->contexturl);
    }

    public function test_duplicate_completion_event_does_not_send_twice(): void {
        global $DB;

        [$course, $learner, $completion] = $this->graded_completion_fixture();
        $sink = $this->redirectMessages();
        $completionid = $completion->mark_complete();
        $record = $DB->get_record('course_completions', ['id' => $completionid], '*', MUST_EXIST);

        \core\event\course_completed::create_from_completion($record)->trigger();

        $this->assertCount(1, $this->final_grade_messages($sink));
    }

    public function test_hidden_total_is_not_exposed_in_notification(): void {
        [$course, $learner, $completion] = $this->graded_completion_fixture(true);
        $sink = $this->redirectMessages();

        $completion->mark_complete();

        $this->assertCount(0, $this->final_grade_messages($sink));
    }

    public function test_null_total_does_not_send_a_final_grade(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'showgrades' => 1]);
        $learner = $generator->create_and_enrol($course, 'student');
        $completion = new \completion_completion(['course' => $course->id, 'userid' => $learner->id]);
        $sink = $this->redirectMessages();

        $completion->mark_complete();

        $this->assertCount(0, $this->final_grade_messages($sink));
    }

    public function test_mismatched_event_cannot_read_another_course_or_user_grade(): void {
        global $DB;

        [$course, $learner, $completion] = $this->graded_completion_fixture();
        $sink = $this->redirectMessages();
        $completionid = $completion->mark_complete();
        $sink->clear();
        final_grade_notification::reset_runtime_state_for_testing();
        $othercourse = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $other = $this->getDataGenerator()->create_and_enrol($othercourse, 'student');

        $event = \core\event\course_completed::create([
            'objectid' => $completionid,
            'relateduserid' => $other->id,
            'context' => \context_course::instance($othercourse->id),
            'courseid' => $othercourse->id,
        ]);
        $event->add_record_snapshot('course_completions',
            $DB->get_record('course_completions', ['id' => $completionid], '*', MUST_EXIST));
        $event->trigger();

        $this->assertCount(0, $this->final_grade_messages($sink));
    }
}
