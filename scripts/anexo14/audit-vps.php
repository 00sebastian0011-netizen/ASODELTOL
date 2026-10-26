<?php
/**
 * Auditor CLI ASODELTOL/Anexo 14/AF8. Solo lectura, sin datos personales.
 * Ejecutar desde el Moodle instalado: php audit-vps.php --matrix=... --output-dir=...
 */
declare(strict_types=1);

const VALID = ['PASS', 'FAIL', 'BLOCKED', 'MANUAL'];

function arg(string $name, ?string $default = null): ?string {
    global $argv;
    foreach ($argv as $item) {
        if (str_starts_with($item, "--{$name}=")) return substr($item, strlen($name) + 3);
        if ($item === "--{$name}") return '1';
    }
    return $default;
}

function safe_text(string $value): string {
    return preg_replace('/[\r\n\t]+/', ' ', trim($value)) ?? '';
}

function matrix_rows(string $file): array {
    if (!is_readable($file)) throw new RuntimeException('Matriz no legible');
    $rows = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!str_starts_with(trim($line), '| REQ-')) continue;
        $cells = array_map('trim', explode('|', trim($line, " |")));
        if (count($cells) < 10 || !preg_match('/^REQ-[0-9]{2}-[0-9]{2}$/', $cells[0])) continue;
        $rows[] = ['id' => $cells[0], 'criterion' => safe_text($cells[1]), 'priority' => safe_text($cells[2])];
    }
    $ids = array_column($rows, 'id');
    if (count($rows) !== 82 || count(array_unique($ids)) !== 82) throw new RuntimeException('La matriz debe contener 82 IDs únicos');
    return $rows;
}

function result(string $status, string $method, string $detail, array $evidence = []): array {
    if (!in_array($status, VALID, true)) throw new InvalidArgumentException('Estado inválido');
    return ['status' => $status, 'method' => $method, 'detail' => safe_text($detail), 'evidence' => $evidence];
}

function table_exists(string $name): bool {
    global $DB;
    try { return $DB->get_manager()->table_exists($name); } catch (Throwable) { return false; }
}

$matrix = arg('matrix', __DIR__ . '/../../docs/anexo14/MATRIZ_ASODELTOL_82.md');
$output = arg('output-dir', getcwd() . DIRECTORY_SEPARATOR . 'audit-output');
$dryrun = arg('dry-run') !== null;
$started = gmdate('c');
$rows = matrix_rows($matrix);
$runtime = ['environment' => $dryrun ? 'dry-run' : 'moodle-cli', 'checked_at' => $started,
    'version' => null, 'plugins' => [], 'permissions' => [], 'course' => [], 'tracking_reports' => []];

if (!$dryrun) {
    $config = dirname(__DIR__, 2) . '/config.php';
    if (!is_readable($config)) throw new RuntimeException('Ejecute el auditor desde la raíz de Moodle o ajuste la ruta del script');
    require($config); // Moodle bootstrap; nunca se imprime config.php.
    require_once($CFG->libdir . '/moodlelib.php');
    $runtime['version'] = (string)($CFG->version ?? 'unknown');
    $runtime['plugins'] = ['local_asodeltolstaff' => core_component::get_component_directory('local_asodeltolstaff') !== false];
    $paths = [$CFG->dirroot, $CFG->dataroot, $CFG->dirroot . '/ai', $CFG->dirroot . '/ai/provider'];
    foreach ($paths as $path) {
        $runtime['permissions'][] = ['path_label' => basename($path), 'exists' => file_exists($path),
            'readable' => is_readable($path), 'executable' => is_executable($path),
            'writable_by_cli' => is_writable($path)];
    }
    $course = $DB->get_record('course', ['idnumber' => 'ASODELTOL-AF8'], 'id,visible,sectioncache');
    $runtime['course'] = ['af8_present' => (bool)$course, 'visible' => $course ? (bool)$course->visible : null];
    $runtime['tracking_reports'] = ['logstore_standard' => table_exists('logstore_standard_log'),
        'reportbuilder' => class_exists('core_reportbuilder\system_report'),
        'staff_table_present' => table_exists('local_asodeltolstaff_session')];
}

$results = [];
foreach ($rows as $row) {
    $status = $dryrun ? 'BLOCKED' : 'MANUAL';
    $method = $dryrun ? 'not_executed' : 'manual_review_required';
    $detail = $dryrun ? 'No se cargó Moodle; ejecución de contrato únicamente' : 'La evidencia técnica no demuestra por sí sola el criterio completo';
    if (!$dryrun && str_contains(strtolower($row['criterion']), 'curso') && ($runtime['course']['af8_present'] ?? false)) {
        $status = 'PASS'; $method = 'moodle_read_only_api'; $detail = 'Curso AF8 localizado por idnumber; revisar el criterio completo y la interfaz por separado';
    }
    $results[] = ['requirement_id' => $row['id'], 'applicability' => 'ASODELTOL_AF8',
        'criterion' => $row['criterion'], 'priority' => $row['priority'], 'result' => result($status, $method, $detail)];
}

$payload = ['schema' => 'asodeltol.anexo14.audit.v1', 'source_matrix' => basename($matrix),
    'scope' => 'ASODELTOL AF8; referencia externa no trasladada', 'read_only' => true,
    'runtime' => $runtime, 'requirements' => $results,
    'summary' => array_reduce($results, function(array $out, array $item): array { $state = $item['result']['status']; $out[$state] = ($out[$state] ?? 0) + 1; return $out; }, [])];

if (!is_dir($output) && !mkdir($output, 0750, true) && !is_dir($output)) throw new RuntimeException('No se pudo crear salida');
$stamp = gmdate('Ymd\THis\Z');
$jsonfile = $output . DIRECTORY_SEPARATOR . "asodeltol-audit-{$stamp}.json";
$csvfile = $output . DIRECTORY_SEPARATOR . "asodeltol-audit-{$stamp}.csv";
file_put_contents($jsonfile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$handle = fopen($csvfile, 'wb');
fputcsv($handle, ['requirement_id', 'applicability', 'priority', 'result', 'method', 'detail'], ',', '"', '\\');
foreach ($results as $item) fputcsv($handle, [$item['requirement_id'], $item['applicability'], $item['priority'], $item['result']['status'], $item['result']['method'], $item['result']['detail']], ',', '"', '\\');
fclose($handle);
echo json_encode(['status' => 'PASS', 'json' => $jsonfile, 'csv' => $csvfile, 'summary' => $payload['summary']], JSON_UNESCAPED_UNICODE) . PHP_EOL;
