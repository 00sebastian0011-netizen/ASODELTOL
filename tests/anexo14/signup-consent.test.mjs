import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const lib = fs.readFileSync('plugins/local/asodeltolstaff/lib.php', 'utf8');
const signup = fs.readFileSync('login/signup_form.php', 'utf8');
const signupPage = fs.readFileSync('login/signup.php', 'utf8');

test('uses Moodle signup extension and post-signup hooks', () => {
  assert.match(signup, /core_login_extend_signup_form\(\$mform\)/);
  assert.match(signup, /core_login_validate_extend_signup_form\(\$data\)/);
  assert.match(signupPage, /core_login_post_signup_requests\(\$user\)/);
  assert.match(lib, /local_asodeltolstaff_extend_signup_form/);
  assert.match(lib, /local_asodeltolstaff_validate_extend_signup_form/);
  assert.match(lib, /local_asodeltolstaff_post_signup_requests/);
});

test('checkbox is unchecked, server validated and native policy URL is used', () => {
  assert.match(lib, /addElement\('checkbox', 'asodeltol_policy_consent'/);
  assert.match(lib, /setDefault\('asodeltol_policy_consent', 0\)/);
  assert.match(lib, /empty\(\$data\['asodeltol_policy_consent'\]\)/);
  assert.match(lib, /\$manager->get_embed_url\(false\)/);
  assert.match(lib, /\$data->policyagreed = 1/);
  assert.doesNotMatch(lib, /aulaasodeltol\.online/);
});

test('no policy URL means no custom checkbox or invented document link', () => {
  assert.match(lib, /\$CFG->sitepolicyhandler.*tool_policy/);
  assert.match(lib, /if \(\!\$url\)/);
  assert.match(lib, /return null/);
});
