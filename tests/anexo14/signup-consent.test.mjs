import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const lib = fs.readFileSync('plugins/local/asodeltolstaff/lib.php', 'utf8');
const upgrade = fs.readFileSync('plugins/local/asodeltolstaff/db/upgrade.php', 'utf8');
const version = fs.readFileSync('plugins/local/asodeltolstaff/version.php', 'utf8');

test('uses native signup hooks without modifying Moodle core', () => {
  assert.match(lib, /function local_asodeltolstaff_extend_signup_form\(\$mform\)/);
  assert.match(lib, /function local_asodeltolstaff_validate_extend_signup_form\(array \$data\)/);
  assert.match(lib, /function local_asodeltolstaff_post_signup_requests\(stdClass \$data\)/);
});

test('manual provisioning leaves consent exclusively to native tool_policy', () => {
  assert.doesNotMatch(lib, /addElement\(\s*'advcheckbox',\s*'asodeltol_policy_consent'/);
  assert.doesNotMatch(lib, /\$data->policyagreed = 1/);
  assert.match(lib, /Native tool_policy handles the only/);
});

test('policy discovery resolves the published native document', () => {
  assert.match(lib, /api::list_current_versions\(/);
  assert.match(lib, /AGREEMENT_COMPULSORY/);
  assert.match(lib, /TYPE_PRIVACY/);
  assert.match(lib, /new moodle_url\('\/admin\/tool\/policy\/view\.php', \['versionid'/);
  assert.doesNotMatch(lib, /get_embed_url\(/);
  assert.doesNotMatch(lib, /https?:\/\/aulaasodeltol\.online/);
});

test('native tool_policy remains the configured consent authority', () => {
  assert.match(lib, /\$CFG->sitepolicyhandler.*tool_policy/);
  assert.match(lib, /function local_asodeltolstaff_validate_extend_signup_form\(array \$data\): array \{\s*return \[\];/);
});

test('signup callbacks do not create a second consent path', () => {
  assert.doesNotMatch(lib, /asodeltol_policy_unavailable/);
  assert.match(lib, /function local_asodeltolstaff_post_signup_requests\(stdClass \$data\): void \{[\s\S]*?No-op:/);
});

test('plugin upgrade disables self-registration for manual provisioning', () => {
  assert.match(upgrade, /\$oldversion < 2026102700/);
  assert.match(upgrade, /set_config\('registerauth', ''\)/);
  assert.match(upgrade, /upgrade_plugin_savepoint\(true, 2026102700/);
  assert.match(version, /\$plugin->version = 2026102700/);
  assert.match(version, /\$plugin->release = '0\.6\.0'/);
});
