<?php
// Local-only institutional UX orchestrator for Moodle 5.3 Boost.
// Provides branded, accessible, responsive styling for ASODELTOL.

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');

const M12_COURSE_IDNUMBER = 'ASODELTOL-AF8';
const M12_CATEGORY_IDNUMBER = 'ASODELTOL-STAGING';
// Propuesta cromática y visual provisional (sujeta a confirmación oficial de identidad gráfica por parte de ASODELTOL).
const M12_BRAND_COLOR = '#1B4D3E';
const M12_SITE_FULLNAME = 'Aula Virtual ASODELTOL';
const M12_SITE_SHORTNAME = 'ASODELTOL';
const M12_SNAPSHOT_VERSION = 2;
const M12_SNAPSHOT_PROVENANCE = 'pre_m12_live_capture';

/**
 * Return resolved snapshot file path located in writable dataroot.
 *
 * @return string File path.
 */
function m12_snapshot_file(): string {
    global $CFG;
    $custom = getenv('M12_SNAPSHOT_FILE');
    if (!empty($custom)) {
        return (string) $custom;
    }
    $dataroot = !empty($CFG->dataroot) ? rtrim($CFG->dataroot, '/') : '/var/www/moodledata';
    return $dataroot . '/theme-snapshot-pre-m12.json';
}

/**
 * Return with diagnostic error.
 *
 * @param string $message Error message.
 * @return never
 */
function m12_fail(string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

/**
 * Get the staging administrator.
 *
 * @return stdClass Admin user.
 */
function m12_admin(): stdClass {
    $admin = get_admin();
    if (!$admin || empty($admin->id)) {
        m12_fail('No se encontró el administrador del staging local.');
    }
    \core\session\manager::set_user($admin);
    return $admin;
}

/**
 * Get AF8 course record.
 *
 * @return stdClass Course record.
 */
function m12_course(): stdClass {
    global $DB;
    return $DB->get_record('course', ['idnumber' => M12_COURSE_IDNUMBER], '*', MUST_EXIST);
}

/**
 * Return SCSS variables (pre-SCSS) for Boost theme.
 *
 * @return string SCSS variables.
 */
function m12_scsspre(): string {
    return <<<'SCSS'
// ASODELTOL Institutional Palette - Tolima Rural Tourism & Sustainable Development
$brand-primary: #1B4D3E;
$primary: #1B4D3E;
$primary-hover: #13382D;
$secondary: #C59B27;
$secondary-light: #E9D7A5;
$success: #15803D;
$danger: #B91C1C;
$warning: #B45309;
$info: #1D4ED8;

// Body & Typography
$body-bg: #F8FAFC;
$body-color: #1E293B;
$font-family-base: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
$headings-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
$headings-color: #0F291E;
$headings-font-weight: 700;
$link-color: #1B4D3E;
$link-hover-color: #13382D;

// Component Radii & Elevation
$border-radius: 0.5rem;
$border-radius-lg: 0.75rem;
$border-radius-sm: 0.375rem;

// Navigation Bar
$navbar-bg: #1B4D3E;
$navbar-dark-color: rgba(255, 255, 255, 0.95);
$navbar-dark-hover-color: #FFFFFF;
$navbar-dark-active-color: #FFFFFF;
SCSS;
}

/**
 * Return custom SCSS rules (post-SCSS) for Boost theme.
 *
 * @return string SCSS rules.
 */
function m12_scss(): string {
    return <<<'SCSS'
/* ==========================================================================
   AULA VIRTUAL ASODELTOL - INSTITUTIONAL UX ENHANCEMENTS (MOODLE 5.3 BOOST)
   ========================================================================== */

/* Accessibility: Universal High-Visibility Focus Outline */
:focus-visible {
    outline: 3px solid #1B4D3E !important;
    outline-offset: 2px !important;
}

/* Universal Page Canvas - Accessible & Naturally Responsive (No artificial overflow hiding) */
body {
    background-color: #F8FAFC;
    color: #1E293B;
    font-size: 0.95rem;
    line-height: 1.6;
}

/* Ensure embedded media, preformatted text and code adapt without overflowing container */
img, svg, video, canvas {
    max-width: 100%;
    height: auto;
}

pre, code {
    white-space: pre-wrap;
    word-break: break-word;
}

/* Off-canvas containment: ensure closed drawers do not bleed into layout space */
.drawer:not(.show),
.drawer.hidden,
.message-drawer.hidden,
[data-region="right-hand-drawer"].hidden,
.drawer[aria-hidden="true"] {
    visibility: hidden;
}

.drawer.show,
.message-drawer:not(.hidden) {
    visibility: visible;
}

/* --------------------------------------------------------------------------
   1. PRIMARY NAVIGATION BAR
   -------------------------------------------------------------------------- */
.navbar.fixed-top,
.navbar.navbar-boots {
    background: linear-gradient(135deg, #1B4D3E 0%, #13382D 100%) !important;
    border-bottom: 2px solid #C59B27;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    min-height: 60px;
}

.navbar .navbar-brand {
    color: #FFFFFF !important;
    font-weight: 800;
    font-size: 1.15rem;
    letter-spacing: -0.01em;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.navbar .navbar-brand::after {
    content: "DIPLOMADO AF8";
    font-size: 0.65rem;
    font-weight: 700;
    background-color: #C59B27;
    color: #0F291E;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    letter-spacing: 0.05em;
    margin-left: 0.5rem;
}

.navbar .nav-link {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 600;
    padding: 0.5rem 0.85rem !important;
    border-radius: 0.375rem;
    transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
}

.navbar .nav-link:hover,
.navbar .nav-link:focus {
    color: #FFFFFF !important;
    background-color: rgba(255, 255, 255, 0.15);
}

.navbar .userinitials {
    background-color: #C59B27 !important;
    color: #0F291E !important;
    font-weight: 700;
}

/* --------------------------------------------------------------------------
   2. INSTITUTIONAL LOGIN PAGE
   -------------------------------------------------------------------------- */
body.pagelayout-login {
    background: radial-gradient(circle at 15% 20%, #E8F5E9 0%, #F1F5F9 50%, #E2E8F0 100%) !important;
    min-height: 100vh;
}

body.pagelayout-login #page-wrapper {
    background: transparent !important;
}

body.pagelayout-login .login-container {
    max-width: 440px;
    margin: 2.5rem auto;
    padding: 0 1rem;
}

body.pagelayout-login .login-form {
    background-color: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 1rem !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04) !important;
    padding: 2.25rem 2rem !important;
}

body.pagelayout-login .login-form h1,
body.pagelayout-login .login-form .h2 {
    color: #1B4D3E !important;
    font-weight: 800 !important;
    font-size: 1.6rem !important;
    text-align: center;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

body.pagelayout-login .login-form .login-instructions,
body.pagelayout-login .login-form .login-subheading {
    text-align: center;
    color: #475569;
    font-size: 0.9rem;
    margin-bottom: 1.75rem;
}

body.pagelayout-login .login-form .form-control {
    border: 1.5px solid #CBD5E1 !important;
    border-radius: 0.5rem !important;
    min-height: 46px !important;
    font-size: 0.95rem;
    padding: 0.65rem 0.85rem;
    transition: all 0.2s ease-in-out;
}

body.pagelayout-login .login-form .form-control:focus {
    border-color: #1B4D3E !important;
    box-shadow: 0 0 0 3px rgba(27, 77, 62, 0.2) !important;
}

body.pagelayout-login .login-form .btn-primary,
body.pagelayout-login #loginbtn {
    background: linear-gradient(135deg, #1B4D3E 0%, #246552 100%) !important;
    border: none !important;
    color: #FFFFFF !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
    min-height: 48px !important;
    border-radius: 0.5rem !important;
    box-shadow: 0 4px 6px -1px rgba(27, 77, 62, 0.25) !important;
    transition: all 0.2s ease-in-out !important;
    width: 100%;
    margin-top: 1rem;
}

body.pagelayout-login .login-form .btn-primary:hover,
body.pagelayout-login #loginbtn:hover {
    background: linear-gradient(135deg, #13382D 0%, #1B4D3E 100%) !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 12px -2px rgba(27, 77, 62, 0.35) !important;
}

/* --------------------------------------------------------------------------
   3. STUDENT DASHBOARD (/my/)
   -------------------------------------------------------------------------- */
.asodeltol-hero-card {
    background: linear-gradient(135deg, #1B4D3E 0%, #246552 60%, #C59B27 100%);
    border-radius: 1rem;
    padding: 2rem;
    color: #FFFFFF;
    margin-bottom: 2rem;
    box-shadow: 0 10px 15px -3px rgba(27, 77, 62, 0.2);
}

.asodeltol-hero-card h1,
.asodeltol-hero-card h2 {
    color: #FFFFFF !important;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.asodeltol-hero-card p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.05rem;
    margin-bottom: 0;
}

/* Course Cards in Dashboard */
.dashboard-card,
.block_myoverview .course-info-container {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 0.75rem !important;
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.04) !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08) !important;
}

.progress-bar {
    background-color: #1B4D3E !important;
}

/* --------------------------------------------------------------------------
   4. DIPLOMADO AF8 COURSE VIEW (/course/view.php)
   -------------------------------------------------------------------------- */
.course-content {
    margin-top: 1.5rem;
}

/* Course Header Banner */
.page-header-headings h1 {
    color: #1B4D3E !important;
    font-weight: 800;
    letter-spacing: -0.02em;
}

/* Section Container Styling */
.course-content ul.topics li.section,
.course-content .section.main {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 1.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

/* General Resources Section (Section 0) */
.course-content ul.topics li.section#section-0,
.course-content .section.main#section-0 {
    border-left: 5px solid #C59B27 !important;
    background: linear-gradient(180deg, #FFFFFF 0%, #FDFCF9 100%);
}

.course-content ul.topics li.section#section-0 .sectionname,
.course-content .section.main#section-0 .sectionname {
    color: #8C6A15 !important;
    font-size: 1.25rem;
    font-weight: 700;
}

/* Academic Units (Sections 1 to 5) */
.course-content ul.topics li.section:not(#section-0) .sectionname,
.course-content .section.main:not(#section-0) .sectionname {
    color: #1B4D3E !important;
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 0.5rem;
}

/* Locked / Restricted Unit Visual State */
.course-content .section.is-restricted,
.course-content .section.availability-locked,
.course-content .section.hidden.section-hidden {
    background: #F8FAFC !important;
    border: 1.5px dashed #CBD5E1 !important;
    opacity: 0.9;
}

.course-content .section .availabilityinfo {
    background-color: #FEF3C7 !important;
    color: #92400E !important;
    border: 1px solid #FDE68A !important;
    border-radius: 0.5rem !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    margin-top: 0.75rem !important;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.course-content .section .availabilityinfo::before {
    content: "🔒 ";
    font-size: 1rem;
}

/* Activity Items Inside Units */
.course-content .activity-item {
    border: 1px solid #F1F5F9 !important;
    border-radius: 0.5rem !important;
    padding: 0.875rem 1rem !important;
    margin-bottom: 0.5rem !important;
    background-color: #FFFFFF !important;
    transition: all 0.15s ease-in-out;
}

.course-content .activity-item:hover {
    border-color: #CBD5E1 !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
}

.course-content .activity-item .activityiconcontainer {
    background-color: #EFF6FF !important;
    border-radius: 0.5rem !important;
}

.course-content .activity-item .activityiconcontainer.assign {
    background-color: #ECFDF5 !important;
}

.course-content .activity-item .activityiconcontainer.forum {
    background-color: #EFF6FF !important;
}

.course-content .activity-item .activityname a {
    color: #1E293B !important;
    font-weight: 600;
}

.course-content .activity-item .activityname a:hover {
    color: #1B4D3E !important;
}

/* Completion Badges */
.badge.badge-success,
.badge.bg-success {
    background-color: #15803D !important;
    color: #FFFFFF !important;
    font-weight: 700;
}

.badge.badge-danger,
.badge.bg-danger {
    background-color: #B91C1C !important;
    color: #FFFFFF !important;
    font-weight: 700;
}

/* --------------------------------------------------------------------------
   5. RESPONSIVE BREAKPOINTS (375px, 768px, 1440px)
   -------------------------------------------------------------------------- */
@media (max-width: 576px) {
    /* Mobile - 375px */
    .navbar .navbar-brand {
        font-size: 1rem;
    }
    
    .navbar .navbar-brand::after {
        display: none;
    }

    body.pagelayout-login .login-container {
        padding: 0 0.5rem;
        margin-top: 1rem;
    }

    body.pagelayout-login .login-form {
        padding: 1.5rem 1.25rem !important;
    }

    .asodeltol-hero-card {
        padding: 1.25rem;
    }

    .course-content ul.topics li.section,
    .course-content .section.main {
        padding: 1rem;
    }
}

@media (min-width: 768px) and (max-width: 991.98px) {
    /* Tablet - 768px */
    .course-content ul.topics li.section,
    .course-content .section.main {
        padding: 1.25rem;
    }
}

@media (min-width: 1200px) {
    /* Desktop - 1440px */
    #page.drawers .main-inner {
        max-width: 1360px;
        margin: 0 auto;
    }
}
SCSS;
}

/**
 * Calculate a stable checksum for a snapshot payload.
 *
 * @param array $snapshot Snapshot payload.
 * @return string SHA-256 checksum.
 */
function m12_snapshot_checksum(array $snapshot): string {
    unset($snapshot['payload_sha256']);
    ksort($snapshot);
    $payload = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar el snapshot para calcular su integridad.');
    }
    return hash('sha256', $payload);
}

/**
 * Read the live values governed by this mission.
 *
 * @return array Current state.
 */
function m12_current_state(): array {
    global $SITE;
    $boost = get_config('theme_boost');
    return [
        'theme' => (string) (get_config('core', 'theme') ?: 'boost'),
        'brandcolor' => (string) ($boost->brandcolor ?? ''),
        'scsspre' => (string) ($boost->scsspre ?? ''),
        'scss' => (string) ($boost->scss ?? ''),
        'site_fullname' => (string) $SITE->fullname,
        'site_shortname' => (string) $SITE->shortname,
    ];
}

/**
 * Read the persisted values directly from Moodle tables for post-write verification.
 *
 * @return array Persisted state.
 */
function m12_persisted_state(): array {
    global $DB, $SITE;
    $theme = $DB->get_field('config', 'value', ['name' => 'theme']);
    $brandcolor = $DB->get_field('config_plugins', 'value', ['plugin' => 'theme_boost', 'name' => 'brandcolor']);
    $scsspre = $DB->get_field('config_plugins', 'value', ['plugin' => 'theme_boost', 'name' => 'scsspre']);
    $scss = $DB->get_field('config_plugins', 'value', ['plugin' => 'theme_boost', 'name' => 'scss']);
    $site = $DB->get_record('course', ['id' => $SITE->id], 'fullname,shortname', MUST_EXIST);
    return [
        'theme' => $theme === false ? 'boost' : (string) $theme,
        'brandcolor' => $brandcolor === false ? '' : (string) $brandcolor,
        'scsspre' => $scsspre === false ? '' : (string) $scsspre,
        'scss' => $scss === false ? '' : (string) $scss,
        'site_fullname' => (string) $site->fullname,
        'site_shortname' => (string) $site->shortname,
    ];
}

/**
 * Compare the six values governed by the M12 snapshot.
 *
 * @param array $actual Actual state.
 * @param array $expected Expected state or snapshot.
 * @return bool True when all governed values match.
 */
function m12_states_equal(array $actual, array $expected): bool {
    foreach (['theme', 'brandcolor', 'scsspre', 'scss', 'site_fullname', 'site_shortname'] as $key) {
        if ((string) $actual[$key] !== (string) $expected[$key]) {
            return false;
        }
    }
    return true;
}

/**
 * Build a versioned snapshot from an observed state.
 *
 * @param array $state State to preserve.
 * @return array Snapshot payload.
 */
function m12_build_snapshot(array $state): array {
    $snapshot = [
        'snapshot_version' => M12_SNAPSHOT_VERSION,
        'captured_at' => gmdate('c'),
        'theme' => (string) $state['theme'],
        'brandcolor' => (string) $state['brandcolor'],
        'scsspre' => (string) $state['scsspre'],
        'scss' => (string) $state['scss'],
        'site_fullname' => (string) $state['site_fullname'],
        'site_shortname' => (string) $state['site_shortname'],
        'provenance' => M12_SNAPSHOT_PROVENANCE,
    ];
    $snapshot['payload_sha256'] = m12_snapshot_checksum($snapshot);
    return $snapshot;
}

/**
 * Validate and read a versioned snapshot.
 *
 * Legacy snapshots are rejected because their historical provenance and
 * integrity cannot be demonstrated after the former seed-snapshot workflow.
 *
 * @param string|null $path Optional path to snapshot file.
 * @return array|null Validated snapshot data array, or null if file does not exist.
 * @throws RuntimeException If file exists but cannot be trusted.
 */
function m12_read_snapshot(?string $path = null): ?array {
    $file = $path ?: m12_snapshot_file();
    if (!file_exists($file)) {
        return null;
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        throw new RuntimeException('El archivo de snapshot existe pero está vacío o no es legible.');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('El archivo de snapshot contiene JSON inválido o corrupto.');
    }

    $requiredkeys = [
        'snapshot_version',
        'captured_at',
        'theme',
        'brandcolor',
        'scsspre',
        'scss',
        'site_fullname',
        'site_shortname',
        'provenance',
        'payload_sha256',
    ];
    foreach ($requiredkeys as $key) {
        if (!array_key_exists($key, $data)) {
            throw new RuntimeException("El snapshot está incompleto: falta el campo obligatorio '{$key}'.");
        }
    }

    if ((int) $data['snapshot_version'] !== M12_SNAPSHOT_VERSION) {
        throw new RuntimeException('El snapshot es heredado o usa una versión no verificable; se requiere una captura pre-Apply fiable.');
    }

    foreach (['captured_at', 'theme', 'brandcolor', 'scsspre', 'scss', 'site_fullname', 'site_shortname',
        'provenance', 'payload_sha256'] as $key) {
        if (!is_string($data[$key])) {
            throw new RuntimeException("El snapshot contiene un tipo inválido en '{$key}'.");
        }
    }

    if (empty($data['theme']) || empty($data['site_fullname']) || empty($data['site_shortname'])) {
        throw new RuntimeException('El snapshot contiene campos obligatorios vacíos.');
    }
    if ($data['provenance'] !== M12_SNAPSHOT_PROVENANCE) {
        throw new RuntimeException('La procedencia del snapshot no corresponde a una captura en vivo inmediatamente anterior a Apply.');
    }

    $expectedchecksum = m12_snapshot_checksum($data);
    if (!hash_equals($expectedchecksum, $data['payload_sha256'])) {
        throw new RuntimeException('La comprobación SHA-256 del contenido del snapshot no coincide.');
    }
    return $data;
}

/**
 * Persist a new snapshot without ever overwriting an existing path.
 *
 * @param string $path Destination path.
 * @param array $snapshot Validated snapshot payload.
 */
function m12_write_snapshot_exclusive(string $path, array $snapshot): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException("No se pudo crear el directorio para el snapshot: {$directory}");
    }

    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar el snapshot.');
    }

    $handle = fopen($path, 'x+b');
    if ($handle === false) {
        throw new RuntimeException('El snapshot ya existe o no se pudo crear de forma exclusiva; no se sobrescribió.');
    }

    $complete = false;
    try {
        $bytes = fwrite($handle, $json);
        if ($bytes === false || $bytes !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('No se pudo persistir el snapshot completo.');
        }
        if (function_exists('fsync') && !fsync($handle)) {
            throw new RuntimeException('No se pudo sincronizar el snapshot con el almacenamiento.');
        }
        $complete = true;
    } finally {
        fclose($handle);
        if (!$complete && file_exists($path)) {
            unlink($path);
        }
    }

    if (m12_read_snapshot($path) === null) {
        throw new RuntimeException('El snapshot no pudo verificarse después de su escritura.');
    }
}

/**
 * Inspect snapshot metadata without changing it.
 *
 * @param string|null $path Optional snapshot path.
 * @return array Read-only snapshot status.
 */
function m12_snapshot_status(?string $path = null): array {
    $file = $path ?: m12_snapshot_file();
    $exists = file_exists($file);
    $status = [
        'exists' => $exists,
        'sha256' => $exists ? hash_file('sha256', $file) : null,
        'bytes' => $exists ? filesize($file) : 0,
        'valid' => false,
        'validation' => $exists ? 'invalid' : 'absent',
    ];
    if (!$exists) {
        return $status;
    }

    try {
        $snapshot = m12_read_snapshot($file);
        $status['valid'] = $snapshot !== null;
        $status['validation'] = 'valid';
        $status['version'] = $snapshot['snapshot_version'];
        $status['provenance'] = $snapshot['provenance'];
    } catch (RuntimeException $e) {
        $status['validation_error'] = $e->getMessage();
    }
    return $status;
}

/**
 * Apply institutional UX to theme_boost.
 */
function m12_apply(): void {
    global $DB, $SITE;
    m12_admin();

    $snapshotfile = m12_snapshot_file();
    $currentstate = m12_current_state();
    $snapshotcreated = false;
    try {
        $existingsnapshot = m12_read_snapshot($snapshotfile);
    } catch (RuntimeException $e) {
        m12_fail('No se puede aplicar M12 porque el snapshot existente no es válido ni verificable: ' . $e->getMessage());
    }

    if ($existingsnapshot === null) {
        $isalreadym12 = ($currentstate['brandcolor'] === M12_BRAND_COLOR
            || str_contains($currentstate['scss'], 'AULA VIRTUAL ASODELTOL')
            || $currentstate['site_fullname'] === M12_SITE_FULLNAME);
        if ($isalreadym12) {
            m12_fail('Operación rechazada: M12 ya está aplicada y no existe un snapshot pre-M12 verificable. '
                . 'No se inventará ni capturará el estado modificado como línea base.');
        }
        try {
            m12_write_snapshot_exclusive($snapshotfile, m12_build_snapshot($currentstate));
            $snapshotcreated = true;
        } catch (RuntimeException $e) {
            m12_fail('No se pudo crear el snapshot pre-Apply sin sobrescritura: ' . $e->getMessage());
        }
    }

    $transaction = $DB->start_delegated_transaction();
    try {
        set_config('brandcolor', M12_BRAND_COLOR, 'theme_boost');
        set_config('scsspre', m12_scsspre(), 'theme_boost');
        set_config('scss', m12_scss(), 'theme_boost');
        set_config('theme', 'boost', 'core');
        if ($SITE->fullname !== M12_SITE_FULLNAME || $SITE->shortname !== M12_SITE_SHORTNAME) {
            $DB->update_record('course', (object) [
                'id' => $SITE->id,
                'fullname' => M12_SITE_FULLNAME,
                'shortname' => M12_SITE_SHORTNAME,
            ]);
        }
        $transaction->allow_commit();
    } catch (Throwable $t) {
        try {
            $transaction->rollback($t);
        } catch (Throwable $rollbackerror) {
            m12_fail('Apply no pudo completar su transacción; el snapshot se conserva. Detalle: '
                . $rollbackerror->getMessage());
        }
        m12_fail('Apply fue revertido y el snapshot se conserva para recuperación.');
    }

    $SITE->fullname = M12_SITE_FULLNAME;
    $SITE->shortname = M12_SITE_SHORTNAME;
    try {
        theme_reset_all_caches();
    } catch (Throwable $t) {
        m12_fail('La configuración M12 se guardó, pero falló la regeneración de caché. '
            . "El snapshot se conserva para recuperación: {$snapshotfile}. Detalle: " . $t->getMessage());
    }

    echo json_encode([
        'status' => 'applied',
        'theme' => 'boost',
        'brandcolor' => M12_BRAND_COLOR,
        'site_fullname' => M12_SITE_FULLNAME,
        'snapshot_file' => $snapshotfile,
        'snapshot_created' => $snapshotcreated,
        'snapshot_verified' => m12_snapshot_status($snapshotfile)['valid'],
        'snapshot_sha256' => hash_file('sha256', $snapshotfile),
        'scsspre_bytes' => strlen(m12_scsspre()),
        'scss_bytes' => strlen(m12_scss()),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Restore the versioned pre-M12 state with transactional database writes.
 *
 * Cache regeneration and snapshot deletion occur after the database commit.
 * A failure in either step leaves the snapshot available for an idempotent retry,
 * so this is a recoverable restoration rather than an all-system atomic claim.
 */
function m12_revert(): void {
    global $DB, $SITE;
    m12_admin();

    $snapshotfile = m12_snapshot_file();
    if (!file_exists($snapshotfile)) {
        m12_fail("Operación rechazada: no existe el archivo de snapshot ({$snapshotfile}). No se alteró la configuración actual.");
    }

    try {
        $snapshot = m12_read_snapshot($snapshotfile);
    } catch (RuntimeException $e) {
        m12_fail('Operación rechazada: el snapshot es inválido, no verificable o está corrupto ('
            . $e->getMessage() . '). No se alteró la configuración actual.');
    }
    if ($snapshot === null) {
        m12_fail('Operación rechazada: no se pudo leer el snapshot. No se alteró la configuración actual.');
    }

    $snapshotsha = hash_file('sha256', $snapshotfile);
    $transaction = $DB->start_delegated_transaction();
    try {
        set_config('brandcolor', $snapshot['brandcolor'], 'theme_boost');
        set_config('scsspre', $snapshot['scsspre'], 'theme_boost');
        set_config('scss', $snapshot['scss'], 'theme_boost');
        set_config('theme', $snapshot['theme'], 'core');
        if ($SITE->fullname !== $snapshot['site_fullname'] || $SITE->shortname !== $snapshot['site_shortname']) {
            $DB->update_record('course', (object) [
                'id' => $SITE->id,
                'fullname' => $snapshot['site_fullname'],
                'shortname' => $snapshot['site_shortname'],
            ]);
        }
        if (!m12_states_equal(m12_persisted_state(), $snapshot)) {
            throw new RuntimeException('La verificación transaccional no coincide con el snapshot.');
        }
        $transaction->allow_commit();
    } catch (Throwable $t) {
        try {
            $transaction->rollback($t);
        } catch (Throwable $rollbackerror) {
            m12_fail('La restauración transaccional fue rechazada y Moodle solicitó rollback. '
                . "El snapshot se conserva en {$snapshotfile}. Detalle: " . $rollbackerror->getMessage());
        }
        m12_fail("La restauración transaccional fue revertida. El snapshot se conserva en {$snapshotfile}.");
    }

    $SITE->fullname = $snapshot['site_fullname'];
    $SITE->shortname = $snapshot['site_shortname'];
    if (!m12_states_equal(m12_persisted_state(), $snapshot)) {
        m12_fail("La transacción terminó, pero la verificación persistida falló. El snapshot se conserva en {$snapshotfile}.");
    }

    try {
        theme_reset_all_caches();
    } catch (Throwable $t) {
        m12_fail('La base de datos fue restaurada, pero falló la regeneración de caché. '
            . "El snapshot se conserva para reintento en {$snapshotfile}. Detalle: " . $t->getMessage());
    }

    if (!unlink($snapshotfile)) {
        m12_fail("La configuración fue restaurada, pero no se pudo retirar el snapshot. Se conserva en {$snapshotfile} para revisión.");
    }

    echo json_encode([
        'status' => 'reverted',
        'restoration_semantics' => 'transactional_database_and_recoverable_cache_finalization',
        'theme' => $snapshot['theme'],
        'brandcolor' => $snapshot['brandcolor'],
        'scsspre_length' => strlen($snapshot['scsspre']),
        'scss_length' => strlen($snapshot['scss']),
        'site_fullname' => $snapshot['site_fullname'],
        'site_shortname' => $snapshot['site_shortname'],
        'snapshot_sha256' => $snapshotsha,
        'snapshot_deleted' => !file_exists($snapshotfile),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Persist synthetic state atomically inside the disposable snapshot sandbox.
 *
 * @param string $path State file path.
 * @param array $state Synthetic state.
 */
function m12_sandbox_write_state(string $path, array $state): void {
    $temporary = $path . '.tmp';
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        if (file_exists($temporary)) {
            unlink($temporary);
        }
        throw new RuntimeException('No se pudo persistir el estado sintético aislado.');
    }
}

/**
 * Read synthetic state from the disposable snapshot sandbox.
 *
 * @param string $path State file path.
 * @return array Synthetic state.
 */
function m12_sandbox_read_state(string $path): array {
    $raw = file_get_contents($path);
    $state = $raw === false ? null : json_decode($raw, true);
    if (!is_array($state)) {
        throw new RuntimeException('El estado sintético aislado no es legible.');
    }
    return $state;
}

/**
 * Apply M12 values only to a disposable file-backed state.
 *
 * @param string $statefile Synthetic state file.
 * @param string $snapshotfile Synthetic snapshot file.
 */
function m12_sandbox_apply(string $statefile, string $snapshotfile): void {
    $state = m12_sandbox_read_state($statefile);
    $snapshot = m12_read_snapshot($snapshotfile);
    if ($snapshot === null) {
        $isalreadym12 = ($state['brandcolor'] === M12_BRAND_COLOR
            || str_contains((string) $state['scss'], 'AULA VIRTUAL ASODELTOL')
            || $state['site_fullname'] === M12_SITE_FULLNAME);
        if ($isalreadym12) {
            throw new RuntimeException('El estado sintético ya contiene M12 sin snapshot fiable.');
        }
        m12_write_snapshot_exclusive($snapshotfile, m12_build_snapshot($state));
    }
    $state['theme'] = 'boost';
    $state['brandcolor'] = M12_BRAND_COLOR;
    $state['scsspre'] = m12_scsspre();
    $state['scss'] = m12_scss();
    $state['site_fullname'] = M12_SITE_FULLNAME;
    $state['site_shortname'] = M12_SITE_SHORTNAME;
    m12_sandbox_write_state($statefile, $state);
}

/**
 * Revert only a disposable file-backed state.
 *
 * @param string $statefile Synthetic state file.
 * @param string $snapshotfile Synthetic snapshot file.
 * @param bool $simulatefailure Simulate a failure before committing state.
 */
function m12_sandbox_revert(string $statefile, string $snapshotfile, bool $simulatefailure = false): void {
    $snapshot = m12_read_snapshot($snapshotfile);
    if ($snapshot === null) {
        throw new RuntimeException('No existe snapshot sintético aislado.');
    }
    if ($simulatefailure) {
        throw new RuntimeException('Fallo sintético previo al commit de restauración.');
    }
    $restored = array_intersect_key($snapshot, array_flip([
        'theme', 'brandcolor', 'scsspre', 'scss', 'site_fullname', 'site_shortname',
    ]));
    m12_sandbox_write_state($statefile, $restored);
    if (!m12_states_equal(m12_sandbox_read_state($statefile), $snapshot)) {
        throw new RuntimeException('La verificación del estado sintético restaurado falló.');
    }
    if (!unlink($snapshotfile)) {
        throw new RuntimeException('No se pudo retirar el snapshot sintético después de restaurarlo.');
    }
}

/**
 * Exercise destructive snapshot scenarios only in an owned disposable sandbox.
 */
function m12_snapshot_selftest(): void {
    global $CFG;
    $testroot = rtrim($CFG->dataroot, '/') . '/temp/m12-snapshot-selftest-' . bin2hex(random_bytes(8));
    $statefile = $testroot . '/state.json';
    $snapshotfile = $testroot . '/snapshot.json';
    $controls = [];
    $baseline = [
        'theme' => 'boost',
        'brandcolor' => '#123456',
        'scsspre' => '// synthetic isolated pre-SCSS',
        'scss' => '// synthetic isolated SCSS',
        'site_fullname' => 'M12 synthetic snapshot sandbox',
        'site_shortname' => 'M12-SANDBOX',
    ];

    if (!mkdir($testroot, 0770, true) && !is_dir($testroot)) {
        m12_fail('No se pudo crear el sandbox desechable de snapshots.');
    }

    $failure = null;
    try {
        m12_sandbox_write_state($statefile, $baseline);

        $statehash = hash_file('sha256', $statefile);
        try {
            m12_sandbox_revert($statefile, $snapshotfile);
            $missingrejected = false;
        } catch (RuntimeException $e) {
            $missingrejected = hash_equals($statehash, hash_file('sha256', $statefile)) && !file_exists($snapshotfile);
        }
        $controls[] = ['id' => 'M12-ISO-NEG-001', 'passed' => $missingrejected,
            'detail' => 'Snapshot ausente rechazado sin cambiar el estado sintético.'];

        file_put_contents($snapshotfile, '{ corrupt_json: true }', LOCK_EX);
        $corrupthash = hash_file('sha256', $snapshotfile);
        try {
            m12_sandbox_revert($statefile, $snapshotfile);
            $corruptrejected = false;
        } catch (RuntimeException $e) {
            $corruptrejected = hash_equals($statehash, hash_file('sha256', $statefile))
                && hash_equals($corrupthash, hash_file('sha256', $snapshotfile));
        }
        $controls[] = ['id' => 'M12-ISO-NEG-002', 'passed' => $corruptrejected,
            'detail' => 'Snapshot corrupto rechazado y conservado sin cambiar el estado sintético.'];
        unlink($snapshotfile);

        m12_sandbox_apply($statefile, $snapshotfile);
        $firstsnapshothash = hash_file('sha256', $snapshotfile);
        m12_sandbox_apply($statefile, $snapshotfile);
        $snapshotpreserved = hash_equals($firstsnapshothash, hash_file('sha256', $snapshotfile));
        $controls[] = ['id' => 'M12-ISO-APPLY-001', 'passed' => $snapshotpreserved,
            'detail' => 'Apply repetido no sobrescribe el snapshot sintético auténtico.'];

        $m12statehash = hash_file('sha256', $statefile);
        try {
            m12_sandbox_revert($statefile, $snapshotfile, true);
            $failurepreserved = false;
        } catch (RuntimeException $e) {
            $failurepreserved = hash_equals($m12statehash, hash_file('sha256', $statefile))
                && hash_equals($firstsnapshothash, hash_file('sha256', $snapshotfile));
        }
        $controls[] = ['id' => 'M12-ISO-RECOVERY-001', 'passed' => $failurepreserved,
            'detail' => 'Un fallo previo al commit conserva estado y snapshot para reintento.'];

        m12_sandbox_revert($statefile, $snapshotfile);
        $restored = m12_states_equal(m12_sandbox_read_state($statefile), $baseline) && !file_exists($snapshotfile);
        m12_sandbox_apply($statefile, $snapshotfile);
        $applied = m12_sandbox_read_state($statefile);
        $cyclepassed = $restored
            && $applied['brandcolor'] === M12_BRAND_COLOR
            && $applied['site_fullname'] === M12_SITE_FULLNAME
            && m12_snapshot_status($snapshotfile)['valid'];
        $controls[] = ['id' => 'M12-ISO-CYCLE-001', 'passed' => $cyclepassed,
            'detail' => 'Apply/Revert/Apply funciona dentro del sandbox desechable.'];

        $passed = count(array_filter($controls, static fn(array $control): bool => !$control['passed'])) === 0;
        echo json_encode([
            'status' => $passed ? 'PASS' : 'FAIL',
            'isolated' => true,
            'live_moodle_configuration_touched' => false,
            'controls' => $controls,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        if (!$passed) {
            throw new RuntimeException('Fallaron controles del sandbox desechable de snapshots.');
        }
    } catch (Throwable $t) {
        $failure = $t;
    } finally {
        foreach ([$snapshotfile, $statefile . '.tmp', $statefile] as $ownedfile) {
            if (file_exists($ownedfile)) {
                unlink($ownedfile);
            }
        }
        if (is_dir($testroot)) {
            rmdir($testroot);
        }
    }
    if ($failure !== null) {
        m12_fail('Falló el sandbox desechable de snapshots: ' . $failure->getMessage());
    }
}

/**
 * Open staging course & category temporarily for visual inspections.
 */
function m12_open(): void {
    m12_admin();
    $course = m12_course();
    $category = \core_course_category::get((int) $course->category);

    if (!$category->visible) {
        $category->show();
    }
    if (!(int) $course->visible) {
        course_change_visibility((int) $course->id, true);
    }
    rebuild_course_cache((int) $course->id, true);

    echo json_encode([
        'status' => 'open',
        'course_id' => (int) $course->id,
        'course_visible' => true,
        'category_visible' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Lockdown staging course & category (hidden state guaranteed).
 */
function m12_lockdown(): void {
    m12_admin();
    $course = m12_course();
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
 * Output current status.
 */
function m12_status(): void {
    global $DB;
    m12_admin();
    $course = m12_course();
    $category = \core_course_category::get((int) $course->category);
    $state = m12_current_state();
    $statejson = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $sessiontable = new xmldb_table('local_asstaff_session');
    $sessiontableexists = $DB->get_manager()->table_exists($sessiontable);
    $restrictedsections = $DB->count_records_select(
        'course_sections',
        'course = :courseid AND section >= :firstsection AND section <= :lastsection '
            . "AND availability IS NOT NULL AND availability <> ''",
        ['courseid' => $course->id, 'firstsection' => 2, 'lastsection' => 5]
    );

    echo json_encode([
        'theme' => $state['theme'],
        'brandcolor' => $state['brandcolor'],
        'scsspre_length' => strlen($state['scsspre']),
        'scsspre_sha256' => hash('sha256', $state['scsspre']),
        'scss_length' => strlen($state['scss']),
        'scss_sha256' => hash('sha256', $state['scss']),
        'site_fullname' => $state['site_fullname'],
        'site_shortname' => $state['site_shortname'],
        'governed_state_sha256' => hash('sha256', (string) $statejson),
        'course_id' => (int) $course->id,
        'course_visible' => (bool) $course->visible,
        'category_visible' => (bool) $category->visible,
        'snapshot' => m12_snapshot_status(),
        'mission10' => [
            'restricted_following_units' => (int) $restrictedsections,
        ],
        'mission11' => [
            'plugin_version' => (int) (get_config('local_asodeltolstaff', 'version') ?: 0),
            'session_table_exists' => $sessiontableexists,
            'synthetic_session_count' => $sessiontableexists ? $DB->count_records('local_asstaff_session') : 0,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$options = getopt('', ['phase:']);
$phase = $options['phase'] ?? 'status';

switch ($phase) {
    case 'apply':
        m12_apply();
        break;
    case 'revert':
        m12_revert();
        break;
    case 'snapshot-selftest':
        m12_snapshot_selftest();
        break;
    case 'open':
        m12_open();
        break;
    case 'lockdown':
        m12_lockdown();
        break;
    case 'status':
        m12_status();
        break;
    default:
        m12_fail("Fase desconocida: {$phase}");
}
