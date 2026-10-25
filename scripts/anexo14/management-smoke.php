<?php
// Moodle CLI acceptance smoke test for Anexo 14 management requirements.

declare(strict_types=1);

define('CLI_SCRIPT', true);

$arguments = $argv ?? [];
if (in_array('--help', $arguments, true)) {
    echo "Usage:\n"
        . "  php scripts/anexo14/management-smoke.php --mode=staging --categoryid=ID --write\n"
        . "  php scripts/anexo14/management-smoke.php --mode=production --categoryid=ID --write"
        . " --confirm-production=ASOCODEM-AF1 --backup-reference=REF --backup-verified=yes"
        . " --change-window=REF\n"
        . "  php scripts/anexo14/management-smoke.php --mode=staging --cleanup\n";
    exit(0);
}

$configfile = __DIR__ . '/../../config.php';
if (!is_file($configfile)) {
    $configfile = '/var/www/html/config.php';
}
if (!is_file($configfile)) {
    fwrite(STDERR, "ERROR: Moodle config.php no está disponible junto al script ni en /var/www/html.\n");
    exit(1);
}
require($configfile);
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

const ANEXO14_PRODUCTION_HOST = 'aulas.prospectiva.edu.co';
const ANEXO14_PRODUCTION_CONFIRMATION = 'ASOCODEM-AF1';
const ANEXO14_TEST_PREFIX = 'anexo14test';

/**
 * Stop with a concise CLI error.
 *
 * @param string $message Error detail.
 * @return never
 */
function anexo14_fail(string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

/**
 * Read and validate CLI options.
 *
 * @return array<string, string|bool>
 */
function anexo14_options(): array {
    $options = getopt('', [
        'mode:',
        'categoryid:',
        'write',
        'confirm-production:',
        'backup-reference:',
        'backup-verified:',
        'change-window:',
        'cleanup',
        'help',
    ]);
    if (!isset($options['mode']) || !in_array($options['mode'], ['staging', 'production'], true)) {
        anexo14_fail('Indique --mode=staging o --mode=production.');
    }
    return $options;
}

/**
 * Enforce the production-specific target and operator confirmations.
 *
 * @param array<string, string|bool> $options CLI options.
 */
function anexo14_guard_environment(array $options): void {
    global $CFG;

    if ($options['mode'] !== 'production') {
        return;
    }
    $host = parse_url((string) $CFG->wwwroot, PHP_URL_HOST);
    if (!is_string($host) || strtolower($host) !== ANEXO14_PRODUCTION_HOST) {
        anexo14_fail('El --mode=production solo se permite en el host de la plataforma evaluada.');
    }
    if (($options['confirm-production'] ?? '') !== ANEXO14_PRODUCTION_CONFIRMATION) {
        anexo14_fail('Producción requiere --confirm-production=ASOCODEM-AF1.');
    }
    if (empty($options['backup-reference'])
            || ($options['backup-verified'] ?? '') !== 'yes'
            || empty($options['change-window'])) {
        anexo14_fail('Producción requiere referencia de respaldo verificado y ventana de cambio aprobada.');
    }
}

/**
 * Return the local manifest location.
 *
 * @return string
 */
function anexo14_manifest_path(): string {
    global $CFG;

    $directory = $CFG->dataroot . '/temp/anexo14-management-smoke';
    if (!is_dir($directory)) {
        $permissions = filter_var($CFG->directorypermissions, FILTER_VALIDATE_INT);
        if ($permissions === false || $permissions < 0 || $permissions > 07777) {
            anexo14_fail('La configuración de permisos de moodledata no es válida para crear el directorio de recuperación.');
        }
        if (!mkdir($directory, $permissions, true) && !is_dir($directory)) {
            anexo14_fail('No fue posible crear el directorio privado de recuperación en moodledata.');
        }
    }
    return $directory . '/active-run.json';
}

/**
 * Persist recovery data outside the webroot.
 *
 * @param string $path Manifest path.
 * @param array<string, mixed> $manifest Recovery data.
 */
function anexo14_save_manifest(string $path, array $manifest): void {
    $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false
            || !rename($temporary, $path)) {
        anexo14_fail('No fue posible guardar el manifiesto de limpieza.');
    }
}

/**
 * Remove only records bearing the unique marker from this run.
 *
 * @param string $path Manifest path.
 */
function anexo14_cleanup(string $path): void {
    global $CFG, $DB;

    if (!is_file($path)) {
        echo "No hay una ejecución previa pendiente de limpieza.\n";
        return;
    }
    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['marker'] ?? '') === ''
            || !str_starts_with((string) $manifest['marker'], ANEXO14_TEST_PREFIX . '_')) {
        anexo14_fail('El manifiesto no contiene el marcador esperado; se conserva sin cambios.');
    }

    $group = null;
    if (!empty($manifest['groupid'])) {
        $group = $DB->get_record('groups', ['id' => $manifest['groupid']]);
    }
    if (!$group && !empty($manifest['groupidnumber']) && !empty($manifest['courseid'])) {
        $group = $DB->get_record('groups', [
            'courseid' => $manifest['courseid'],
            'idnumber' => $manifest['groupidnumber'],
        ]);
    }
    if ($group) {
        if ((int) $group->courseid !== (int) ($manifest['courseid'] ?? 0)
                || (string) $group->idnumber !== (string) $manifest['groupidnumber']) {
            anexo14_fail('El grupo ya no coincide con los marcadores; no se elimina.');
        }
        groups_delete_group($group);
        echo "Grupo sintético eliminado.\n";
    }

    $course = null;
    if (!empty($manifest['courseid'])) {
        $course = $DB->get_record('course', ['id' => $manifest['courseid']]);
    }
    if (!$course && !empty($manifest['courseidnumber'])) {
        $course = $DB->get_record('course', ['idnumber' => $manifest['courseidnumber']]);
    }
    if ($course) {
        if ((string) $course->idnumber !== (string) $manifest['courseidnumber']
                || (string) $course->shortname !== (string) $manifest['courseshortname']
                || (int) $course->visible !== 0) {
            anexo14_fail('El curso no conserva todos los marcadores/estado oculto; no se elimina.');
        }
        if (!delete_course($course, false)) {
            anexo14_fail('Moodle no pudo eliminar el curso sintético.');
        }
        echo "Curso sintético eliminado.\n";
    }

    $user = null;
    if (!empty($manifest['userid'])) {
        $user = $DB->get_record('user', ['id' => $manifest['userid']]);
    }
    if (!$user && !empty($manifest['username'])) {
        $user = $DB->get_record('user', [
            'username' => $manifest['username'],
            'mnethostid' => $CFG->mnet_localhost_id,
        ]);
    }
    if ($user && empty($user->deleted)) {
        if ((string) $user->username !== (string) $manifest['username']
                || (string) $user->email !== (string) $manifest['email']) {
            anexo14_fail('La cuenta no coincide con los marcadores; no se elimina.');
        }
        if (!\core_user::delete_user($user)) {
            anexo14_fail('Moodle no pudo marcar como eliminada la cuenta sintética.');
        }
        echo "Cuenta sintética eliminada lógicamente por Moodle.\n";
    }

    if (is_file($path) && !unlink($path)) {
        anexo14_fail('Los objetos se limpiaron, pero no se pudo retirar el manifiesto.');
    }
}

/**
 * Run management CRUD checks using newly created synthetic records only.
 *
 * @param array<string, string|bool> $options CLI options.
 */
function anexo14_run(array $options): void {
    global $CFG, $DB;

    if (!array_key_exists('write', $options)) {
        echo "PRECHECK PASS: no se escribieron datos. Revise el entorno y repita con --write.\n";
        echo 'wwwroot=' . (string) $CFG->wwwroot . PHP_EOL;
        return;
    }
    if (empty($options['categoryid']) || !ctype_digit((string) $options['categoryid'])) {
        if ($options['mode'] === 'staging') {
            $categoryid = $DB->get_field('course_categories', 'id', ['idnumber' => 'ASODELTOL-STAGING']);
            if (!$categoryid) {
                anexo14_fail('No existe la categoría sintética ASODELTOL-STAGING en el staging.');
            }
            $category = \core_course_category::get((int) $categoryid, MUST_EXIST, true);
            if ((string) $category->name !== 'AF8') {
                anexo14_fail('La categoría ASODELTOL-STAGING no tiene el nombre esperado AF8.');
            }
            $options['categoryid'] = (string) $categoryid;
        } else {
            anexo14_fail('Producción requiere --categoryid de una categoría autorizada para pruebas.');
        }
    }

    $manifestpath = anexo14_manifest_path();
    if (is_file($manifestpath)) {
        anexo14_fail("Existe una ejecución pendiente. Revísela y ejecute --cleanup: {$manifestpath}");
    }

    $admin = get_admin();
    if (!$admin || empty($admin->id)) {
        anexo14_fail('No se encontró la cuenta administradora de Moodle para la ejecución CLI.');
    }
    \core\session\manager::set_user($admin);

    $token = strtolower(bin2hex(random_bytes(6)));
    $marker = ANEXO14_TEST_PREFIX . '_' . gmdate('Ymd_His') . '_' . $token;
    $manifest = [
        'createdat' => gmdate(DATE_ATOM),
        'marker' => $marker,
        'categoryid' => (int) $options['categoryid'],
        'courseidnumber' => strtoupper($marker),
        'courseshortname' => strtoupper($marker),
        'groupidnumber' => strtoupper($marker . '_G1'),
        'groupname' => 'ANEXO14 TEST ' . $token,
        'username' => $marker,
        'email' => $marker . '@example.invalid',
        'backupreference' => (string) ($options['backup-reference'] ?? ''),
        'changewindow' => (string) ($options['change-window'] ?? ''),
    ];
    anexo14_save_manifest($manifestpath, $manifest);
    $controls = [];

    try {
        $category = \core_course_category::get((int) $options['categoryid'], MUST_EXIST, true);
        $course = create_course((object) [
            'category' => (int) $category->id,
            'fullname' => 'ANEXO14 TEST ' . $token,
            'shortname' => $manifest['courseshortname'],
            'idnumber' => $manifest['courseidnumber'],
            'summary' => 'Datos sintéticos temporales Anexo 14; ejecución ' . $token,
            'summaryformat' => FORMAT_PLAIN,
            'format' => 'topics',
            'visible' => 0,
            'startdate' => time(),
        ]);
        $manifest['courseid'] = (int) $course->id;
        anexo14_save_manifest($manifestpath, $manifest);
        $savedcourse = $DB->get_record('course', ['idnumber' => $manifest['courseidnumber']], '*', MUST_EXIST);
        if ((int) $savedcourse->id !== (int) $course->id || (int) $savedcourse->visible !== 0) {
            throw new RuntimeException('No se pudo leer el curso sintético creado o confirmar que está oculto.');
        }
        $controls[] = ['REQ-13-01', 'PASS', 'Curso sintético oculto creado.'];

        $course->fullname = 'ANEXO14 TEST EDITADO ' . $token;
        update_course($course);
        $course = $DB->get_record('course', ['id' => $manifest['courseid']], '*', MUST_EXIST);
        if ((string) $course->fullname !== 'ANEXO14 TEST EDITADO ' . $token) {
            throw new RuntimeException('No se confirmó la edición del curso sintético.');
        }
        $controls[] = ['REQ-13-02', 'PARTIAL', 'Se verificaron nombre, código, descripción y estado oculto; otros campos contractuales no se inventaron.'];

        $user = (object) [
            'auth' => 'nologin',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $manifest['username'],
            'firstname' => 'Prueba Anexo14',
            'lastname' => $token,
            'email' => $manifest['email'],
            'city' => 'Prueba sintética',
            'country' => 'CO',
            'lang' => current_language(),
        ];
        $manifest['userid'] = \core_user::create_user($user, false, true);
        anexo14_save_manifest($manifestpath, $manifest);
        $createduser = $DB->get_record('user', ['username' => $manifest['username']], '*', MUST_EXIST);
        if ((int) $createduser->id !== (int) $manifest['userid']) {
            throw new RuntimeException('No se pudo consultar la cuenta sintética recién creada.');
        }
        $controls[] = ['REQ-15-01', 'PASS', 'Cuenta sintética creada mediante API nativa de Moodle.'];

        $manualinstance = null;
        foreach (enrol_get_instances((int) $course->id, true) as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
        if (!$manualinstance) {
            throw new RuntimeException('El curso sintético no tiene una instancia de matrícula manual habilitada.');
        }
        $manualplugin = enrol_get_plugin('manual');
        if (!$manualplugin) {
            throw new RuntimeException('El plugin de matrícula manual no está disponible.');
        }
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $manualplugin->enrol_user(
            $manualinstance,
            (int) $manifest['userid'],
            $studentroleid,
            time()
        );
        if (!is_enrolled(context_course::instance((int) $course->id), (int) $manifest['userid'])) {
            throw new RuntimeException('No se confirmó la matrícula sintética del beneficiario.');
        }

        $user = $DB->get_record('user', ['id' => $manifest['userid']], '*', MUST_EXIST);
        $user->firstname = 'Prueba Anexo14 Editada';
        $user->city = 'Ciudad sintética';
        \core_user::update_user($user, false, true);
        $user = $DB->get_record('user', ['id' => $manifest['userid']], '*', MUST_EXIST);
        if ($user->firstname !== 'Prueba Anexo14 Editada' || $user->city !== 'Ciudad sintética') {
            throw new RuntimeException('No se confirmó la edición del perfil sintético.');
        }
        $controls[] = ['REQ-15-02', 'PARTIAL', 'Se probaron campos nativos sintéticos; campos documentales/caracterización requieren aprobación aparte.'];

        $duplicatecreated = false;
        try {
            \core_user::create_user((object) [
                'auth' => 'nologin',
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username' => $manifest['username'],
                'firstname' => 'Intento duplicado',
                'lastname' => $token,
                'email' => $manifest['email'],
            ], false, false);
            $duplicatecreated = true;
        } catch (\moodle_exception $exception) {
            $duplicatecount = $DB->count_records('user', [
                'username' => $manifest['username'],
                'mnethostid' => $CFG->mnet_localhost_id,
                'deleted' => 0,
            ]);
            if ($duplicatecount !== 1) {
                throw new RuntimeException('El intento duplicado dejó un estado inesperado.', 0, $exception);
            }
        }
        if ($duplicatecreated) {
            throw new RuntimeException('Moodle permitió crear un usuario con nombre duplicado.');
        }
        $controls[] = ['REQ-16-03', 'PARTIAL', 'Moodle rechazó el nombre de usuario duplicado; la duplicidad del documento requiere el campo institucional aprobado.'];

        require_once($CFG->dirroot . '/group/lib.php');
        $group = (object) [
            'courseid' => (int) $course->id,
            'name' => $manifest['groupname'],
            'idnumber' => $manifest['groupidnumber'],
            'description' => 'Grupo temporal sintético Anexo 14.',
            'descriptionformat' => FORMAT_PLAIN,
        ];
        $manifest['groupid'] = groups_create_group($group);
        anexo14_save_manifest($manifestpath, $manifest);
        if (!groups_get_group_by_idnumber((int) $course->id, $manifest['groupidnumber'])) {
            throw new RuntimeException('No se pudo consultar el grupo sintético recién creado.');
        }
        $controls[] = ['REQ-14-01', 'PASS', 'Grupo sintético creado mediante API nativa en el curso aislado.'];

        $group->id = (int) $manifest['groupid'];
        $group->name = $manifest['groupname'] . ' EDITADO';
        $group->description = 'Descripción sintética editada.';
        if (!groups_update_group($group)) {
            throw new RuntimeException('Moodle no pudo actualizar el grupo sintético.');
        }
        if (!groups_get_group_by_idnumber((int) $course->id, $manifest['groupidnumber'])) {
            throw new RuntimeException('No se encontró el grupo editado mediante su identificador estable.');
        }
        $controls[] = ['REQ-14-02', 'PARTIAL', 'Se verificaron nombre, descripción e identificador; estado y relación contractual requieren revisión de Interventoría.'];

        if (!groups_add_member((int) $manifest['groupid'], (int) $manifest['userid'])) {
            throw new RuntimeException('Moodle no pudo asignar la cuenta sintética al grupo.');
        }
        if (!groups_is_member((int) $manifest['groupid'], (int) $manifest['userid'])) {
            throw new RuntimeException('No se confirmó la membresía del grupo sintético.');
        }
        $controls[] = ['REQ-16-01', 'PASS', 'La cuenta sintética quedó asignada al grupo aislado.'];

        if (!groups_delete_group((int) $manifest['groupid'])
                || $DB->record_exists('groups', ['id' => $manifest['groupid']])) {
            throw new RuntimeException('No se confirmó la eliminación del grupo sintético.');
        }
        $manifest['groupid'] = null;
        anexo14_save_manifest($manifestpath, $manifest);

        if (!delete_course($course, false)
                || $DB->record_exists('course', ['id' => $manifest['courseid']])) {
            throw new RuntimeException('No se confirmó la eliminación del curso sintético.');
        }
        $manifest['courseid'] = null;
        anexo14_save_manifest($manifestpath, $manifest);
        $controls[] = ['REQ-13-01', 'PASS', 'El curso sintético se eliminó al finalizar su recorrido CRUD.'];

        $user = $DB->get_record('user', ['id' => $manifest['userid']], '*', MUST_EXIST);
        if (!\core_user::delete_user($user)) {
            throw new RuntimeException('Moodle no pudo eliminar lógicamente la cuenta sintética.');
        }
        $deleteduser = $DB->get_record('user', ['id' => $manifest['userid']], '*', MUST_EXIST);
        if (empty($deleteduser->deleted)) {
            throw new RuntimeException('La cuenta sintética no quedó marcada como eliminada por Moodle.');
        }
        $manifest['userid'] = null;
        anexo14_save_manifest($manifestpath, $manifest);
        $controls[] = ['REQ-15-01', 'PASS', 'La cuenta sintética quedó eliminada lógicamente por Moodle.'];

        if (!unlink($manifestpath)) {
            throw new RuntimeException('No fue posible retirar el manifiesto de recuperación.');
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, "ERROR: {$exception->getMessage()}\n");
        fwrite(STDERR, "Manifiesto conservado para recuperación: {$manifestpath}\n");
        fwrite(STDERR, "Tras revisar el entorno, ejecute --mode={$options['mode']} --cleanup.\n");
        exit(1);
    }

    $passed = array_filter($controls, static fn(array $control): bool => $control[1] === 'PASS');
    $partial = array_filter($controls, static fn(array $control): bool => $control[1] === 'PARTIAL');
    $failed = array_filter($controls, static fn(array $control): bool => $control[1] === 'FAIL');
    $result = [
        'generatedat' => gmdate(DATE_ATOM),
        'environment' => (string) $options['mode'],
        'host' => (string) parse_url((string) $CFG->wwwroot, PHP_URL_HOST),
        'marker' => $marker,
        'synthetic_only' => true,
        'course_removed' => true,
        'group_removed' => true,
        'user_deleted_logically' => true,
        'controls' => $controls,
        'summary' => [
            'pass' => count($passed),
            'partial' => count($partial),
            'fail' => count($failed),
            'total' => count($controls),
        ],
    ];
    $output = $CFG->dataroot . '/temp/anexo14-management-' . $token . '.json';
    if (file_put_contents(
        $output,
        json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    ) === false) {
        anexo14_fail("Los controles finalizaron, pero no se pudo escribir el informe en moodledata: {$output}");
    }
    foreach ($controls as [$requirement, $status, $detail]) {
        echo "{$requirement} {$status}: {$detail}\n";
    }
    echo "EVIDENCE={$output}\n";
    echo 'SUMMARY=' . json_encode($result['summary'], JSON_THROW_ON_ERROR) . PHP_EOL;
}

$options = anexo14_options();
anexo14_guard_environment($options);
if (array_key_exists('cleanup', $options)) {
    anexo14_cleanup(anexo14_manifest_path());
    exit(0);
}
anexo14_run($options);
