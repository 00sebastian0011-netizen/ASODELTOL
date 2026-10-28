import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import {classifyFiles} from '../../scripts/deploy/classify-changes.mjs';

const service = fs.readFileSync('plugins/local/asodeltolstaff/classes/student_credentials.php', 'utf8');
const controller = fs.readFileSync('plugins/local/asodeltolstaff/credentials.php', 'utf8');
const settings = fs.readFileSync('plugins/local/asodeltolstaff/settings.php', 'utf8');
const spanish = fs.readFileSync('plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php', 'utf8');
const spec = fs.readFileSync('docs/REQUISITOS_PERFILES_CREDENCIALES.md', 'utf8');

test('REQ-USR-01..05: enforces municipality locking, non-deducible passwords and password-free ledger', () => {
  assert.match(spec, /REQ-USR-01/);
  assert.match(spec, /REQ-USR-02/);
  assert.match(spec, /REQ-USR-03/);
  assert.match(spec, /REQ-USR-04/);
  assert.match(spec, /REQ-USR-05/);

  // REQ-USR-01: city hidden and locked for students, updated without touching enrolments/grades.
  assert.match(service, /set_config\('hiddenuserfields'/);
  assert.match(service, /set_config\('field_lock_city', 'locked', 'auth_manual'\)/);
  assert.match(service, /user_update_user\(\(object\) \[\s*'id' => \(int\) \$user->id,\s*'city' => \$newcity,\s*\], false, false\)/);

  // REQ-USR-02 & REQ-USR-03: CSPRNG password generation, rejection of sena+firstname+5678$, and CAP_PREVENT on student password change.
  assert.match(service, /random_int\(/);
  assert.match(service, /\^sena\.\*5678\\\$\$/);
  assert.match(service, /assign_capability\('moodle\/user:changeownpassword', CAP_PREVENT/);
  assert.match(service, /update_internal_user_password\(\$user, \$password\)/);

  // REQ-USR-04: ledger and exports never store or export passwords.
  assert.match(service, /cred_ledger_/);
  assert.doesNotMatch(service, /'password'\s*=>\s*\$entry/);
  assert.match(controller, /require_sesskey\(\)/);
  assert.match(settings, /asodeltolcredentials/);
  assert.ok(spanish.includes('Perfiles y credenciales de estudiantes'));
});

test('REQ-USR-01..05: plugin changes classify as standard automatic deployment without schema drift', () => {
  const classification = classifyFiles([
    'plugins/local/asodeltolstaff/classes/student_credentials.php',
    'plugins/local/asodeltolstaff/credentials.php',
    'plugins/local/asodeltolstaff/index.php',
    'plugins/local/asodeltolstaff/settings.php',
    'plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php',
    'plugins/local/asodeltolstaff/lang/en/local_asodeltolstaff.php',
  ]);
  assert.equal(classification.risk, 'standard');
  assert.equal(classification.deployable, true);
  assert.equal(classification.manualRequired, false);
});
