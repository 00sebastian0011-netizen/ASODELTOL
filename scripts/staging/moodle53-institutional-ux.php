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
 * Validate and read existing snapshot file.
 *
 * @param string|null $path Optional path to snapshot file.
 * @return array|null Validated snapshot data array, or null if file does not exist.
 * @throws RuntimeException If file exists but is invalid, corrupted, or incomplete.
 */
function m12_read_snapshot(?string $path = null): ?array {
    $file = $path ?: m12_snapshot_file();
    if (!file_exists($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        throw new RuntimeException("El archivo de snapshot existe pero está vacío o no es legible.");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("El archivo de snapshot contiene JSON inválido o corrupto.");
    }

    $required_keys = ['captured_at', 'theme', 'brandcolor', 'scsspre', 'scss', 'site_fullname', 'site_shortname'];
    foreach ($required_keys as $key) {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw new RuntimeException("El snapshot está incompleto: falta el campo obligatorio '{$key}'.");
        }
    }

    if (empty($data['theme']) || empty($data['site_fullname']) || empty($data['site_shortname'])) {
        throw new RuntimeException("El snapshot contiene campos obligatorios vacíos (theme, site_fullname o site_shortname).");
    }

    return $data;
}

/**
 * Apply institutional UX to theme_boost.
 */
function m12_apply(): void {
    global $DB, $SITE;
    m12_admin();

    $snapshot_file = m12_snapshot_file();

    // 1. Validar si ya existe un snapshot previo.
    $existing_snapshot = null;
    try {
        $existing_snapshot = m12_read_snapshot($snapshot_file);
    } catch (RuntimeException $e) {
        m12_fail("No se puede aplicar M12 porque el snapshot existente está corrupto: " . $e->getMessage());
    }

    // 2. Si no existe snapshot, verificar si el estado actual es limpio o ya es M12.
    if ($existing_snapshot === null) {
        $current_brand = (string) (get_config('theme_boost', 'brandcolor') ?: '');
        $current_scss = (string) (get_config('theme_boost', 'scss') ?: '');
        $current_fullname = (string) $SITE->fullname;

        $is_already_m12 = ($current_brand === M12_BRAND_COLOR ||
                           str_contains($current_scss, 'AULA VIRTUAL ASODELTOL') ||
                           $current_fullname === M12_SITE_FULLNAME);

        if ($is_already_m12) {
            // M12 ya estaba aplicada y no existe snapshot pre-M12 previo.
            // Para no falsear la línea base capturando la personalización como si fuera el original ni inventar datos,
            // abortamos exigiendo inicializar/recuperar el snapshot pre-M12 de forma deliberada.
            m12_fail("Operación rechazada: la personalización M12 ya se encuentra aplicada en el sitio pero no existe un snapshot pre-M12 válido. No se puede capturar el estado modificado como línea base ni inventar un respaldo. Inicialice o restaure el snapshot preexistente antes de volver a aplicar.");
        }

        // Estado limpio original: capturar fielmente el estado actual antes de aplicar M12.
        $snapshot = [
            'captured_at' => gmdate('c'),
            'theme' => (string) (get_config('core', 'theme') ?: 'boost'),
            'brandcolor' => $current_brand,
            'scsspre' => (string) (get_config('theme_boost', 'scsspre') ?: ''),
            'scss' => $current_scss,
            'site_fullname' => $current_fullname,
            'site_shortname' => (string) $SITE->shortname,
            'provenance' => 'pre_m12_live_capture',
        ];

        $snapshot_dir = dirname($snapshot_file);
        if (!is_dir($snapshot_dir) && !@mkdir($snapshot_dir, 0770, true)) {
            m12_fail("No se pudo crear el directorio para el snapshot: {$snapshot_dir}");
        }

        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bytes = @file_put_contents($snapshot_file, $json);
        if ($bytes === false || $bytes === 0 || !file_exists($snapshot_file) || filesize($snapshot_file) === 0) {
            m12_fail("Fallo crítico de E/S: no se pudo escribir o verificar el snapshot de respaldo en {$snapshot_file}.");
        }
    }

    // 3. Aplicar personalización
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
        $SITE->fullname = M12_SITE_FULLNAME;
        $SITE->shortname = M12_SITE_SHORTNAME;
    }

    theme_reset_all_caches();

    echo json_encode([
        'status' => 'applied',
        'theme' => 'boost',
        'brandcolor' => M12_BRAND_COLOR,
        'site_fullname' => M12_SITE_FULLNAME,
        'snapshot_file' => $snapshot_file,
        'snapshot_verified' => file_exists($snapshot_file) && filesize($snapshot_file) > 0,
        'scsspre_bytes' => strlen(m12_scsspre()),
        'scss_bytes' => strlen(m12_scss()),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Revert theme_boost to default unbranded state using snapshot if available.
 */
function m12_revert(): void {
    global $DB, $SITE;
    m12_admin();

    $snapshot_file = m12_snapshot_file();

    // 1. Validar estrictamente la presencia y contenido del snapshot SIN modificar nada en Moodle.
    if (!file_exists($snapshot_file)) {
        m12_fail("Operación rechazada: no existe el archivo de snapshot ({$snapshot_file}). No se alteró la configuración actual.");
    }

    $snapshot = null;
    try {
        $snapshot = m12_read_snapshot($snapshot_file);
    } catch (RuntimeException $e) {
        m12_fail("Operación rechazada: el snapshot es inválido o está corrupto (" . $e->getMessage() . "). No se alteró la configuración actual.");
    }

    if ($snapshot === null) {
        m12_fail("Operación rechazada: no se pudo leer el snapshot. No se alteró la configuración actual.");
    }

    // 2. Ejecutar la restauración de configuración paso a paso
    $brandcolor = $snapshot['brandcolor'];
    $scsspre = $snapshot['scsspre'];
    $scss = $snapshot['scss'];
    $theme = $snapshot['theme'];
    $site_fullname = $snapshot['site_fullname'];
    $site_shortname = $snapshot['site_shortname'];

    try {
        set_config('brandcolor', $brandcolor, 'theme_boost');
        set_config('scsspre', $scsspre, 'theme_boost');
        set_config('scss', $scss, 'theme_boost');
        set_config('theme', $theme, 'core');

        if ($SITE->fullname !== $site_fullname || $SITE->shortname !== $site_shortname) {
            $DB->update_record('course', (object) [
                'id' => $SITE->id,
                'fullname' => $site_fullname,
                'shortname' => $site_shortname,
            ]);
            $SITE->fullname = $site_fullname;
            $SITE->shortname = $site_shortname;
        }

        theme_reset_all_caches();
    } catch (Throwable $t) {
        m12_fail("Fallo durante la restauración de configuración en Moodle: " . $t->getMessage() . ". El snapshot se conserva en: {$snapshot_file}");
    }

    // 3. SOLO después de confirmar que TODAS las operaciones de restauración fueron exitosas, se elimina el snapshot.
    if (!@unlink($snapshot_file)) {
        fwrite(STDERR, "ADVERTENCIA: La configuración fue revertida con éxito pero no se pudo eliminar el archivo de snapshot.\n");
    }

    echo json_encode([
        'status' => 'reverted',
        'theme' => $theme,
        'brandcolor' => $brandcolor,
        'scsspre_length' => strlen($scsspre),
        'scss_length' => strlen($scss),
        'site_fullname' => $site_fullname,
        'site_shortname' => $site_shortname,
        'snapshot_deleted' => !file_exists($snapshot_file),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

/**
 * Seed canonical pre-M12 baseline snapshot if it does not already exist.
 * Enables clean baseline tracking for existing staging installations where M12 was applied
 * before snapshots were introduced, without inventing data or capturing customized state as baseline.
 */
function m12_seed_snapshot(): void {
    m12_admin();
    $snapshot_file = m12_snapshot_file();

    if (file_exists($snapshot_file)) {
        echo json_encode([
            'status' => 'exists',
            'message' => 'El snapshot ya existe; no se sobreescribe.',
            'snapshot_file' => $snapshot_file,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return;
    }

    $baseline = [
        'captured_at' => gmdate('c'),
        'theme' => 'boost',
        'brandcolor' => '',
        'scsspre' => '',
        'scss' => '',
        'site_fullname' => 'ASODELTOL Moodle 5.3 Staging',
        'site_shortname' => 'ASODELTOL-STG',
        'provenance' => 'staging_canonical_baseline',
    ];

    $snapshot_dir = dirname($snapshot_file);
    if (!is_dir($snapshot_dir) && !@mkdir($snapshot_dir, 0770, true)) {
        m12_fail("No se pudo crear el directorio para el snapshot: {$snapshot_dir}");
    }

    $json = json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $bytes = @file_put_contents($snapshot_file, $json);
    if ($bytes === false || $bytes === 0 || !file_exists($snapshot_file) || filesize($snapshot_file) === 0) {
        m12_fail("No se pudo escribir el snapshot canónico en {$snapshot_file}.");
    }

    echo json_encode([
        'status' => 'seeded',
        'snapshot_file' => $snapshot_file,
        'bytes' => $bytes,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
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
    global $CFG, $SITE;
    m12_admin();
    $course = m12_course();
    $category = \core_course_category::get((int) $course->category);
    $boost = get_config('theme_boost');
    $snapshot_file = m12_snapshot_file();

    echo json_encode([
        'theme' => get_config('core', 'theme'),
        'brandcolor' => $boost->brandcolor ?? '',
        'scsspre_length' => strlen($boost->scsspre ?? ''),
        'scss_length' => strlen($boost->scss ?? ''),
        'site_fullname' => $SITE->fullname,
        'site_shortname' => $SITE->shortname,
        'course_id' => (int) $course->id,
        'course_visible' => (bool) $course->visible,
        'category_visible' => (bool) $category->visible,
        'snapshot_exists' => file_exists($snapshot_file),
        'snapshot_file' => $snapshot_file,
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
    case 'seed-snapshot':
        m12_seed_snapshot();
        break;
    case 'open':
        m12_open();
        break;
    case 'lockdown':
        m12_lockdown();
        break;
    case 'status':
    default:
        m12_status();
        break;
}
