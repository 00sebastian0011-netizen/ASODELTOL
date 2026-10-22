<?php
// Local-only functional pilot for Moodle 5.3. Mounted read-only into the container.

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/group/lib.php');

const M07_CATEGORY_IDNUMBER = 'ASODELTOL-STAGING';
const M07_COURSE_IDNUMBER = 'ASODELTOL-AF8';
const M07_ASSIGN_IDNUMBER = 'ASODELTOL-AF8-M07-ASSIGN';
const M07_FORUM_IDNUMBER = 'ASODELTOL-AF8-M07-FORUM';
const M07_TRAINER_ROLE = 'asodeltol_capacitador_m07';
const M07_AUDITOR_ROLE = 'asodeltol_auditor_m07';

const M07_OFFICIAL_UNITS = [
    'Gestión estratégica del turismo rural sostenible y desarrollo de destinos competitivos.',
    'Diseño estratégico de experiencias turísticas, calidad del servicio y generación de valor.',
    'Marketing estratégico y gestión competitiva de destinos turísticos.',
    'Transformación digital, inteligencia artificial y tecnologías emergentes aplicadas al turismo.',
    'Desarrollo rural sostenible, economía campesina y sistemas alimentarios.',
];

function m07_fail(string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function m07_set_user(stdClass $user): void {
    \core\session\manager::set_user($user);
}

function m07_admin(): stdClass {
    $admin = get_admin();
    if (!$admin || empty($admin->id)) {
        m07_fail('No se encontró el administrador del staging local.');
    }
    m07_set_user($admin);
    return $admin;
}

function m07_course(): stdClass {
    global $DB;
    return $DB->get_record('course', ['idnumber' => M07_COURSE_IDNUMBER], '*', MUST_EXIST);
}

function m07_synthetic_password(): string {
    $password = trim((string) file_get_contents('/run/secrets/student_password'));
    if ($password === '') {
        m07_fail('La contraseña sintética local no está disponible.');
    }
    return $password;
}

function m07_user(string $username): stdClass {
    global $CFG, $DB;
    return $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ], '*', MUST_EXIST);
}

function m07_ensure_user(string $username, string $firstname, string $lastname): stdClass {
    global $CFG, $DB;

    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
    ]);
    if ($user) {
        if (!str_ends_with((string) $user->email, '@example.invalid')) {
            m07_fail("La cuenta {$username} no pertenece al dominio sintético aprobado.");
        }
        return $user;
    }

    $userid = \core_user::create_user((object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $username,
        'password' => m07_synthetic_password(),
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $username . '@example.invalid',
        'emailstop' => 1,
        'city' => 'Entorno local',
        'country' => 'CO',
        'lang' => 'en',
        'timezone' => 'America/Bogota',
    ]);
    return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
}

function m07_ensure_role(
    string $shortname,
    string $name,
    string $description,
    ?string $archetype,
    array $allowed,
    array $prohibited = []
): int {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => $shortname]);
    if (!$role) {
        $roleid = create_role($name, $shortname, $description, $archetype ?? '');
    } else {
        $roleid = (int) $role->id;
    }

    set_role_contextlevels($roleid, [CONTEXT_COURSE]);
    reset_role_capabilities($roleid);

    $systemcontext = \context_system::instance();
    foreach ($allowed as $capability) {
        role_change_permission($roleid, $systemcontext, $capability, CAP_ALLOW);
    }
    foreach ($prohibited as $capability) {
        role_change_permission($roleid, $systemcontext, $capability, CAP_PROHIBIT);
    }
    return $roleid;
}

function m07_manual_enrol(stdClass $course, stdClass $user, int $roleid): void {
    global $DB;

    $instance = $DB->get_record('enrol', [
        'courseid' => $course->id,
        'enrol' => 'manual',
        'status' => ENROL_INSTANCE_ENABLED,
    ]);
    if (!$instance) {
        m07_fail('El curso AF8 no tiene una instancia activa de matrícula manual.');
    }
    $plugin = enrol_get_plugin('manual');
    if (!$plugin) {
        m07_fail('El método de matrícula manual no está disponible.');
    }
    $plugin->enrol_user($instance, $user->id, $roleid, 0, 0, ENROL_USER_ACTIVE);
}

function m07_group(string $idnumber, int $courseid): stdClass {
    global $DB;
    $group = $DB->get_record('groups', [
        'courseid' => $courseid,
        'idnumber' => $idnumber,
    ]);
    if (!$group) {
        // Cohort sync creates the native group with the approved name, but no group idnumber.
        $group = $DB->get_record('groups', [
            'courseid' => $courseid,
            'name' => $idnumber,
        ]);
    }
    if (!$group) {
        $available = array_map(
            static fn(stdClass $record): string => (string) $record->name,
            $DB->get_records('groups', ['courseid' => $courseid], 'name ASC', 'id,name')
        );
        m07_fail("No existe el grupo esperado {$idnumber} en el curso AF8. Disponibles: " . implode(', ', $available));
    }
    return $group;
}

function m07_normalize_group_names(stdClass $course): void {
    global $DB;

    for ($number = 1; $number <= 7; $number++) {
        $code = sprintf('AF8-G%02d', $number);
        $group = $DB->get_record('groups', [
            'courseid' => $course->id,
            'idnumber' => $code,
        ]);
        if (!$group) {
            $group = $DB->get_record('groups', [
                'courseid' => $course->id,
                'name' => $code,
            ]);
        }
        if (!$group) {
            $group = $DB->get_record('groups', [
                'courseid' => $course->id,
                'name' => $code . ' cohort',
            ]);
        }
        if (!$group) {
            m07_fail("No se encontró el grupo nativo correspondiente a {$code}.");
        }
        if ($group->name !== $code || $group->idnumber !== $code) {
            $group->name = $code;
            $group->idnumber = $code;
            groups_update_group($group);
        }
    }
}

function m07_ensure_staff(stdClass $course): void {
    $trainerrole = m07_ensure_role(
        M07_TRAINER_ROLE,
        'Capacitador sintético M07',
        'Rol local de prueba basado en profesor sin permiso de edición.',
        'teacher',
        [],
        ['moodle/site:accessallgroups']
    );
    $auditorrole = m07_ensure_role(
        M07_AUDITOR_ROLE,
        'Auditor sintético M07',
        'Rol local de consulta transversal sin capacidades de modificación.',
        null,
        [
            'moodle/course:view',
            'moodle/course:viewparticipants',
            'moodle/grade:viewall',
            'moodle/site:accessallgroups',
            'moodle/user:viewdetails',
            'gradereport/grader:view',
            'gradereport/user:view',
            'mod/assign:view',
            'mod/assign:viewgrades',
            'mod/forum:viewdiscussion',
        ]
    );

    foreach ([1, 2] as $number) {
        $user = m07_ensure_user(
            sprintf('af8.synthetic.trainer.g%02d', $number),
            'Capacitador',
            sprintf('Sintético G%02d', $number)
        );
        m07_manual_enrol($course, $user, $trainerrole);
        $group = m07_group(sprintf('AF8-G%02d', $number), (int) $course->id);
        groups_add_member($group, $user);
    }

    $auditor = m07_ensure_user('af8.synthetic.auditor', 'Auditor', 'Sintético');
    m07_manual_enrol($course, $auditor, $auditorrole);
}

function m07_ensure_assignment(stdClass $course): cm_info {
    global $DB;

    $cmrecord = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => M07_ASSIGN_IDNUMBER,
    ]);
    if (!$cmrecord) {
        [, , , , $data] = prepare_new_moduleinfo_data($course, 'assign', 1);
        $data->name = 'Actividad evaluable sintética M07';
        $data->cmidnumber = M07_ASSIGN_IDNUMBER;
        $data->introeditor = [
            'text' => 'Prueba técnica del flujo de entrega, calificación y avance. No reemplaza ni altera contenidos académicos oficiales.',
            'format' => FORMAT_PLAIN,
            'itemid' => 0,
        ];
        $data->showdescription = 0;
        $data->alwaysshowdescription = 1;
        $data->allowsubmissionsfromdate = 0;
        $data->duedate = 0;
        $data->cutoffdate = 0;
        $data->gradingduedate = 0;
        $data->timelimit = 0;
        $data->grade = 100;
        $data->submissiondrafts = 0;
        $data->requiresubmissionstatement = 0;
        $data->sendnotifications = 0;
        $data->sendlatenotifications = 0;
        $data->sendstudentnotifications = 0;
        $data->teamsubmission = 0;
        $data->requireallteammemberssubmit = 0;
        $data->blindmarking = 0;
        $data->hidegrader = 0;
        $data->markingworkflow = 0;
        $data->markingallocation = 0;
        $data->attemptreopenmethod = 'none';
        $data->maxattempts = -1;
        $data->preventsubmissionnotingroup = 1;
        $data->assignsubmission_file_enabled = 0;
        $data->assignsubmission_onlinetext_enabled = 1;
        $data->assignsubmission_onlinetext_wordlimit_enabled = 0;
        $data->assignfeedback_comments_enabled = 1;
        $data->assignfeedback_editpdf_enabled = 0;
        $data->assignfeedback_file_enabled = 0;
        $data->visible = 1;
        $data->groupmode = SEPARATEGROUPS;
        $data->completion = COMPLETION_TRACKING_AUTOMATIC;
        $data->completionusegrade = 1;
        $data->completionpassgrade = 0;
        $moduleinfo = add_moduleinfo($data, $course);
        $cmrecord = $DB->get_record('course_modules', ['id' => $moduleinfo->coursemodule], '*', MUST_EXIST);
    }
    return get_fast_modinfo($course)->get_cm((int) $cmrecord->id);
}

function m07_ensure_forum(stdClass $course): cm_info {
    global $DB;

    $cmrecord = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => M07_FORUM_IDNUMBER,
    ]);
    if (!$cmrecord) {
        [, , , , $data] = prepare_new_moduleinfo_data($course, 'forum', 1);
        $data->name = 'Foro sintético de aislamiento M07';
        $data->cmidnumber = M07_FORUM_IDNUMBER;
        $data->introeditor = [
            'text' => 'Foro técnico temporal para comprobar aislamiento entre grupos con cuentas sintéticas.',
            'format' => FORMAT_PLAIN,
            'itemid' => 0,
        ];
        $data->type = 'general';
        $data->assessed = 0;
        $data->scale = 0;
        $data->forcesubscribe = 0;
        $data->trackingtype = 1;
        $data->maxattachments = 1;
        $data->maxbytes = 0;
        $data->displaywordcount = 0;
        $data->warnafter = 0;
        $data->blockafter = 0;
        $data->blockperiod = 0;
        $data->rssarticles = 0;
        $data->rsstype = 0;
        $data->completion = COMPLETION_TRACKING_NONE;
        $data->visible = 1;
        $data->groupmode = SEPARATEGROUPS;
        $moduleinfo = add_moduleinfo($data, $course);
        $cmrecord = $DB->get_record('course_modules', ['id' => $moduleinfo->coursemodule], '*', MUST_EXIST);
    }
    return get_fast_modinfo($course)->get_cm((int) $cmrecord->id);
}

function m07_setup(): void {
    global $DB;

    m07_admin();
    $course = m07_course();
    $category = \core_course_category::get((int) $course->category);
    if (!$category->visible) {
        $category->show();
    }

    if (!(int) $course->visible) {
        course_change_visibility((int) $course->id, true);
    }
    if (!(int) $course->enablecompletion) {
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
    }
    if ((int) $course->groupmode !== SEPARATEGROUPS || !(int) $course->groupmodeforce) {
        update_course((object) [
            'id' => $course->id,
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => 1,
        ]);
    }
    rebuild_course_cache((int) $course->id, true);
    $course = m07_course();

    $sectionrecords = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
    $sections = [];
    foreach ($sectionrecords as $sectionrecord) {
        $sections[(int) $sectionrecord->section] = $sectionrecord;
    }
    foreach (M07_OFFICIAL_UNITS as $index => $officialname) {
        $sectionnumber = $index + 1;
        if (!isset($sections[$sectionnumber]) || $sections[$sectionnumber]->name !== $officialname) {
            $actual = isset($sections[$sectionnumber]) ? (string) $sections[$sectionnumber]->name : '(ausente)';
            m07_fail("La UT{$sectionnumber} no conserva su denominación oficial. Valor actual: {$actual}");
        }
    }

    m07_normalize_group_names($course);
    m07_ensure_staff($course);
    $assigncm = m07_ensure_assignment($course);
    $forumcm = m07_ensure_forum($course);
    rebuild_course_cache((int) $course->id, true);

    echo json_encode([
        'status' => 'ready',
        'course_visible' => true,
        'category_visible' => true,
        'assignment_cm_id' => $assigncm->id,
        'forum_cm_id' => $forumcm->id,
        'synthetic_only' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function m07_lockdown(): void {
    m07_admin();
    $course = m07_course();
    if ((int) $course->visible) {
        course_change_visibility((int) $course->id, false);
    }
    $category = \core_course_category::get((int) $course->category);
    if ($category->visible) {
        $category->hide();
    }
    rebuild_course_cache((int) $course->id, true);
    echo json_encode([
        'status' => 'locked_down',
        'course_visible' => false,
        'category_visible' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Return group identifiers assigned to a user in the pilot course.
 *
 * @return list<string>
 */
function m07_user_groups(int $courseid, int $userid): array {
    $idnumbers = array_map(
        static fn(stdClass $group): string => $group->idnumber !== '' ? (string) $group->idnumber : (string) $group->name,
        groups_get_all_groups($courseid, $userid, 0, 'g.id,g.idnumber,g.name')
    );
    sort($idnumbers);
    return array_values($idnumbers);
}

function m07_assignment_record(stdClass $course): array {
    global $DB;

    $cmid = $DB->get_field('course_modules', 'id', [
        'course' => $course->id,
        'idnumber' => M07_ASSIGN_IDNUMBER,
    ], MUST_EXIST);
    $cm = get_coursemodule_from_id('assign', (int) $cmid, $course->id, false, MUST_EXIST);
    $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
    return [$cm, $assign];
}

function m07_forum_record(stdClass $course): array {
    global $DB;

    $cmid = $DB->get_field('course_modules', 'id', [
        'course' => $course->id,
        'idnumber' => M07_FORUM_IDNUMBER,
    ], MUST_EXIST);
    $cm = get_coursemodule_from_id('forum', (int) $cmid, $course->id, false, MUST_EXIST);
    $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
    return [$cm, $forum];
}

function m07_exercise(): void {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/assign/externallib.php');
    require_once($CFG->dirroot . '/mod/forum/lib.php');

    $admin = m07_admin();
    $course = m07_course();
    [, $assign] = m07_assignment_record($course);
    [, $forum] = m07_forum_record($course);

    $grades = [1 => 83.0, 2 => 97.0];
    foreach ($grades as $number => $grade) {
        $student = m07_user(sprintf('af8.synthetic.g%02d', $number));
        m07_set_user($student);
        $warnings = mod_assign_external::save_submission((int) $assign->id, [
            'onlinetext_editor' => [
                'text' => sprintf('<p>M07-G%02d-SUBMISSION-MARKER. Synthetic submission for isolation testing.</p>', $number),
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
        ]);
        if ($warnings !== []) {
            m07_fail("Moodle devolvió advertencias al guardar la entrega sintética G{$number}.");
        }

        $trainer = m07_user(sprintf('af8.synthetic.trainer.g%02d', $number));
        m07_set_user($trainer);
        mod_assign_external::save_grade(
            (int) $assign->id,
            (int) $student->id,
            $grade,
            -1,
            false,
            '',
            false,
            ['assignfeedbackcomments_editor' => [
                'text' => sprintf('Retroalimentación sintética G%02d; sin contenido académico.', $number),
                'format' => FORMAT_PLAIN,
            ]]
        );
    }

    $studentg02 = m07_user('af8.synthetic.g02');
    $groupg02 = m07_group('AF8-G02', (int) $course->id);
    $discussion = $DB->get_record('forum_discussions', [
        'forum' => $forum->id,
        'name' => 'M07-G02-PRIVATE-DISCUSSION',
    ]);
    if (!$discussion) {
        m07_set_user($studentg02);
        $discussionid = forum_add_discussion((object) [
            'course' => $course->id,
            'forum' => $forum->id,
            'name' => 'M07-G02-PRIVATE-DISCUSSION',
            'message' => 'M07-G02-DIRECT-ACCESS-DENIED-MARKER',
            'messageformat' => FORMAT_PLAIN,
            'messagetrust' => 0,
            'attachments' => 0,
            'mailnow' => 0,
            'groupid' => $groupg02->id,
        ], null, null, (int) $studentg02->id);
        if (!$discussionid) {
            m07_fail('No fue posible crear la discusión sintética de G02.');
        }
    }

    m07_set_user($admin);
    echo json_encode([
        'status' => 'exercised',
        'submissions' => 2,
        'grades' => ['G01' => 83.0, 'G02' => 97.0],
        'forum_discussion' => 'G02-only synthetic thread',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function m07_grade_value(int $courseid, int $assignid, int $userid): ?float {
    global $DB;
    $item = $DB->get_record('grade_items', [
        'courseid' => $courseid,
        'itemmodule' => 'assign',
        'iteminstance' => $assignid,
    ], '*', MUST_EXIST);
    $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
    return $grade && $grade->finalgrade !== null ? (float) $grade->finalgrade : null;
}

function m07_snapshot(): void {
    global $CFG, $DB;

    m07_admin();
    $course = m07_course();
    $category = \core_course_category::get((int) $course->category);
    [$assigncm, $assign] = m07_assignment_record($course);
    [$forumcm, $forum] = m07_forum_record($course);
    $assigncontext = \context_module::instance((int) $assigncm->id);
    $coursecontext = \context_course::instance((int) $course->id);

    $units = [];
    foreach (M07_OFFICIAL_UNITS as $index => $officialname) {
        $sectionnumber = $index + 1;
        $section = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $sectionnumber,
        ], '*', MUST_EXIST);
        $units[] = [
            'code' => "UT{$sectionnumber}",
            'official_name_match' => $section->name === $officialname,
            'visible' => (bool) $section->visible,
        ];
    }

    $groups = [];
    for ($number = 1; $number <= 7; $number++) {
        $code = sprintf('AF8-G%02d', $number);
        $student = m07_user(sprintf('af8.synthetic.g%02d', $number));
        $group = m07_group($code, (int) $course->id);
        $groups[] = [
            'code' => $code,
            'group_id' => (int) $group->id,
            'synthetic_student_enrolled' => is_enrolled($coursecontext, $student, '', true),
            'membership_exact' => m07_user_groups((int) $course->id, (int) $student->id) === [$code],
            'member_count' => groups_get_members($group->id, 'u.id') ? count(groups_get_members($group->id, 'u.id')) : 0,
        ];
    }

    $actors = [];
    foreach ([
        'student_g01' => 'af8.synthetic.g01',
        'student_g02' => 'af8.synthetic.g02',
        'trainer_g01' => 'af8.synthetic.trainer.g01',
        'trainer_g02' => 'af8.synthetic.trainer.g02',
        'auditor' => 'af8.synthetic.auditor',
    ] as $label => $username) {
        $user = m07_user($username);
        $actors[$label] = [
            'user_id' => (int) $user->id,
            'display_name' => fullname($user),
            'groups' => m07_user_groups((int) $course->id, (int) $user->id),
            'can_grade' => has_capability('mod/assign:grade', $assigncontext, $user),
            'can_view_grades' => has_capability('mod/assign:viewgrades', $assigncontext, $user),
            'can_view_all_course_grades' => has_capability('moodle/grade:viewall', $coursecontext, $user),
            'can_access_all_groups' => has_capability('moodle/site:accessallgroups', $coursecontext, $user),
            'can_manage_activities' => has_capability('moodle/course:manageactivities', $coursecontext, $user),
            'can_update_users' => has_capability('moodle/user:update', \context_system::instance(), $user),
        ];
    }

    $submissions = [];
    foreach ([1, 2] as $number) {
        $student = m07_user(sprintf('af8.synthetic.g%02d', $number));
        $submission = $DB->get_record('assign_submission', [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'latest' => 1,
        ]);
        $grade = m07_grade_value((int) $course->id, (int) $assign->id, (int) $student->id);
        $completion = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $assigncm->id,
            'userid' => $student->id,
        ]);
        $submissions[sprintf('G%02d', $number)] = [
            'status' => $submission ? $submission->status : null,
            'grade' => $grade,
            'gradebook_recorded' => $grade !== null,
            'completion_state' => $completion ? (int) $completion->completionstate : 0,
        ];
    }

    $discussion = $DB->get_record('forum_discussions', [
        'forum' => $forum->id,
        'name' => 'M07-G02-PRIVATE-DISCUSSION',
    ]);

    echo json_encode([
        'runtime' => [
            'release' => $CFG->release,
            'wwwroot' => $CFG->wwwroot,
            'course_visible' => (bool) $course->visible,
            'category_visible' => (bool) $category->visible,
            'separate_groups' => (int) $course->groupmode === SEPARATEGROUPS,
            'group_mode_forced' => (bool) $course->groupmodeforce,
            'outbound_mail_disabled' => (bool) get_config('core', 'noemailever'),
        ],
        'course' => [
            'course_id' => (int) $course->id,
            'units' => $units,
            'groups' => $groups,
        ],
        'activities' => [
            'assignment' => [
                'cm_id' => (int) $assigncm->id,
                'instance_id' => (int) $assign->id,
                'idnumber' => M07_ASSIGN_IDNUMBER,
                'section' => (int) get_fast_modinfo($course)->get_cm((int) $assigncm->id)->sectionnum,
            ],
            'forum' => [
                'cm_id' => (int) $forumcm->id,
                'instance_id' => (int) $forum->id,
                'discussion_id' => $discussion ? (int) $discussion->id : null,
            ],
        ],
        'actors' => $actors,
        'submissions' => $submissions,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$options = getopt('', ['phase:']);
$phase = $options['phase'] ?? '';

switch ($phase) {
    case 'setup':
        m07_setup();
        break;
    case 'exercise':
        m07_exercise();
        break;
    case 'snapshot':
        m07_snapshot();
        break;
    case 'lockdown':
        m07_lockdown();
        break;
    default:
        m07_fail('Use --phase=setup, --phase=exercise, --phase=snapshot o --phase=lockdown.');
}
