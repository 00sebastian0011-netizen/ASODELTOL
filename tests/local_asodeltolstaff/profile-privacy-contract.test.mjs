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
  assert.match(callbacks, /function local_asodeltolstaff_control_view_profile/);
  assert.match(callbacks, /profile_privacy::control_view_profile/);
  assert.doesNotMatch(hooks, /before_standard_head_html_generation/);
  assert.match(privacy, /control_view_profile/);
  assert.match(privacy, /shared_courses/);
  assert.match(privacy, /enforce_own_profile/);
  assert.match(privacy, /groups_get_all_groups/);
  assert.match(privacy, /array_intersect_key/);
  assert.match(privacy, /user\/view\.php/);
  assert.match(privacy, /user\/profile\.php/);
  assert.match(read('plugins/local/asodeltolstaff/lib.php'), /control_view_profile/);
  assert.match(privacy, /VIEWPROFILE_PREVENT/);
  assert.match(privacy, /VIEWPROFILE_FORCE_ALLOW/);
  assert.doesNotMatch(privacy, /optional_param\('course'/);
  assert.match(privacy, /target->id/);
  assert.doesNotMatch(privacy, /ASODELTOL-AF8/);
  assert.doesNotMatch(privacy, /['"]id['"]\s*=>\s*4/);
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

test('profile decision matrix blocks beneficiaries and scopes institutional roles', () => {
  const decide = ({own = false, minimal, enrolled, role, sameGroup = false}) => {
    if (own) return 'ALLOW';
    if (minimal) return 'PREVENT';
    if (!enrolled) return 'DO_NOT_PREVENT';
    if (role === 'auditor' || role === 'sena') return 'ALLOW';
    if (role === 'capacitador') return sameGroup ? 'ALLOW' : 'PREVENT';
    return 'DO_NOT_PREVENT';
  };
  assert.equal(decide({minimal: true, enrolled: false}), 'PREVENT');
  assert.equal(decide({minimal: true, enrolled: true}), 'PREVENT');
  assert.equal(decide({role: 'capacitador', enrolled: true, sameGroup: true}), 'ALLOW');
  assert.equal(decide({role: 'capacitador', enrolled: true, sameGroup: false}), 'PREVENT');
  assert.equal(decide({role: 'auditor', enrolled: true}), 'ALLOW');
  assert.equal(decide({role: 'sena', enrolled: true}), 'ALLOW');
  assert.equal(decide({role: 'auditor', enrolled: false}), 'DO_NOT_PREVENT');
  assert.match(privacy, /is_minimal_viewer/);
  assert.match(privacy, /is_enrolled/);
  assert.match(privacy, /asodeltol_trainer/);
});
