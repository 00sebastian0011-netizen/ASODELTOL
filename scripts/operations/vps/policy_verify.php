<?php
define('CLI_SCRIPT', true);
$site = getenv('ASODELTOL_SITE_ROOT');
if (!$site || !is_file($site . '/config.php')) { throw new RuntimeException('invalid_site_root'); }
require($site . '/config.php');
require_once($CFG->dirroot . '/admin/tool/policy/classes/policy_version.php');
$policy = $DB->get_record_sql("SELECT p.id, p.currentversionid, v.optional, v.audience FROM {tool_policy} p JOIN {tool_policy_versions} v ON v.id = p.currentversionid WHERE v.name LIKE ?", ['%Tratamiento de Datos Personales de ASODELTOL%']);
if (!$policy || !$policy->currentversionid || (int)$policy->optional !== \tool_policy\policy_version::AGREEMENT_COMPULSORY || (int)$policy->audience !== \tool_policy\policy_version::AUDIENCE_LOGGEDIN) { throw new RuntimeException('required_active_policy_not_found'); }
echo json_encode(['status' => 'verified', 'policyid' => (int)$policy->id, 'versionid' => (int)$policy->currentversionid, 'required' => true], JSON_UNESCAPED_UNICODE) . PHP_EOL;
