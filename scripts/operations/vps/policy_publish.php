<?php
// Fixed production publisher: uses Moodle's tool_policy API only.
define('CLI_SCRIPT', true);
$site = getenv('ASODELTOL_SITE_ROOT');
if (!$site || !is_file($site . '/config.php')) { throw new RuntimeException('invalid_site_root'); }
require($site . '/config.php');
require_once($CFG->dirroot . '/admin/tool/policy/classes/api.php');
require_once($CFG->dirroot . '/admin/tool/policy/classes/policy_version.php');
$source = getenv('ASODELTOL_POLICY_SOURCE');
if (!$source || !is_file($source)) { throw new RuntimeException('policy_source_missing'); }
$markdown = file_get_contents($source);
if ($markdown === false || !str_contains($markdown, 'AUTORIZACIÓN PARA EL TRATAMIENTO DE DATOS PERSONALES')) { throw new RuntimeException('policy_source_invalid'); }
$content = trim(preg_replace('/^# .*?\R+/', '', $markdown));
$existing = $DB->get_records('tool_policy_versions', ['type' => \tool_policy\policy_version::TYPE_PRIVACY], 'id DESC');
$policyid = 0;
foreach ($existing as $item) { if (stripos($item->name, 'Tratamiento de Datos Personales de ASODELTOL') !== false) { $policyid = (int)$item->policyid; break; } }
$form = (object)['name' => 'Política de Tratamiento de Datos Personales de ASODELTOL', 'type' => \tool_policy\policy_version::TYPE_PRIVACY, 'audience' => \tool_policy\policy_version::AUDIENCE_LOGGEDIN, 'agreementstyle' => \tool_policy\policy_version::AGREEMENTSTYLE_CONSENTPAGE, 'optional' => \tool_policy\policy_version::AGREEMENT_COMPULSORY, 'revision' => '2026-10-27 — versión institucional aprobada', 'summary_editor' => ['text' => '<p>Autorización institucional para el tratamiento de datos personales de los participantes del Aula Virtual ASODELTOL.</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content_editor' => ['text' => $content, 'format' => FORMAT_MARKDOWN, 'itemid' => 0]];
$transaction = $DB->start_delegated_transaction();
if ($policyid) { $form->policyid = $policyid; $version = \tool_policy\api::form_policydoc_update_new($form); }
else { $version = \tool_policy\api::form_policydoc_add($form); $policyid = (int)$version->get('policyid'); }
\tool_policy\api::make_current($version->get('id'));
set_config('sitepolicyhandler', 'tool_policy');
$transaction->allow_commit();
purge_all_caches();
echo json_encode(['status' => 'published', 'policyid' => $policyid, 'versionid' => (int)$version->get('id')], JSON_UNESCAPED_UNICODE) . PHP_EOL;
