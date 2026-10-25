<?php
// This file is part of Moodle - http://moodle.org/
namespace local_asodeltolstaff;

/** Native enrollment/completion exports with the plugin's existing group scope. */
final class report_manager {
    /** Column identifiers and localized headings. */
    public static function columns(string $dataset): array {
        $common = ['trainingactioncode', 'trainingaction'];
        $fields = match ($dataset) {
            'participants' => ['group', 'username', 'student', 'enrolmentstate', 'moodlecompleted', 'completionutc'],
            'consolidated' => ['groupcount', 'registeredcount', 'activecount', 'moodlecompletedcount', 'completionenabled'],
            'access' => ['trainer', 'group', 'rowtype', 'loginutc', 'logoututc', 'coursestartutc', 'courseendutc',
                'officehoursstartutc', 'officehoursendutc', 'sourcerecord'],
            default => throw new \invalid_parameter_exception('Unknown report dataset'),
        };
        $columns = [];
        foreach (array_merge($common, $fields) as $field) {
            $columns[$field] = get_string('report_' . $field, 'local_asodeltolstaff');
        }
        return $columns;
    }

    /**
     * Return actual Moodle records, not inferred attendance or success rules.
     * A student in several groups is counted once in the course consolidated row.
     */
    public static function rows(int $courseid, string $dataset): array {
        global $DB;
        self::columns($dataset); // Strict allowlist, including API callers.
        $context = \context_course::instance($courseid);
        require_capability('local/asodeltolstaff:export', $context);
        if ($dataset === 'access') {
            return access_report::rows($courseid);
        }
        $groups = manager::get_available_groups($courseid);
        $viewall = manager::can_view_all($context);
        if (!$viewall && !$groups) {
            return [];
        }
        $filter = $viewall ? 0 : array_keys($groups);
        $fields = 'u.id,u.username,u.suspended,' . implode(',', array_map(
            static fn(string $field): string => 'u.' . $field,
            \core_user\fields::get_name_fields()
        ));
        $users = get_enrolled_users($context, 'local/asodeltolstaff:beattended', $filter, $fields, 'u.id');
        $active = get_enrolled_users($context, 'local/asodeltolstaff:beattended', $filter, 'u.id', 'u.id', 0, 0, true);
        $course = get_course($courseid);
        $common = ['trainingactioncode' => (string) ($course->idnumber ?: $course->shortname),
                   'trainingaction' => (string) $course->fullname];
        $memberships = [];
        if ($groups) {
            foreach ($DB->get_records_list('groups_members', 'groupid', array_keys($groups), '', 'id,groupid,userid') as $member) {
                $memberships[$member->userid][] = $member->groupid;
            }
        }
        $completions = [];
        if ($users && !empty($course->enablecompletion)) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'learner');
            $params['course'] = $courseid;
            foreach ($DB->get_records_select('course_completions',
                    "course = :course AND userid $insql AND timecompleted IS NOT NULL AND timecompleted > 0",
                    $params, '', 'id,userid,timecompleted') as $completion) {
                $completions[$completion->userid] = $completion->timecompleted;
            }
        }
        $activecount = 0;
        foreach ($users as $user) {
            if (isset($active[$user->id]) && !$user->suspended) {
                $activecount++;
            }
        }
        if ($dataset === 'consolidated') {
            return [$common + ['groupcount' => count($groups), 'registeredcount' => count($users),
                'activecount' => $activecount, 'moodlecompletedcount' => count($completions),
                'completionenabled' => empty($course->enablecompletion) ? 0 : 1]];
        }
        $rows = [];
        foreach ($users as $user) {
            foreach ($memberships[$user->id] ?? [0] as $groupid) {
                $rows[] = $common + ['group' => $groupid ? $groups[$groupid]->name : '',
                    'username' => $user->username, 'student' => fullname($user),
                    'enrolmentstate' => isset($active[$user->id]) && !$user->suspended ? 'active' : 'inactive',
                    'moodlecompleted' => isset($completions[$user->id]) ? 1 : 0,
                    'completionutc' => isset($completions[$user->id])
                        ? gmdate('Y-m-d\TH:i:s\Z', (int) $completions[$user->id]) : ''];
            }
        }
        return $rows;
    }
}
