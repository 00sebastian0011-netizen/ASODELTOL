<?php
// Local-only template and reporting verification for Moodle 5.3. Mounted read-only into the container.

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
require_once($CFG->dirroot . '/group/lib.php');

const M09_CATEGORY_IDNUMBER = 'ASODELTOL-STAGING';
const M09_SOURCE_IDNUMBER = 'ASODELTOL-AF8';
const M09_DEMO_IDNUMBER = 'ASODELTOL-AF8-TEMPLATE-DEMO';
const M09_DEMO_SHORTNAME = 'ASODELTOL-AF8-TPL-DEMO';
const M09_DEMO_FULLNAME = 'AF8 - Edición sintética desde plantilla';
const M09_DEMO_COHORT = 'AF8-TEMPLATE-G01';
const M09_DEMO_STUDENT = 'af8.template.student.g01';
const M09_DEMO_TRAINER = 'af8.template.trainer.g01';
const M09_TRAINER_ROLE = 'asodeltol_capacitador_m07';
const M09_TEMPLATE_FILENAME = 'asodeltol-af8-structure-template.mbz';

const M09_OFFICIAL_UNITS = [
    'Gestión estratégica del turismo rural sostenible y desarrollo de destinos competitivos.',
    'Diseño estratégico de experiencias turísticas, calidad del servicio y generación de valor.',
    'Marketing estratégico y gestión competitiva de destinos turísticos.',
    'Transformación digital, inteligencia artificial y tecnologías emergentes aplicadas al turismo.',
    'Desarrollo rural sostenible, economía campesina y sistemas alimentarios.',
];

/**
 * Stop without exposing secrets or database details.
 */
function m09_fail(string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

/**
 * Print one machine-readable result.
 */
function m09_output(array $data): never {
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

/**
 * Set the CLI session to the local Moodle administrator.
 */
function m09_admin(): stdClass {
    $admin = get_admin();
    if (!$admin || empty($admin->id)) {
        m09_fail('No se encontró el administrador del staging local.');
    }
    \core\session\manager::set_user($admin);
    return $admin;
}

/**
 * Return one course by stable identifier.
 */
function m09_course(string $idnumber, bool $required = true): stdClass|false {
    global $DB;

    $strictness = $required ? MUST_EXIST : IGNORE_MISSING;
    return $DB->get_record('course', ['idnumber' => $idnumber], '*', $strictness);
}

/**
 * Return the local path that remains outside Git.
 */
function m09_template_path(): string {
    global $CFG;

    return $CFG->dataroot . '/temp/asodeltol-m09/' . M09_TEMPLATE_FILENAME;
}

/**
 * Get an existing synthetic account or create it from the local ignored secret.
 */
function m09_ensure_synthetic_user(string $username, string $firstname, string $lastname): stdClass {
    global $CFG, $DB;

    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
    ]);
    if ($user) {
        if ((int) $user->deleted !== 0 || !str_ends_with((string) $user->email, '@example.invalid')) {
            m09_fail("La cuenta {$username} no es una identidad sintética válida.");
        }
        return $user;
    }

    $password = trim((string) file_get_contents('/run/secrets/student_password'));
    if ($password === '') {
        m09_fail('El secreto sintético local no está disponible.');
    }

    $userid = \core_user::create_user((object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $username,
        'password' => $password,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $username . '@example.invalid',
        'emailstop' => 1,
        'city' => 'Entorno local',
        'country' => 'CO',
        'lang' => 'en',
        'timezone' => 'America/Bogota',
    ]);
    $password = '';

    return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
}

/**
 * Enrol a synthetic staff member with the native manual method.
 */
function m09_manual_enrol(stdClass $course, stdClass $user, int $roleid): void {
    global $DB;

    $instance = $DB->get_record('enrol', [
        'courseid' => $course->id,
        'enrol' => 'manual',
        'status' => ENROL_INSTANCE_ENABLED,
    ]);
    $plugin = enrol_get_plugin('manual');
    if (!$plugin) {
        m09_fail('La matrícula manual nativa no está disponible.');
    }
    if (!$instance) {
        $instanceid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }
    $plugin->enrol_user($instance, $user->id, $roleid, 0, 0, ENROL_USER_ACTIVE);
}

/**
 * Return stable course evidence without volatile timestamps or personal data.
 */
function m09_course_snapshot(stdClass $course): array {
    global $DB;

    $context = \context_course::instance($course->id);
    $category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);
    $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
    $units = [];
    foreach ($sections as $section) {
        if ((int) $section->section < 1 || (int) $section->section > 5) {
            continue;
        }
        $index = (int) $section->section - 1;
        $units[] = [
            'number' => (int) $section->section,
            'name' => (string) $section->name,
            'official_name_match' => (string) $section->name === M09_OFFICIAL_UNITS[$index],
            'visible' => (bool) $section->visible,
        ];
    }

    $groups = [];
    foreach ($DB->get_records('groups', ['courseid' => $course->id], 'idnumber ASC, name ASC') as $group) {
        $members = array_values(array_map(
            static fn(stdClass $record): int => (int) $record->userid,
            $DB->get_records('groups_members', ['groupid' => $group->id], 'userid ASC', 'id,userid')
        ));
        $groups[] = [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
            'idnumber' => (string) $group->idnumber,
            'member_ids' => $members,
        ];
    }

    $enrolmentmethods = [];
    foreach ($DB->get_records('enrol', ['courseid' => $course->id], 'id ASC') as $instance) {
        $cohortcode = null;
        if ($instance->enrol === 'cohort' && !empty($instance->customint1)) {
            $cohortcode = $DB->get_field('cohort', 'idnumber', ['id' => $instance->customint1]) ?: null;
        }
        $enrolmentmethods[] = [
            'type' => (string) $instance->enrol,
            'enabled' => (int) $instance->status === ENROL_INSTANCE_ENABLED,
            'cohort' => $cohortcode,
            'group_id' => (int) ($instance->customint2 ?? 0),
        ];
    }

    $users = get_enrolled_users(
        $context,
        '',
        0,
        'u.id,u.username,u.email',
        'u.username ASC'
    );
    $enrolledusers = [];
    foreach ($users as $user) {
        $enrolledusers[] = [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'synthetic_domain' => str_ends_with((string) $user->email, '@example.invalid'),
        ];
    }

    $roleassignments = [];
    $sql = "SELECT ra.id, u.username, r.shortname
              FROM {role_assignments} ra
              JOIN {user} u ON u.id = ra.userid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ra.contextid = :contextid
          ORDER BY u.username, r.shortname";
    foreach ($DB->get_records_sql($sql, ['contextid' => $context->id]) as $assignment) {
        $roleassignments[] = [
            'username' => (string) $assignment->username,
            'role' => (string) $assignment->shortname,
        ];
    }

    $grades = [];
    $sql = "SELECT gg.id, u.username, gi.itemtype, gi.itemmodule, gi.idnumber, gg.finalgrade
              FROM {grade_grades} gg
              JOIN {grade_items} gi ON gi.id = gg.itemid
              JOIN {user} u ON u.id = gg.userid
             WHERE gi.courseid = :courseid
                   AND gg.finalgrade IS NOT NULL
          ORDER BY u.username, gi.id";
    foreach ($DB->get_records_sql($sql, ['courseid' => $course->id]) as $grade) {
        $grades[] = [
            'username' => (string) $grade->username,
            'itemtype' => (string) $grade->itemtype,
            'itemmodule' => (string) ($grade->itemmodule ?? ''),
            'idnumber' => (string) ($grade->idnumber ?? ''),
            'grade' => round((float) $grade->finalgrade, 5),
        ];
    }

    $groupids = array_map(static fn(array $group): int => $group['id'], $groups);
    $groupmembershipcount = 0;
    if ($groupids) {
        [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'groupid');
        $groupmembershipcount = $DB->count_records_select('groups_members', "groupid {$insql}", $params);
    }

    $submissionsql = "SELECT COUNT(s.id)
                        FROM {assign_submission} s
                        JOIN {assign} a ON a.id = s.assignment
                       WHERE a.course = :courseid AND s.latest = 1";
    $completionsql = "SELECT COUNT(cmc.id)
                        FROM {course_modules_completion} cmc
                        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                       WHERE cm.course = :courseid";

    return [
        'id' => (int) $course->id,
        'fullname' => (string) $course->fullname,
        'shortname' => (string) $course->shortname,
        'idnumber' => (string) $course->idnumber,
        'category_idnumber' => (string) $category->idnumber,
        'visible' => (bool) $course->visible,
        'format' => (string) $course->format,
        'groupmode' => (int) $course->groupmode,
        'groupmodeforce' => (bool) $course->groupmodeforce,
        'enablecompletion' => (bool) $course->enablecompletion,
        'showgrades' => (bool) $course->showgrades,
        'showreports' => (bool) $course->showreports,
        'startdate' => (int) $course->startdate,
        'enddate' => (int) $course->enddate,
        'units' => $units,
        'official_units_match' => count($units) === 5 && !array_filter(
            $units,
            static fn(array $unit): bool => !$unit['official_name_match']
        ),
        'activity_count' => $DB->count_records('course_modules', ['course' => $course->id, 'deletioninprogress' => 0]),
        'groups' => $groups,
        'group_membership_count' => (int) $groupmembershipcount,
        'enrolment_methods' => $enrolmentmethods,
        'enrolled_users' => $enrolledusers,
        'role_assignments' => $roleassignments,
        'grades' => $grades,
        'submission_count' => (int) $DB->count_records_sql($submissionsql, ['courseid' => $course->id]),
        'completion_record_count' => (int) $DB->count_records_sql($completionsql, ['courseid' => $course->id]),
    ];
}

/**
 * Hash the stable source evidence to prove that backup/restore did not mutate it.
 */
function m09_snapshot_hash(array $snapshot): string {
    return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Configure a no-user, no-activity native backup and retain an ignored local MBZ.
 *
 * @return array{backupid:string,basepath:string,template:array,settings:array}
 */
function m09_create_template_backup(stdClass $source, stdClass $admin): array {
    $controller = new backup_controller(
        backup::TYPE_1COURSE,
        $source->id,
        backup::FORMAT_MOODLE,
        backup::INTERACTIVE_NO,
        backup::MODE_GENERAL,
        $admin->id
    );
    $plan = $controller->get_plan();
    $values = [
        'users' => false,
        'anonymize' => false,
        'role_assignments' => false,
        'activities' => false,
        'blocks' => false,
        'files' => false,
        'filters' => false,
        'comments' => false,
        'badges' => false,
        'calendarevents' => false,
        'userscompletion' => false,
        'logs' => false,
        'grade_histories' => false,
        'groups' => false,
        'competencies' => false,
        'customfield' => false,
        'contentbankcontent' => false,
        'xapistate' => false,
        'legacyfiles' => false,
    ];
    foreach ($values as $name => $value) {
        if (!$plan->setting_exists($name)) {
            continue;
        }
        try {
            $plan->get_setting($name)->set_value($value);
        } catch (base_setting_exception $exception) {
            if ((bool) $plan->get_setting($name)->get_value() !== $value) {
                $controller->destroy();
                throw $exception;
            }
        }
    }
    $plan->get_setting('filename')->set_value(M09_TEMPLATE_FILENAME);

    $effective = [];
    foreach (array_keys($values) as $name) {
        if ($plan->setting_exists($name)) {
            $effective[$name] = (bool) $plan->get_setting($name)->get_value();
        }
    }
    foreach ($effective as $name => $value) {
        if ($value !== false) {
            $controller->destroy();
            m09_fail("La plantilla no pudo desactivar el ajuste sensible {$name}.");
        }
    }

    $backupid = $controller->get_backupid();
    $basepath = $plan->get_basepath();
    $controller->execute_plan();
    $results = $controller->get_results();
    $file = $results['backup_destination'] ?? null;
    if (!$file instanceof stored_file) {
        $controller->destroy();
        m09_fail('Moodle no produjo el respaldo estructural esperado.');
    }

    $path = m09_template_path();
    $directory = dirname($path);
    make_writable_directory($directory, true);
    $realparent = realpath($directory);
    $realdataroot = realpath($GLOBALS['CFG']->dataroot);
    if ($realparent === false || $realdataroot === false || !str_starts_with($realparent, $realdataroot)) {
        $controller->destroy();
        m09_fail('La ruta local de la plantilla no está dentro de moodledata.');
    }
    if (file_exists($path) && !unlink($path)) {
        $controller->destroy();
        m09_fail('No se pudo reemplazar la plantilla sintética local existente.');
    }
    if (!$file->copy_content_to($path) || !is_readable($path)) {
        $controller->destroy();
        m09_fail('No se pudo conservar la plantilla local fuera de Git.');
    }

    return [
        'controller' => $controller,
        'backupid' => $backupid,
        'basepath' => $basepath,
        'settings' => $effective,
        'template' => [
            'container_path' => $path,
            'filename' => M09_TEMPLATE_FILENAME,
            'bytes' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ],
    ];
}

/**
 * Restore the native backup into a new independent hidden course.
 */
function m09_restore_template(array $backup, stdClass $source, stdClass $admin): stdClass {
    global $DB;

    $newcourseid = 0;
    try {
        if (!file_exists($backup['basepath'] . '/moodle_backup.xml')) {
            $packer = get_file_packer('application/vnd.moodle.backup');
            if (!$packer->extract_to_pathname(m09_template_path(), $backup['basepath'])) {
                m09_fail('No fue posible extraer la plantilla nativa para restaurarla.');
            }
        }

        $newcourseid = (int) restore_dbops::create_new_course(
            M09_DEMO_FULLNAME,
            M09_DEMO_SHORTNAME,
            $source->category
        );
        $controller = new restore_controller(
            $backup['backupid'],
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $admin->id,
            backup::TARGET_NEW_COURSE
        );
        if ($controller->execute_precheck() !== true) {
            $controller->destroy();
            throw new moodle_exception('restoreprecheckfailed', 'backup');
        }
        $controller->execute_plan();
        $controller->destroy();

        update_course((object) [
            'id' => $newcourseid,
            'fullname' => M09_DEMO_FULLNAME,
            'shortname' => M09_DEMO_SHORTNAME,
            'idnumber' => M09_DEMO_IDNUMBER,
            'category' => $source->category,
            'visible' => 0,
            'startdate' => 0,
            'enddate' => 0,
        ]);
        rebuild_course_cache($newcourseid, true);
        return m09_course(M09_DEMO_IDNUMBER);
    } catch (Throwable $exception) {
        if ($newcourseid > 0 && $DB->record_exists('course', ['id' => $newcourseid])) {
            delete_course($newcourseid, false);
        }
        throw $exception;
    }
}

/**
 * Prove that the restored course starts with no copied identities or learning records.
 */
function m09_assert_clean_restore(array $source, array $demo): void {
    if ($demo['id'] === $source['id'] || $demo['idnumber'] !== M09_DEMO_IDNUMBER) {
        m09_fail('El curso restaurado no tiene una identidad independiente.');
    }
    if ($demo['category_idnumber'] !== M09_CATEGORY_IDNUMBER || $demo['visible']) {
        m09_fail('El curso restaurado no está oculto en la categoría local aprobada.');
    }
    if (!$demo['official_units_match'] || count($demo['units']) !== 5) {
        m09_fail('La plantilla no conservó exactamente las cinco unidades oficiales.');
    }
    foreach (['format', 'groupmode', 'groupmodeforce', 'enablecompletion', 'showgrades', 'showreports'] as $field) {
        if ($demo[$field] !== $source[$field]) {
            m09_fail("La configuración general {$field} no fue reutilizada correctamente.");
        }
    }
    $mustbeempty = [
        'activities' => $demo['activity_count'],
        'groups' => count($demo['groups']),
        'group memberships' => $demo['group_membership_count'],
        'enrolled users' => count($demo['enrolled_users']),
        'role assignments' => count($demo['role_assignments']),
        'grades' => count($demo['grades']),
        'submissions' => $demo['submission_count'],
        'completion records' => $demo['completion_record_count'],
    ];
    foreach ($mustbeempty as $label => $count) {
        if ((int) $count !== 0) {
            m09_fail("La restauración limpia arrastró {$label}: {$count}.");
        }
    }
    $cohortmethods = array_filter(
        $demo['enrolment_methods'],
        static fn(array $method): bool => $method['type'] === 'cohort'
    );
    if ($cohortmethods) {
        m09_fail('La restauración limpia arrastró sincronizaciones de cohortes.');
    }
}

/**
 * Configure one independent synthetic group after the clean restore was proven.
 */
function m09_configure_independent_edition(stdClass $course): void {
    global $DB;

    $student = m09_ensure_synthetic_user(M09_DEMO_STUDENT, 'Estudiante', 'Plantilla G01');
    $trainer = m09_ensure_synthetic_user(M09_DEMO_TRAINER, 'Capacitador', 'Plantilla G01');

    $cohort = $DB->get_record('cohort', ['idnumber' => M09_DEMO_COHORT]);
    if (!$cohort) {
        $cohort = (object) [
            'contextid' => \context_system::instance()->id,
            'name' => M09_DEMO_COHORT,
            'idnumber' => M09_DEMO_COHORT,
            'description' => 'Cohorte sintética independiente para demostrar reutilización de la plantilla.',
            'descriptionformat' => FORMAT_PLAIN,
            'visible' => 0,
            'component' => '',
        ];
        $cohort->id = cohort_add_cohort($cohort);
    }
    if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $student->id])) {
        cohort_add_member($cohort->id, $student->id);
    }

    $group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => M09_DEMO_COHORT]);
    if (!$group) {
        $groupid = groups_create_group((object) [
            'courseid' => $course->id,
            'name' => M09_DEMO_COHORT,
            'idnumber' => M09_DEMO_COHORT,
            'description' => 'Grupo sintético independiente de la edición creada desde plantilla.',
            'descriptionformat' => FORMAT_PLAIN,
        ]);
        $group = $DB->get_record('groups', ['id' => $groupid], '*', MUST_EXIST);
    }

    $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
    $cohortinstance = $DB->get_record('enrol', [
        'courseid' => $course->id,
        'enrol' => 'cohort',
        'customint1' => $cohort->id,
    ]);
    if (!$cohortinstance) {
        $plugin = enrol_get_plugin('cohort');
        if (!$plugin) {
            m09_fail('La sincronización nativa de cohortes no está disponible.');
        }
        $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint1' => $cohort->id,
            'customint2' => $group->id,
            'roleid' => $studentrole->id,
        ]);
    }
    enrol_cohort_sync(new null_progress_trace(), $course->id);

    $trainerrole = $DB->get_record('role', ['shortname' => M09_TRAINER_ROLE]);
    if (!$trainerrole) {
        m09_fail('El rol sintético de capacitador validado en la Misión 07 no existe.');
    }
    m09_manual_enrol($course, $trainer, (int) $trainerrole->id);
    if (!groups_is_member($group->id, $trainer->id)) {
        groups_add_member($group, $trainer);
    }

    update_course((object) ['id' => $course->id, 'visible' => 0]);
    rebuild_course_cache((int) $course->id, true);
}

/**
 * Validate the independent post-restore assignments.
 */
function m09_assert_configured_demo(array $demo): void {
    if ($demo['visible'] || count($demo['groups']) !== 1 || $demo['group_membership_count'] !== 2) {
        m09_fail('La edición sintética no conserva aislamiento, grupo o membresías esperadas.');
    }
    if ($demo['groups'][0]['name'] !== M09_DEMO_COHORT
            || $demo['groups'][0]['idnumber'] !== M09_DEMO_COHORT) {
        m09_fail('La edición sintética no conserva el identificador independiente de su grupo.');
    }
    $usernames = array_column($demo['enrolled_users'], 'username');
    sort($usernames);
    $expected = [M09_DEMO_STUDENT, M09_DEMO_TRAINER];
    sort($expected);
    if ($usernames !== $expected) {
        m09_fail('La edición sintética no tiene exactamente sus dos identidades independientes.');
    }
    if (array_filter($demo['enrolled_users'], static fn(array $user): bool => !$user['synthetic_domain'])) {
        m09_fail('La edición sintética contiene una identidad fuera de example.invalid.');
    }
    $cohortmethods = array_values(array_filter(
        $demo['enrolment_methods'],
        static fn(array $method): bool => $method['type'] === 'cohort' && $method['cohort'] === M09_DEMO_COHORT
    ));
    if (count($cohortmethods) !== 1 || !$cohortmethods[0]['enabled']) {
        m09_fail('La edición sintética no tiene su sincronización de cohorte independiente.');
    }
    if ($demo['activity_count'] !== 0 || $demo['grades'] || $demo['submission_count'] !== 0
            || $demo['completion_record_count'] !== 0) {
        m09_fail('La edición sintética contiene actividades o registros académicos no autorizados.');
    }
}

/**
 * Describe report facilities that are actually enabled in this Moodle.
 */
function m09_report_snapshot(stdClass $course, stdClass $admin): array {
    global $DB;

    \core\session\manager::set_user($admin);
    $context = \context_course::instance($course->id);
    $formats = [];
    foreach (\core_plugin_manager::instance()->get_plugins_of_type('dataformat') as $format) {
        if ($format->is_enabled()) {
            $formats[] = $format->name;
        }
    }
    sort($formats);
    $attendance = \core_plugin_manager::instance()->get_plugin_info('mod_attendance');
    $actors = [
        'admin' => $admin,
        'student_g01' => $DB->get_record('user', ['username' => 'af8.synthetic.g01'], '*', MUST_EXIST),
        'trainer_g01' => $DB->get_record('user', ['username' => 'af8.synthetic.trainer.g01'], '*', MUST_EXIST),
        'auditor' => $DB->get_record('user', ['username' => 'af8.synthetic.auditor'], '*', MUST_EXIST),
    ];
    $accessmatrix = [];
    foreach ($actors as $key => $user) {
        $doanything = $key === 'admin';
        $allgroups = has_capability('moodle/site:accessallgroups', $context, $user, $doanything);
        $groups = groups_get_all_groups($course->id, $user->id, 0, 'g.id,g.idnumber');
        $accessmatrix[$key] = [
            'username' => $user->username,
            'view_participants' => has_capability(
                'moodle/course:viewparticipants',
                $context,
                $user,
                $doanything
            ),
            'participant_scope' => $allgroups ? 'all_groups' : array_values(array_column($groups, 'idnumber')),
            'export_grades_xlsx' => has_capability('moodle/grade:export', $context, $user, $doanything)
                && has_capability('gradeexport/xls:view', $context, $user, $doanything),
            'view_activity_completion' => has_capability(
                'report/progress:view',
                $context,
                $user,
                $doanything
            ),
            'view_course_completion' => has_capability(
                'report/completion:view',
                $context,
                $user,
                $doanything
            ),
            'access_all_groups' => $allgroups,
        ];
    }

    return [
        'enabled_dataformats' => $formats,
        'excel_enabled' => in_array('excel', $formats, true),
        'admin_capabilities' => [
            'view_participants' => has_capability('moodle/course:viewparticipants', $context),
            'export_grades' => has_capability('moodle/grade:export', $context),
            'view_activity_completion' => has_capability('report/progress:view', $context),
            'view_course_completion' => has_capability('report/completion:view', $context),
        ],
        'access_matrix' => $accessmatrix,
        'attendance_plugin_installed' => $attendance !== null,
        'attendance_evidence_rule' => 'Los logs de navegación o sesión no prueban asistencia efectiva ni tiempo trabajado.',
    ];
}

/**
 * Return current evidence without changing either course.
 */
function m09_snapshot(stdClass $admin): array {
    $source = m09_course(M09_SOURCE_IDNUMBER);
    $demo = m09_course(M09_DEMO_IDNUMBER, false);
    return [
        'runtime' => [
            'release' => (string) $GLOBALS['CFG']->release,
            'wwwroot' => (string) $GLOBALS['CFG']->wwwroot,
            'outbound_mail_disabled' => (bool) $GLOBALS['CFG']->noemailever,
        ],
        'source' => m09_course_snapshot($source),
        'demo' => $demo ? m09_course_snapshot($demo) : null,
        'reports' => m09_report_snapshot($source, $admin),
        'template' => file_exists(m09_template_path()) ? [
            'container_path' => m09_template_path(),
            'filename' => M09_TEMPLATE_FILENAME,
            'bytes' => filesize(m09_template_path()),
            'sha256' => hash_file('sha256', m09_template_path()),
        ] : null,
    ];
}

$options = getopt('', ['phase:']);
$phase = $options['phase'] ?? '';
$admin = m09_admin();

try {
    switch ($phase) {
        case 'inspect':
        case 'snapshot':
            m09_output(m09_snapshot($admin));

        case 'create':
            $sourcecourse = m09_course(M09_SOURCE_IDNUMBER);
            $existingdemo = m09_course(M09_DEMO_IDNUMBER, false);
            if ($existingdemo) {
                $before = m09_course_snapshot($sourcecourse);
                $beforehash = m09_snapshot_hash($before);
                $backup = m09_create_template_backup($sourcecourse, $admin);
                $backup['controller']->destroy();
                $afterhash = m09_snapshot_hash(m09_course_snapshot(m09_course(M09_SOURCE_IDNUMBER)));
                if (!hash_equals($beforehash, $afterhash)) {
                    m09_fail('El curso AF8 original cambió al regenerar su plantilla estructural.');
                }
                $snapshot = m09_snapshot($admin);
                m09_assert_configured_demo($snapshot['demo']);
                m09_output([
                    'status' => 'already_ready',
                    'source_integrity' => true,
                    'source_hash_before' => $beforehash,
                    'source_hash_after' => $afterhash,
                    'template' => $backup['template'],
                    'backup_settings' => $backup['settings'],
                    'snapshot' => $snapshot,
                ]);
            }

            $before = m09_course_snapshot($sourcecourse);
            $beforehash = m09_snapshot_hash($before);
            $backup = m09_create_template_backup($sourcecourse, $admin);
            $democourse = m09_restore_template($backup, $sourcecourse, $admin);
            $backup['controller']->destroy();

            $clean = m09_course_snapshot($democourse);
            m09_assert_clean_restore($before, $clean);
            m09_configure_independent_edition($democourse);
            $configured = m09_course_snapshot(m09_course(M09_DEMO_IDNUMBER));
            m09_assert_configured_demo($configured);

            $after = m09_course_snapshot(m09_course(M09_SOURCE_IDNUMBER));
            $afterhash = m09_snapshot_hash($after);
            if (!hash_equals($beforehash, $afterhash)) {
                m09_fail('El curso AF8 original cambió durante la creación de la plantilla.');
            }

            m09_output([
                'status' => 'created',
                'source_integrity' => true,
                'source_hash_before' => $beforehash,
                'source_hash_after' => $afterhash,
                'template' => $backup['template'],
                'backup_settings' => $backup['settings'],
                'restored_clean' => $clean,
                'configured_demo' => $configured,
                'reports' => m09_report_snapshot($sourcecourse, $admin),
            ]);

        default:
            m09_fail('Use --phase=inspect, --phase=create o --phase=snapshot.');
    }
} catch (Throwable $exception) {
    m09_fail($exception->getMessage());
}
