<?php
// Local-only acceptance fixture for local_asodeltolstaff on Moodle 5.3.

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

const M11_COURSE_IDNUMBER = 'ASODELTOL-AF8';
const M11_CATEGORY_IDNUMBER = 'ASODELTOL-STAGING';
const M11_TRAINER_ROLE = 'asodeltol_capacitador_m07';
const M11_AUDITOR_ROLE = 'asodeltol_auditor_m07';
const M11_SESSION_G01 = 'M11-AF8-G01-01';
const M11_SESSION_G02 = 'M11-AF8-G02-01';

/**
 * Terminate with a safe diagnostic.
 *
 * @param string $message Message.
 * @return never
 */
function m11_fail(string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

/**
 * Set the current synthetic actor.
 *
 * @param stdClass $user User.
 */
function m11_set_user(stdClass $user): void {
    \core\session\manager::set_user($user);
}

/**
 * Get the staging administrator.
 *
 * @return stdClass
 */
function m11_admin(): stdClass {
    $admin = get_admin();
    if (!$admin || empty($admin->id)) {
        m11_fail('No se encontró el administrador local.');
    }
    return $admin;
}

/**
 * Get AF8 without creating or changing academic content.
 *
 * @return stdClass
 */
function m11_course(): stdClass {
    global $DB;

    return $DB->get_record('course', ['idnumber' => M11_COURSE_IDNUMBER], '*', MUST_EXIST);
}

/**
 * Get an existing synthetic account.
 *
 * @param string $username Username.
 * @return stdClass
 */
function m11_user(string $username): stdClass {
    global $CFG, $DB;

    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ], '*', MUST_EXIST);
    if (!str_ends_with((string) $user->email, '@example.invalid')) {
        m11_fail("La cuenta {$username} no es sintética.");
    }
    return $user;
}

/**
 * Get one existing AF8 group.
 *
 * @param int $number Group number.
 * @param int $courseid Course id.
 * @return stdClass
 */
function m11_group(int $number, int $courseid): stdClass {
    global $DB;

    $code = sprintf('AF8-G%02d', $number);
    $group = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $code]);
    if (!$group) {
        $group = $DB->get_record('groups', ['courseid' => $courseid, 'name' => $code]);
    }
    if (!$group) {
        m11_fail("No existe el grupo sintético {$code}.");
    }
    return $group;
}

/**
 * Add plugin permissions to an existing synthetic role without changing core roles.
 *
 * @param string $shortname Role shortname.
 * @param list<string> $allowed Allowed capabilities.
 * @param list<string> $prohibited Prohibited capabilities.
 */
function m11_configure_role(string $shortname, array $allowed, array $prohibited = []): void {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => $shortname], '*', MUST_EXIST);
    $systemcontext = context_system::instance();
    foreach ($allowed as $capability) {
        role_change_permission((int) $role->id, $systemcontext, $capability, CAP_ALLOW);
    }
    foreach ($prohibited as $capability) {
        role_change_permission((int) $role->id, $systemcontext, $capability, CAP_PROHIBIT);
    }
}

/**
 * Return a plugin session by stable technical code.
 *
 * @param string $code Session code.
 * @return stdClass|null
 */
function m11_session_by_code(string $code): ?stdClass {
    global $DB;

    $course = m11_course();
    $record = $DB->get_record('local_asstaff_session', [
        'courseid' => $course->id,
        'sessioncode' => $code,
    ]);
    return $record ?: null;
}

/**
 * Create or update one deterministic synthetic session as its trainer.
 *
 * @param int $number Group number.
 * @param string $code Technical code.
 * @param string $scheduledstart Explicit UTC instant.
 * @param string $scheduledend Explicit UTC instant.
 * @param string $reportedstart Explicit UTC instant.
 * @param string $reportedend Explicit UTC instant.
 * @return stdClass
 */
function m11_upsert_session(
    int $number,
    string $code,
    string $scheduledstart,
    string $scheduledend,
    string $reportedstart,
    string $reportedend
): stdClass {
    $course = m11_course();
    $group = m11_group($number, (int) $course->id);
    $trainer = m11_user(sprintf('af8.synthetic.trainer.g%02d', $number));
    m11_set_user($trainer);
    $existing = m11_session_by_code($code);
    return \local_asodeltolstaff\manager::save_session((object) [
        'id' => $existing ? (int) $existing->id : 0,
        'courseid' => (int) $course->id,
        'sectionnum' => $number,
        'groupid' => (int) $group->id,
        'trainerid' => (int) $trainer->id,
        'sessioncode' => $code,
        'scheduledstart' => strtotime($scheduledstart),
        'scheduledend' => strtotime($scheduledend),
        'reportedstart' => strtotime($reportedstart),
        'reportedend' => strtotime($reportedend),
        'evidenceref' => sprintf('https://evidence.example.invalid/m11/g%02d/session-01', $number),
        'reason' => 'Escenario sintético M11; no constituye política institucional.',
    ]);
}

/**
 * Prepare role permissions, temporary visibility and deterministic sessions.
 */
function m11_setup(): void {
    global $DB;

    $admin = m11_admin();
    m11_set_user($admin);
    $course = m11_course();
    $category = core_course_category::get((int) $course->category);
    if (!$category->visible) {
        $category->show();
    }
    if (!(int) $course->visible) {
        course_change_visibility((int) $course->id, true);
    }

    m11_configure_role(M11_TRAINER_ROLE, [
        'local/asodeltolstaff:viewsessions',
        'local/asodeltolstaff:manageown',
        'local/asodeltolstaff:viewevidence',
        'local/asodeltolstaff:export',
    ], [
        'local/asodeltolstaff:viewall',
        'local/asodeltolstaff:validate',
    ]);
    m11_configure_role(M11_AUDITOR_ROLE, [
        'local/asodeltolstaff:viewsessions',
        'local/asodeltolstaff:viewall',
        'local/asodeltolstaff:viewevidence',
        'local/asodeltolstaff:export',
    ], [
        'local/asodeltolstaff:manageown',
        'local/asodeltolstaff:validate',
    ]);
    accesslib_clear_all_caches(true);

    $g01 = m11_upsert_session(
        1,
        M11_SESSION_G01,
        '2026-11-03T14:00:00Z',
        '2026-11-03T16:00:00Z',
        '2026-11-03T14:05:00Z',
        '2026-11-03T15:50:00Z'
    );
    $g02 = m11_upsert_session(
        2,
        M11_SESSION_G02,
        '2026-11-04T14:00:00Z',
        '2026-11-04T16:00:00Z',
        '2026-11-04T14:10:00Z',
        '2026-11-04T15:55:00Z'
    );

    m11_set_user($admin);
    echo json_encode([
        'status' => 'ready',
        'courseid' => (int) $course->id,
        'sessions' => ['G01' => (int) $g01->id, 'G02' => (int) $g02->id],
        'synthetic_only' => true,
        'course_visible_temporarily' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Record attendance, an auditable correction, and admin validation.
 */
function m11_exercise(): void {
    $g01 = m11_session_by_code(M11_SESSION_G01);
    $g02 = m11_session_by_code(M11_SESSION_G02);
    if (!$g01 || !$g02) {
        m11_fail('Ejecute primero la fase setup.');
    }
    $trainer1 = m11_user('af8.synthetic.trainer.g01');
    $trainer2 = m11_user('af8.synthetic.trainer.g02');
    $student1 = m11_user('af8.synthetic.g01');
    $student2 = m11_user('af8.synthetic.g02');

    m11_set_user($trainer1);
    \local_asodeltolstaff\manager::save_attendance(
        (int) $g01->id,
        [(int) $student1->id => 'reported_late'],
        'Primera declaración sintética M11.'
    );
    \local_asodeltolstaff\manager::save_attendance(
        (int) $g01->id,
        [(int) $student1->id => 'reported_present'],
        'Corrección sintética M11 para comprobar historial; no es una política.'
    );

    m11_set_user($trainer2);
    \local_asodeltolstaff\manager::save_attendance(
        (int) $g02->id,
        [(int) $student2->id => 'reported_absent'],
        'Declaración sintética M11; estado provisional.'
    );

    $admin = m11_admin();
    m11_set_user($admin);
    \local_asodeltolstaff\manager::validate_session(
        (int) $g01->id,
        \local_asodeltolstaff\manager::VALIDATION_VALIDATED,
        100,
        'Regla técnica M11: valor positivo no superior a los 105 minutos reportados.'
    );

    echo json_encode([
        'status' => 'exercised',
        'attendance_records' => 2,
        'g01_validated_minutes' => 100,
        'policy_definitive' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Execute one callable and return whether Moodle denied/rejected it.
 *
 * @param callable $operation Operation.
 * @return bool
 */
function m11_is_rejected(callable $operation): bool {
    try {
        $operation();
        return false;
    } catch (Throwable $exception) {
        return $exception instanceof moodle_exception
            || $exception instanceof required_capability_exception
            || $exception instanceof dml_exception;
    }
}

/**
 * Verify service-level positive and negative controls and print a snapshot.
 */
function m11_verify(): void {
    global $DB;

    $course = m11_course();
    $context = context_course::instance((int) $course->id);
    $g01 = m11_session_by_code(M11_SESSION_G01);
    $g02 = m11_session_by_code(M11_SESSION_G02);
    if (!$g01 || !$g02) {
        m11_fail('Faltan las sesiones sintéticas M11.');
    }
    $trainer1 = m11_user('af8.synthetic.trainer.g01');
    $student1 = m11_user('af8.synthetic.g01');
    $auditor = m11_user('af8.synthetic.auditor');
    $admin = m11_admin();
    $controls = [];
    $add = static function (string $id, bool $passed, string $detail) use (&$controls): void {
        $controls[] = ['id' => $id, 'passed' => $passed, 'detail' => $detail];
    };

    $attendance1 = $DB->get_record('local_asstaff_attendance', [
        'sessionid' => $g01->id,
        'studentid' => $student1->id,
    ]);
    $add('M11-POS-001', $attendance1 && $attendance1->status === 'reported_present',
        'La asistencia provisional G01 conserva la corrección más reciente.');
    $add('M11-POS-002', $g01->validationstatus === 'validated' && (int) $g01->validatedminutes === 100,
        'Administración validó 100 minutos dentro de 105 reportados.');

    m11_set_user($trainer1);
    $trainerrows = \local_asodeltolstaff\manager::get_report_rows((int) $course->id);
    $trainercodes = array_unique(array_column($trainerrows, 'sessioncode'));
    $add('M11-ISO-001', $trainercodes === [M11_SESSION_G01],
        'El capacitador G01 sólo obtiene filas de su grupo.');
    $add('M11-NEG-001', m11_is_rejected(static function () use ($g02, $student1): void {
        \local_asodeltolstaff\manager::save_attendance(
            (int) $g02->id,
            [(int) $student1->id => 'reported_present'],
            'Intento negativo sintético.'
        );
    }), 'El capacitador G01 no modifica G02.');
    $add('M11-NEG-002', m11_is_rejected(static function () use ($g01): void {
        \local_asodeltolstaff\manager::validate_session(
            (int) $g01->id,
            \local_asodeltolstaff\manager::VALIDATION_VALIDATED,
            100,
            'Intento negativo sintético.'
        );
    }), 'El capacitador no autovalida su evidencia.');
    $attendancehistorybefore = count(\local_asodeltolstaff\manager::get_history_records((int) $g01->id));
    $add('M11-NEG-005', m11_is_rejected(static function () use ($g01, $student1): void {
        \local_asodeltolstaff\manager::save_attendance(
            (int) $g01->id,
            [(int) $student1->id => 'reported_absent'],
            '   '
        );
    }) && $DB->get_field('local_asstaff_attendance', 'status', [
        'sessionid' => $g01->id,
        'studentid' => $student1->id,
    ]) === 'reported_present'
        && count(\local_asodeltolstaff\manager::get_history_records((int) $g01->id)) === $attendancehistorybefore,
        'Una corrección de asistencia sin motivo se rechaza sin modificar datos ni historial.');
    $sessionhistorybefore = count(\local_asodeltolstaff\manager::get_history_records((int) $g01->id));
    $add('M11-NEG-006', m11_is_rejected(static function () use ($g01): void {
        \local_asodeltolstaff\manager::save_session((object) [
            'id' => (int) $g01->id,
            'courseid' => (int) $g01->courseid,
            'sectionnum' => (int) $g01->sectionnum,
            'groupid' => (int) $g01->groupid,
            'trainerid' => (int) $g01->trainerid,
            'sessioncode' => (string) $g01->sessioncode,
            'scheduledstart' => (int) $g01->scheduledstart,
            'scheduledend' => (int) $g01->scheduledend,
            'reportedstart' => (int) $g01->reportedstart,
            'reportedend' => (int) $g01->reportedend,
            'evidenceref' => (string) $g01->evidenceref,
            'reason' => '',
        ]);
    }) && \local_asodeltolstaff\manager::get_session((int) $g01->id)->validationstatus === 'validated'
        && count(\local_asodeltolstaff\manager::get_history_records((int) $g01->id)) === $sessionhistorybefore,
        'Una edición de sesión sin motivo se rechaza sin reiniciar la validación ni alterar el historial.');
    $group1 = m11_group(1, (int) $course->id);
    $add('M11-NEG-003', m11_is_rejected(static function () use ($course, $group1, $trainer1): void {
        \local_asodeltolstaff\manager::save_session((object) [
            'courseid' => $course->id,
            'sectionnum' => 1,
            'groupid' => $group1->id,
            'trainerid' => $trainer1->id,
            'sessioncode' => 'M11-INVALID-TIME',
            'scheduledstart' => strtotime('2026-11-05T16:00:00Z'),
            'scheduledend' => strtotime('2026-11-05T15:00:00Z'),
            'reportedstart' => null,
            'reportedend' => null,
            'evidenceref' => '',
            'reason' => 'Intento negativo sintético.',
        ]);
    }), 'Se rechaza un horario inconsistente.');

    m11_set_user($student1);
    $add('M11-NEG-004', m11_is_rejected(static function () use ($g01, $student1): void {
        \local_asodeltolstaff\manager::save_attendance(
            (int) $g01->id,
            [(int) $student1->id => 'reported_present'],
            'Intento negativo sintético.'
        );
    }), 'El estudiante no registra ni modifica asistencia.');
    $add('M11-SEC-001', !has_capability('local/asodeltolstaff:viewevidence', $context, $student1),
        'El estudiante no tiene acceso a referencias de evidencia.');

    m11_set_user($auditor);
    $add('M11-SEC-002', has_capability('local/asodeltolstaff:viewall', $context, $auditor)
        && has_capability('local/asodeltolstaff:export', $context, $auditor)
        && !has_capability('local/asodeltolstaff:validate', $context, $auditor),
        'Auditoría consulta y exporta transversalmente, sin validar ni modificar.');

    m11_set_user($admin);
    $group2 = m11_group(2, (int) $course->id);
    $trainer2 = m11_user('af8.synthetic.trainer.g02');
    $groupchangehistorybefore = count(\local_asodeltolstaff\manager::get_history_records((int) $g01->id));
    $groupchangeattendancebefore = array_keys($DB->get_records(
        'local_asstaff_attendance',
        ['sessionid' => $g01->id],
        'id',
        'id'
    ));
    $groupchangerejected = m11_is_rejected(static function () use ($g01, $group2, $trainer2): void {
        \local_asodeltolstaff\manager::save_session((object) [
            'id' => (int) $g01->id,
            'courseid' => (int) $g01->courseid,
            'sectionnum' => (int) $g01->sectionnum,
            'groupid' => (int) $group2->id,
            'trainerid' => (int) $trainer2->id,
            'sessioncode' => (string) $g01->sessioncode,
            'scheduledstart' => (int) $g01->scheduledstart,
            'scheduledend' => (int) $g01->scheduledend,
            'reportedstart' => (int) $g01->reportedstart,
            'reportedend' => (int) $g01->reportedend,
            'evidenceref' => (string) $g01->evidenceref,
            'reason' => 'Intento sintético de reasignación entre grupos.',
        ]);
    });
    $groupchangeattendanceafter = array_keys($DB->get_records(
        'local_asstaff_attendance',
        ['sessionid' => $g01->id],
        'id',
        'id'
    ));
    $sessionaftergroupchange = \local_asodeltolstaff\manager::get_session((int) $g01->id);
    $add('M11-ISO-002', $groupchangerejected
        && (int) $sessionaftergroupchange->groupid === (int) $group1->id
        && $groupchangeattendanceafter === $groupchangeattendancebefore
        && count(\local_asodeltolstaff\manager::get_history_records((int) $g01->id)) === $groupchangehistorybefore,
        'La reasignación G01 a G02 se rechaza sin mover la sesión, asistencia ni historial.');
    $history = \local_asodeltolstaff\manager::get_history_records((int) $g01->id);
    $actions = array_column($history, 'action');
    $add('M11-AUD-001', in_array('attendance_created', $actions, true)
        && in_array('attendance_updated', $actions, true)
        && in_array('validation_validated', $actions, true),
        'El historial anexable conserva declaración, corrección y validación.');
    $subjectlinksvalid = true;
    foreach ($history as $entry) {
        $expectedsubject = $entry->entitytype === 'attendance' ? (int) $student1->id : (int) $trainer1->id;
        if ((int) $entry->subjectuserid !== $expectedsubject) {
            $subjectlinksvalid = false;
            break;
        }
    }
    $privacycontexts = \local_asodeltolstaff\privacy\provider::get_contexts_for_userid((int) $student1->id);
    $privacycontextids = array_map('intval', $privacycontexts->get_contextids());
    $privacyusers = new \core_privacy\local\request\userlist($context, 'local_asodeltolstaff');
    \local_asodeltolstaff\privacy\provider::get_users_in_context($privacyusers);
    $privacyuserids = array_map('intval', $privacyusers->get_userids());
    $add('M11-PRIV-001', $subjectlinksvalid
        && in_array((int) $context->id, $privacycontextids, true)
        && in_array((int) $student1->id, $privacyuserids, true)
        && in_array((int) $trainer1->id, $privacyuserids, true),
        'El historial identifica al titular para exportación y eliminación mediante Privacy API.');
    $privacydeletionvalid = false;
    $rollbackmarker = 'M11_PRIVACY_TEST_ROLLBACK';
    $privacytransaction = $DB->start_delegated_transaction();
    try {
        $approvedcontexts = new \core_privacy\local\request\approved_contextlist(
            $student1,
            'local_asodeltolstaff',
            [(int) $context->id]
        );
        \local_asodeltolstaff\privacy\provider::delete_data_for_user($approvedcontexts);
        $attendancegone = !$DB->record_exists('local_asstaff_attendance', [
            'sessionid' => $g01->id,
            'studentid' => $student1->id,
        ]);
        $subjectgone = !$DB->record_exists('local_asstaff_history', [
            'sessionid' => $g01->id,
            'subjectuserid' => $student1->id,
        ]);
        $payloadredacted = true;
        $userfields = ['trainerid', 'validatedby', 'studentid', 'reportedby'];
        $containsuserid = static function (mixed $value) use (&$containsuserid, $student1, $userfields): bool {
            if (!is_array($value)) {
                return false;
            }
            foreach ($value as $key => $item) {
                if (in_array((string) $key, $userfields, true)
                        && is_int($item)
                        && $item === (int) $student1->id) {
                    return true;
                }
                if ($containsuserid($item)) {
                    return true;
                }
            }
            return false;
        };
        foreach (\local_asodeltolstaff\manager::get_history_records((int) $g01->id) as $entry) {
            if ($containsuserid(json_decode($entry->payload, true, 512, JSON_THROW_ON_ERROR))) {
                $payloadredacted = false;
                break;
            }
        }
        $privacydeletionvalid = $attendancegone && $subjectgone && $payloadredacted;
        $privacytransaction->rollback(new RuntimeException($rollbackmarker));
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== $rollbackmarker) {
            throw $exception;
        }
    }
    $add('M11-PRIV-002', $privacydeletionvalid && $DB->record_exists('local_asstaff_attendance', [
        'sessionid' => $g01->id,
        'studentid' => $student1->id,
    ]), 'Privacy API elimina o anonimiza identificadores dentro de una transacción de prueba revertida.');
    $add('M11-SEC-004', m11_is_rejected(static function () use ($course, $group1, $trainer1): void {
        \local_asodeltolstaff\manager::save_session((object) [
            'courseid' => $course->id,
            'sectionnum' => 1,
            'groupid' => $group1->id,
            'trainerid' => $trainer1->id,
            'sessioncode' => 'M11-INVALID-EVIDENCE',
            'scheduledstart' => strtotime('2026-11-06T14:00:00Z'),
            'scheduledend' => strtotime('2026-11-06T16:00:00Z'),
            'reportedstart' => null,
            'reportedend' => null,
            'evidenceref' => 'https://synthetic-user:synthetic-secret@evidence.example.invalid/m11/invalid',
            'reason' => 'Intento negativo sintético.',
        ]);
    }), 'Se rechazan referencias HTTPS que incorporan credenciales en la URL.');
    $adminrows = \local_asodeltolstaff\manager::get_report_rows((int) $course->id);
    $crossgrouprows = array_filter($adminrows, static fn(array $row): bool =>
        $row['sessioncode'] === M11_SESSION_G01 && $row['group'] === $group2->name);
    $admincodes = array_unique(array_column($adminrows, 'sessioncode'));
    sort($admincodes);
    $section1 = $DB->get_record('course_sections', [
        'course' => $course->id,
        'section' => 1,
    ], 'id,name', MUST_EXIST);
    $add('M11-REP-001', $admincodes === [M11_SESSION_G01, M11_SESSION_G02]
        && str_contains((string) $adminrows[0]['section'], (string) $section1->name)
        && !$crossgrouprows,
        'El reporte contiene ambos grupos y la denominación académica real de la unidad.');
    $add('M11-SEC-003', !$DB->record_exists('local_asstaff_session', [
        'courseid' => $course->id,
        'sessioncode' => 'M11-INVALID-TIME',
    ]) && !$DB->record_exists('local_asstaff_session', [
        'courseid' => $course->id,
        'sessioncode' => 'M11-INVALID-EVIDENCE',
    ]), 'Las entradas inseguras o inconsistentes no produjeron escritura parcial.');

    $failed = array_values(array_filter($controls, static fn(array $control): bool => !$control['passed']));
    echo json_encode([
        'status' => $failed ? 'FAIL' : 'PASS',
        'course' => [
            'id' => (int) $course->id,
            'visible' => (bool) $course->visible,
            'category_visible' => (bool) core_course_category::get((int) $course->category)->visible,
        ],
        'sessions' => [
            'G01' => ['id' => (int) $g01->id, 'code' => $g01->sessioncode],
            'G02' => ['id' => (int) $g02->id, 'code' => $g02->sessioncode],
        ],
        'actors' => [
            'student_g01' => ['id' => (int) $student1->id, 'username' => $student1->username],
            'trainer_g01' => ['id' => (int) $trainer1->id, 'username' => $trainer1->username],
            'auditor' => ['id' => (int) $auditor->id, 'username' => $auditor->username],
        ],
        'report_row_count' => count($adminrows),
        'history_count_g01' => count($history),
        'controls' => $controls,
        'summary' => [
            'passed' => count($controls) - count($failed),
            'failed' => count($failed),
            'total' => count($controls),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if ($failed) {
        exit(1);
    }
}

/**
 * Hide the AF8 course and category after every acceptance run.
 */
function m11_lockdown(): void {
    $admin = m11_admin();
    m11_set_user($admin);
    $course = m11_course();
    if ((int) $course->visible) {
        course_change_visibility((int) $course->id, false);
    }
    $category = core_course_category::get((int) $course->category);
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
 * Return non-sensitive runtime status without changing data.
 */
function m11_status(): void {
    global $DB;

    m11_set_user(m11_admin());
    $course = m11_course();
    echo json_encode([
        'course_visible' => (bool) $course->visible,
        'category_visible' => (bool) core_course_category::get((int) $course->category)->visible,
        'session_count' => $DB->count_records('local_asstaff_session', ['courseid' => $course->id]),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Report whether the mounted plugin version is already registered in Moodle.
 */
function m11_plugin_status(): void {
    global $CFG;
    $plugin = new stdClass();
    require($CFG->dirroot . '/local/asodeltolstaff/version.php');
    $installedversion = get_config('local_asodeltolstaff', 'version');
    echo json_encode([
        'installed' => $installedversion !== false,
        'version' => $installedversion === false ? null : (int) $installedversion,
        'expected' => (int) $plugin->version,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$options = getopt('', ['phase:']);
$phase = $options['phase'] ?? '';
switch ($phase) {
    case 'setup':
        m11_setup();
        break;
    case 'exercise':
        m11_exercise();
        break;
    case 'verify':
    case 'snapshot':
        m11_verify();
        break;
    case 'status':
        m11_status();
        break;
    case 'plugin-status':
        m11_plugin_status();
        break;
    case 'lockdown':
        m11_lockdown();
        break;
    default:
        m11_fail('Use --phase=setup, --phase=exercise, --phase=verify, --phase=status, --phase=plugin-status o --phase=lockdown.');
}
