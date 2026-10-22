<?php
// Local-only Moodle 5.3 staging provisioner. This file is mounted read-only into the container.

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

const AF8_CATEGORY_IDNUMBER = 'ASODELTOL-STAGING';
const AF8_COURSE_IDNUMBER = 'ASODELTOL-AF8';
const AF8_TEST_ACTIVITY_IDNUMBER = 'ASODELTOL-AF8-UT1-TEST';
const AF8_BUNDLE_DIRECTORY = '/opt/asodeltol/artifacts/bundle';

$officialunits = [
    'Gestión estratégica del turismo rural sostenible y desarrollo de destinos competitivos.',
    'Diseño estratégico de experiencias turísticas, calidad del servicio y generación de valor.',
    'Marketing estratégico y gestión competitiva de destinos turísticos.',
    'Transformación digital, inteligencia artificial y tecnologías emergentes aplicadas al turismo.',
    'Desarrollo rural sostenible, economía campesina y sistemas alimentarios.',
];

function fail(string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function require_admin_context(): void {
    $admin = get_admin();
    if (!$admin || empty($admin->id)) {
        fail('No se encontró el administrador del staging.');
    }
    \core\session\manager::set_user($admin);
}

function ensure_category(): void {
    global $DB;

    $record = $DB->get_record('course_categories', ['idnumber' => AF8_CATEGORY_IDNUMBER]);
    if ($record) {
        if ($record->name !== 'AF8') {
            fail('La categoría existente ASODELTOL-STAGING no se llama AF8.');
        }
        echo "CATEGORY=EXISTING\n";
        return;
    }

    $category = \core_course_category::create([
        'name' => 'AF8',
        'idnumber' => AF8_CATEGORY_IDNUMBER,
        'description' => 'Categoría local para el piloto aislado de AF8 con datos sintéticos.',
        'descriptionformat' => FORMAT_PLAIN,
        'visible' => 0,
    ]);
    echo "CATEGORY=CREATED;ID={$category->id}\n";
}

/**
 * Read the generated cohort CSV through Moodle's own CSV parser.
 *
 * @return array<int, array<string, string>>
 */
function read_cohort_rows(string $path): array {
    if (!is_readable($path)) {
        fail("No se puede leer el CSV de cohortes: {$path}");
    }

    $reader = new csv_import_reader(csv_import_reader::get_new_iid('asodeltolcohort'), 'asodeltolcohort');
    $content = file_get_contents($path);
    if ($content === false || $reader->load_csv_content($content, 'utf8', 'comma') === false) {
        fail('Moodle rechazó el CSV de cohortes.');
    }
    unset($content);

    $headers = $reader->get_columns();
    $expected = ['name', 'idnumber', 'description', 'descriptionformat', 'visible'];
    if ($headers !== $expected) {
        $reader->cleanup(true);
        fail('Las columnas del CSV de cohortes no coinciden con el contrato aprobado.');
    }

    if (!$reader->init()) {
        $reader->cleanup(true);
        fail('Moodle no pudo iniciar la lectura de las filas del CSV de cohortes.');
    }

    $rows = [];
    while (($values = $reader->next()) !== false) {
        if (count($values) !== count($headers)) {
            $reader->cleanup(true);
            fail('Una fila del CSV de cohortes tiene un número inválido de columnas.');
        }
        $rows[] = array_combine($headers, $values);
    }
    $reader->close();
    $reader->cleanup(true);

    if (count($rows) !== 7) {
        fail('El CSV debe contener exactamente siete cohortes.');
    }
    return $rows;
}

function import_cohorts(): void {
    global $DB;

    $rows = read_cohort_rows(AF8_BUNDLE_DIRECTORY . '/cohorts-upload.csv');
    $seen = [];
    $created = 0;
    $existing = 0;
    foreach ($rows as $index => $row) {
        $expectedid = sprintf('AF8-G%02d', $index + 1);
        if ($row['idnumber'] !== $expectedid || $row['name'] !== $expectedid) {
            fail("La fila de cohorte " . ($index + 1) . " no coincide con {$expectedid}.");
        }
        if (isset($seen[$row['idnumber']])) {
            fail("Identificador de cohorte duplicado: {$row['idnumber']}");
        }
        $seen[$row['idnumber']] = true;

        $current = $DB->get_record('cohort', ['idnumber' => $row['idnumber']]);
        if ($current) {
            if ($current->name !== $row['name']) {
                fail("La cohorte existente {$row['idnumber']} tiene un nombre diferente.");
            }
            $existing++;
            continue;
        }

        $cohort = (object) [
            'contextid' => \context_system::instance()->id,
            'name' => $row['name'],
            'idnumber' => $row['idnumber'],
            'description' => $row['description'],
            'descriptionformat' => (int) $row['descriptionformat'],
            'visible' => (int) $row['visible'],
            'component' => '',
        ];
        cohort_add_cohort($cohort);
        $created++;
    }
    echo "COHORTS=CREATED:{$created};EXISTING:{$existing}\n";
}

function course_status(): void {
    global $DB;
    echo $DB->record_exists('course', ['idnumber' => AF8_COURSE_IDNUMBER]) ? "PRESENT\n" : "ABSENT\n";
}

function create_test_activity(stdClass $course): void {
    global $DB;

    if ($DB->record_exists('course_modules', [
        'course' => $course->id,
        'idnumber' => AF8_TEST_ACTIVITY_IDNUMBER,
    ])) {
        return;
    }

    [, , , , $data] = prepare_new_moduleinfo_data($course, 'page', 1);
    $data->name = 'Actividad de prueba sintética UT1';
    $data->cmidnumber = AF8_TEST_ACTIVITY_IDNUMBER;
    $data->introeditor = [
        'text' => 'Actividad técnica de validación del entorno local. No contiene contenido académico.',
        'format' => FORMAT_PLAIN,
        'itemid' => 0,
    ];
    $data->page = [
        'text' => '<p>Prueba sintética de acceso y aislamiento para UT1.</p>',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];
    $data->display = 0;
    $data->popupwidth = 620;
    $data->popupheight = 450;
    $data->printintro = 0;
    $data->printlastmodified = 0;
    $data->revision = 1;
    $data->showdescription = 0;
    $data->completion = COMPLETION_TRACKING_NONE;
    add_moduleinfo($data, $course);
}

function ensure_synthetic_users(stdClass $course): void {
    global $CFG, $DB;

    $password = trim((string) file_get_contents('/run/secrets/student_password'));
    if ($password === '') {
        fail('La contraseña sintética del staging no está disponible.');
    }

    for ($number = 1; $number <= 7; $number++) {
        $groupcode = sprintf('AF8-G%02d', $number);
        $username = sprintf('af8.synthetic.g%02d', $number);
        $cohort = $DB->get_record('cohort', ['idnumber' => $groupcode], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
        if (!$user) {
            $userid = \core_user::create_user((object) [
                'auth' => 'manual',
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username' => $username,
                'password' => $password,
                'firstname' => 'Estudiante',
                'lastname' => sprintf('Sintético G%02d', $number),
                'email' => sprintf('af8-g%02d@example.invalid', $number),
                'emailstop' => 1,
                'city' => 'Entorno local',
                'country' => 'CO',
                'lang' => 'en',
                'timezone' => 'America/Bogota',
            ]);
        } else {
            if (!str_ends_with($user->email, '@example.invalid')) {
                fail("La cuenta {$username} no usa el dominio sintético aprobado.");
            }
            $userid = (int) $user->id;
        }

        if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $userid])) {
            cohort_add_member($cohort->id, $userid);
        }
    }

    enrol_cohort_sync(new null_progress_trace(), $course->id);
}

function configure_course(array $officialunits): void {
    global $DB;

    $course = $DB->get_record('course', ['idnumber' => AF8_COURSE_IDNUMBER], '*', MUST_EXIST);
    if ((int) $course->visible !== 0) {
        fail('El curso importado no está oculto.');
    }

    course_create_sections_if_missing($course, range(1, 5));
    foreach ($officialunits as $index => $title) {
        $sectionnumber = $index + 1;
        $section = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $sectionnumber,
        ], '*', MUST_EXIST);
        if ($section->name !== $title) {
            course_update_section($course, $section, ['name' => $title, 'visible' => 1]);
        }
    }

    create_test_activity($course);
    ensure_synthetic_users($course);
    echo "COURSE=CONFIGURED;SECTIONS=5;SYNTHETIC_USERS=7\n";
}

$options = getopt('', ['phase:']);
$phase = $options['phase'] ?? '';
require_admin_context();

switch ($phase) {
    case 'category':
        ensure_category();
        break;
    case 'cohorts':
        import_cohorts();
        break;
    case 'course-status':
        course_status();
        break;
    case 'post':
        configure_course($officialunits);
        break;
    default:
        fail('Use --phase=category, --phase=cohorts, --phase=course-status o --phase=post.');
}
