import assert from 'node:assert/strict';
import {test} from 'node:test';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const read = (path) => readFileSync(resolve(process.cwd(), path), 'utf8');
const access = read('plugins/local/asodeltolstaff/db/access.php');
const hooks = read('plugins/local/asodeltolstaff/db/hooks.php');
const callbacks = read('plugins/local/asodeltolstaff/lib.php');
const privacy = read('plugins/local/asodeltolstaff/classes/profile_privacy.php');
const endpoint = read('plugins/local/asodeltolstaff/profile.php');
const upgrade = read('plugins/local/asodeltolstaff/db/upgrade.php');

test('Beneficiario profile boundary is server-side and own-profile only', () => {
  assert.match(access, /local\/asodeltolstaff:viewminimalprofile/);
  assert.match(access, /'student'\s*=>\s*CAP_ALLOW/);
  assert.match(callbacks, /function local_asodeltolstaff_after_require_login/);
  assert.match(callbacks, /profile_privacy::enforce_profile_access/);
  assert.doesNotMatch(hooks, /before_standard_head_html_generation/);
  assert.match(privacy, /function enforce_profile_access/);
  assert.match(privacy, /is_minimal_profile_user/);
  assert.match(privacy, /enforce_trainer_group_scope/);
  assert.match(privacy, /groups_get_all_groups/);
  assert.match(privacy, /array_intersect_key/);
  assert.match(privacy, /moodle\/site:accessallgroups/);
  assert.match(privacy, /user\/view\.php/);
  assert.match(privacy, /user\/profile\.php/);
  assert.doesNotMatch(privacy, /optional_param\('course'/);
  assert.match(privacy, /targetid !== \(int\) \$USER->id/);
  assert.match(privacy, /nopermissions/);
  assert.match(privacy, /local\/asodeltolstaff\/profile\.php/);
});

test('minimal endpoint emits only approved fields', () => {
  assert.match(endpoint, /numero_documento/);
  assert.match(endpoint, /fullname\(\$USER\)/);
  assert.match(endpoint, /\$USER->email/);
  assert.doesNotMatch(endpoint, /city|address|fecha_nacimiento|empresa_institucion|direccion_completa/);
});

test('SENA is provisioned without assignments by cloning Interventoria role definition', () => {
  assert.match(upgrade, /shortname'\s*=>\s*'asodeltol_auditor'/);
  assert.match(upgrade, /shortname'\s*=>\s*'sena'/);
  assert.match(upgrade, /create_role\('SENA', 'sena'/);
  assert.match(upgrade, /role_capabilities/);
  assert.doesNotMatch(upgrade, /role_assignments|role_assign\(/);
  assert.match(upgrade, /unassign_capability/);
  assert.doesNotMatch(upgrade, /assign_capability\([^;]*CAP_PROHIBIT/);
});
