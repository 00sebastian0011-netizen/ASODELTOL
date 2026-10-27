import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const lib = fs.readFileSync('plugins/local/asodeltolstaff/lib.php', 'utf8');

test('registers Moodle signup callbacks without changes to Moodle core', () => {
  assert.match(lib, /function local_asodeltolstaff_extend_signup_form\(\$mform\)/);
  assert.match(lib, /function local_asodeltolstaff_validate_extend_signup_form\(array \$data\)/);
  assert.match(lib, /function local_asodeltolstaff_post_signup_requests\(stdClass \$data\)/);
});

test('shows an unchecked checkbox linked to native policy', () => {
  assert.match(lib, /elementExists\('policyagreed'\)/);
  assert.match(lib, /removeElement\('policyagreed'\)/);
  assert.match(lib, /addElement\('checkbox', 'policyagreed'/);
  assert.match(lib, /setDefault\('policyagreed', 0\)/);
  assert.match(lib, /get_embed_url\(false\)/);
  assert.match(lib, /He leído y acepto la Política de Tratamiento de Datos Personales de ASODELTOL/);
  assert.doesNotMatch(lib, /asodeltol_policy_consent/);
  assert.doesNotMatch(lib, /https?:\/\/aulaasodeltol\.online/);
});

test('server rejects missing consent and native event records policyagreed', () => {
  assert.match(lib, /empty\(\$data\['policyagreed'\]\)/);
  assert.match(lib, /return \['policyagreed' => /);
  assert.match(lib, /throw new \\moodle_exception\(/);
  assert.match(lib, /\$data->policyagreed = 1/);
  assert.match(lib, /\$CFG->sitepolicyhandler.*tool_policy/);
  assert.match(lib, /if \(!\$url\)/);
});
