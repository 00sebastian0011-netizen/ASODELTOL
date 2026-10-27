import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const lib = fs.readFileSync('plugins/local/asodeltolstaff/lib.php', 'utf8');

test('uses native signup hooks without modifying Moodle core', () => {
  assert.match(lib, /function local_asodeltolstaff_extend_signup_form\(\$mform\)/);
  assert.match(lib, /function local_asodeltolstaff_validate_extend_signup_form\(array \$data\)/);
  assert.match(lib, /function local_asodeltolstaff_post_signup_requests\(stdClass \$data\)/);
});

test('checkbox is Spanish, unchecked and independent of native policyagreed', () => {
  assert.match(lib, /addElement\(\s*'checkbox',\s*'asodeltol_policy_consent'/);
  assert.match(lib, /setDefault\('asodeltol_policy_consent', 0\)/);
  assert.match(lib, /He leído y acepto la Política de Tratamiento de Datos Personales de ASODELTOL/);
  assert.doesNotMatch(lib, /addElement\('checkbox', 'policyagreed'/);
  assert.doesNotMatch(lib, /removeElement\('policyagreed'\)/);
  assert.doesNotMatch(lib, /I accept the personal-data processing policy/);
});

test('link opens a published native policy document, not signup', () => {
  assert.match(lib, /api::list_current_versions\(/);
  assert.match(lib, /AGREEMENT_COMPULSORY/);
  assert.match(lib, /TYPE_PRIVACY/);
  assert.match(lib, /new moodle_url\('\/admin\/tool\/policy\/view\.php', \['versionid'/);
  assert.match(lib, /addElement\('static', 'asodeltol_policy_link'/);
  assert.match(lib, /html_writer::link\(/);
  assert.match(lib, /noopener noreferrer/);
  assert.doesNotMatch(lib, /get_embed_url\(/);
  assert.doesNotMatch(lib, /https?:\/\/aulaasodeltol\.online/);
});

test('server rejects missing consent and native tool_policy records acceptance', () => {
  assert.match(lib, /empty\(\$data\['asodeltol_policy_consent'\]\)/);
  assert.match(lib, /return \['asodeltol_policy_consent' => /);
  assert.match(lib, /throw new \\moodle_exception\(/);
  assert.match(lib, /\$data->policyagreed = 1/);
  assert.match(lib, /\$CFG->sitepolicyhandler.*tool_policy/);
});
