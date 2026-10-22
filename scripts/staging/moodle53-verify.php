<?php
// Produces sanitized evidence from the live local Moodle database and APIs.

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

require_once($CFG->dirroot . '/group/lib.php');

const AF8_CATEGORY_IDNUMBER = 'ASODELTOL-STAGING';
const AF8_COURSE_IDNUMBER = 'ASODELTOL-AF8';
const AF8_TEST_ACTIVITY_IDNUMBER = 'ASODELTOL-AF8-UT1-TEST';

$officialunits = [
    'Gestión estratégica del turismo rural sostenible y desarrollo de destinos competitivos.',
    'Diseño estratégico de experiencias turísticas, calidad del servicio y generación de valor.',
    'Marketing estratégico y gestión competitiva de destinos turísticos.',
    'Transformación digital, inteligencia artificial y tecnologías emergentes aplicadas al turismo.',
    'Desarrollo rural sostenible, economía campesina y sistemas alimentarios.',
];

function assert_evidence(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    global $CFG, $DB;

    $admin = get_admin();
    assert_evidence($admin !== false && !empty($admin->id), 'No existe administrador local.');
    \core\session\manager::set_user($admin);

    $category = $DB->get_record('course_categories', ['idnumber' => AF8_CATEGORY_IDNUMBER], '*', MUST_EXIST);
    assert_evidence($category->name === 'AF8', 'La categoría AF8 no coincide.');
    assert_evidence((int) $category->visible === 0, 'La categoría de pruebas debe permanecer oculta.');

    $course = $DB->get_record('course', ['idnumber' => AF8_COURSE_IDNUMBER], '*', MUST_EXIST);
    assert_evidence((int) $course->visible === 0, 'El curso AF8 debe permanecer oculto.');
    assert_evidence($course->format === 'topics', 'El curso no usa el formato por temas.');
    assert_evidence((int) $course->groupmode === SEPARATEGROUPS, 'El curso no usa grupos separados.');
    assert_evidence((int) $course->groupmodeforce === 1, 'El modo de grupo no está forzado.');
    assert_evidence((int) $course->enablecompletion === 1, 'El seguimiento de finalización no está habilitado.');

    $cohorts = $DB->get_records('cohort', [], 'idnumber ASC');
    $expectedcodes = [];
    for ($number = 1; $number <= 7; $number++) {
        $expectedcodes[] = sprintf('AF8-G%02d', $number);
    }
    $actualcohortcodes = array_values(array_map(static fn(stdClass $cohort): string => $cohort->idnumber, $cohorts));
    assert_evidence($actualcohortcodes === $expectedcodes, 'Las siete cohortes importadas no coinciden exactamente.');

    $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'cohort'], 'id ASC');
    assert_evidence(count($instances) === 7, 'No existen exactamente siete métodos de sincronización de cohortes.');
    $instancecodes = [];
    $groupcodes = [];
    $expectedgroupnames = $expectedcodes;
    foreach ($instances as $instance) {
        $cohort = $DB->get_record('cohort', ['id' => $instance->customint1], '*', MUST_EXIST);
        $group = $DB->get_record('groups', ['id' => $instance->customint2, 'courseid' => $course->id], '*', MUST_EXIST);
        $role = $DB->get_record('role', ['id' => $instance->roleid], '*', MUST_EXIST);
        $isautomaticname = $group->name === "{$cohort->name} cohort" && $group->idnumber === '';
        $isnormalizedname = $group->name === $cohort->idnumber && $group->idnumber === $cohort->idnumber;
        assert_evidence(
            $isautomaticname || $isnormalizedname,
            "El grupo de {$cohort->idnumber} no usa el nombre automático ni el código aprobado; Moodle registró {$group->name}."
        );
        assert_evidence($role->shortname === 'student', "La sincronización de {$cohort->idnumber} no usa student.");
        $instancecodes[] = $cohort->idnumber;
        $groupcodes[] = $cohort->idnumber;
    }
    sort($instancecodes);
    sort($groupcodes);
    sort($expectedgroupnames);
    assert_evidence($instancecodes === $expectedcodes, 'Las sincronizaciones no cubren las siete cohortes.');
    assert_evidence($groupcodes === $expectedgroupnames, 'Los siete grupos no coinciden con los códigos aprobados.');

    $sections = $DB->get_records_select(
        'course_sections',
        'course = :courseid AND section > 0',
        ['courseid' => $course->id],
        'section ASC'
    );
    assert_evidence(count($sections) === 5, 'El curso no contiene exactamente cinco unidades temáticas.');
    $sectionnames = array_values(array_map(static fn(stdClass $section): string => (string) $section->name, $sections));
    assert_evidence($sectionnames === $officialunits, 'Las denominaciones de las unidades no son las oficiales.');

    $coursemodule = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => AF8_TEST_ACTIVITY_IDNUMBER,
    ], '*', MUST_EXIST);
    $pagemoduleid = $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST);
    assert_evidence((int) $coursemodule->module === (int) $pagemoduleid, 'La actividad sintética de UT1 no es una Página Moodle.');
    $activitysection = $DB->get_record('course_sections', ['id' => $coursemodule->section], '*', MUST_EXIST);
    assert_evidence((int) $activitysection->section === 1, 'La actividad sintética no está en UT1.');

    $coursecontext = \context_course::instance($course->id);
    $syntheticusers = [];
    for ($number = 1; $number <= 7; $number++) {
        $code = sprintf('AF8-G%02d', $number);
        $username = sprintf('af8.synthetic.g%02d', $number);
        $user = $DB->get_record('user', [
            'username' => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ], '*', MUST_EXIST);
        assert_evidence(str_ends_with($user->email, '@example.invalid'), "{$username} no es inequívocamente sintético.");
        assert_evidence(is_enrolled($coursecontext, $user, '', true), "{$username} no está matriculado por sincronización.");
        assert_evidence(!has_capability('moodle/course:viewhiddencourses', $coursecontext, $user, false), "{$username} puede ver cursos ocultos.");

        $cohort = $DB->get_record('cohort', ['idnumber' => $code], '*', MUST_EXIST);
        $instance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'cohort',
            'customint1' => $cohort->id,
        ], '*', MUST_EXIST);
        $group = $DB->get_record('groups', [
            'id' => $instance->customint2,
            'courseid' => $course->id,
        ], '*', MUST_EXIST);
        assert_evidence($DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $user->id]), "{$username} no pertenece a su cohorte.");
        assert_evidence(groups_is_member($group->id, $user->id), "{$username} no pertenece a su grupo.");

        $othermemberships = $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {groups_members} gm
               JOIN {groups} g ON g.id = gm.groupid
              WHERE gm.userid = :userid AND g.courseid = :courseid AND g.id <> :groupid',
            ['userid' => $user->id, 'courseid' => $course->id, 'groupid' => $group->id]
        );
        assert_evidence((int) $othermemberships === 0, "{$username} quedó asociado a otro grupo.");
        $syntheticusers[] = $username;
    }

    assert_evidence(realpath($CFG->dirroot) === '/var/www/html/public', 'La raíz Moodle activa no es /public.');
    assert_evidence(realpath($CFG->dataroot) === '/var/www/moodledata', 'moodledata no usa el volumen independiente.');
    assert_evidence(!empty($CFG->noemailever), 'El envío de correo no está desactivado.');
    assert_evidence(empty($CFG->smtphosts), 'Existe un servidor SMTP configurado.');
    assert_evidence(str_starts_with((string) $CFG->release, '5.3'), 'La versión activa no es Moodle 5.3.');
    assert_evidence(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4, 'La versión activa no es PHP 8.4.');

    $dbversion = (string) $DB->get_field_sql('SELECT VERSION()');
    assert_evidence(str_starts_with($dbversion, '8.4.'), 'La base de datos activa no es MySQL 8.4.');

    $evidence = [
        'status' => 'PASS',
        'checked_at_utc' => gmdate('c'),
        'runtime' => [
            'moodle_release' => (string) $CFG->release,
            'php_version' => PHP_VERSION,
            'database_version' => $dbversion,
            'web_root' => realpath($CFG->dirroot),
            'data_root' => realpath($CFG->dataroot),
        ],
        'isolation' => [
            'course_hidden' => true,
            'category_hidden' => true,
            'outgoing_mail_disabled' => true,
            'students_can_view_hidden_course' => false,
            'synthetic_accounts_only' => true,
            'separate_group_mode_forced' => true,
        ],
        'af8' => [
            'category_idnumber' => AF8_CATEGORY_IDNUMBER,
            'course_idnumber' => AF8_COURSE_IDNUMBER,
            'cohorts' => $expectedcodes,
            'groups' => $groupcodes,
            'cohort_enrolment_methods' => count($instances),
            'official_units' => $sectionnames,
            'test_activity_idnumber' => AF8_TEST_ACTIVITY_IDNUMBER,
            'synthetic_users' => $syntheticusers,
        ],
    ];

    echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "VERIFICATION_FAILED: {$error->getMessage()}\n");
    exit(1);
}
