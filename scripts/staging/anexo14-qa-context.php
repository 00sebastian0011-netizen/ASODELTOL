<?php
// ASODELTOL - sólo lectura; exclusivamente staging local Docker.
// Se monta desde scripts/staging a /opt/asodeltol/scripts.
declare(strict_types=1);
define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

try {
    global $CFG, $DB;
    if ((string) $CFG->wwwroot !== 'http://127.0.0.1:8053'
            || !str_starts_with((string) $CFG->release, '5.3')
            || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
        throw new RuntimeException('Entorno diferente de Moodle 5.3 / PHP 8.4 local esperado.');
    }
    $category = $DB->get_record('course_categories', ['idnumber' => 'ASODELTOL-STAGING'], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['idnumber' => 'ASODELTOL-AF8'], '*', MUST_EXIST);
    if ((int) $course->category !== (int) $category->id
            || (int) $course->visible !== 0 || (int) $category->visible !== 0) {
        throw new RuntimeException('La categoría o el curso no coincide con el staging oculto.');
    }
    $pluginversion = (string) $DB->get_field('config_plugins', 'value', [
        'plugin' => 'local_asodeltolstaff', 'name' => 'version',
    ]);
    if ($pluginversion === '') {
        throw new RuntimeException('El plugin local_asodeltolstaff no está instalado en el staging.');
    }
    $sessioncount = (int) $DB->count_records('local_asstaff_session', ['courseid' => $course->id]);
    $prefix = (string) ($CFG->phpunit_prefix ?? '');
    $dataroot = (string) ($CFG->phpunit_dataroot ?? '');
    $safeprefix = preg_match('/^[a-z][a-z0-9_]+_$/D', $prefix) === 1
        && $prefix !== (string) $CFG->prefix;
    // Canonical paths must exist and neither may contain the other.
    $testroot = $dataroot === '' ? false : realpath($dataroot);
    $liveroot = realpath((string) $CFG->dataroot);
    $safedataroot = $testroot !== false && $liveroot !== false
        && $testroot !== $liveroot
        && !str_starts_with($testroot . '/', $liveroot . '/')
        && !str_starts_with($liveroot . '/', $testroot . '/');
    // A distinct prefix alone does not satisfy this project's isolation policy.
    $safedatabase = !empty($CFG->phpunit_dbname) && !empty($CFG->phpunit_dbuser)
        && (string) $CFG->phpunit_dbname !== (string) $CFG->dbname
        && (string) $CFG->phpunit_dbuser !== (string) $CFG->dbuser;
    $hasxml = is_file('/var/www/html/phpunit.xml');
    $result = [
        'wwwroot' => (string) $CFG->wwwroot,
        'release' => (string) $CFG->release,
        'php' => PHP_VERSION,
        'course_id' => (int) $course->id,
        'course_idnumber' => (string) $course->idnumber,
        'category_hidden' => true,
        'course_hidden' => true,
        'plugin_version' => $pluginversion,
        'session_count' => $sessioncount,
        // Grant verification is performed by run-isolated-phpunit.py, not inferred here.
        'phpunit_ready' => false,
        'phpunit_config_separated' => $safeprefix && $safedataroot && $safedatabase && $hasxml,
        'phpunit_requires_grant_verification' => true,
        'phpunit_has_separate_database_account' => $safedatabase,
        'phpunit_has_prefix' => $safeprefix,
        'phpunit_has_dataroot' => $safedataroot,
        'phpunit_has_xml' => $hasxml,
    ];
    echo 'ASODELTOL_QA_CONTEXT=' . json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'QA_CONTEXT_FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
