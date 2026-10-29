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
  assert.match(callbacks, /function local_asodeltolstaff_before_standard_html_head/);
  assert.match(callbacks, /before_standard_html_head[\s\S]*profile_privacy::enforce_own_profile/);
  // user/profile.php skips require_login() when public profiles are enabled,
  // so the route-scoped enforcement also has to run before page output.
  assert.match(hooks, /before_standard_head_html_generation/);
  assert.match(hooks, /enforce_profile_privacy/);
  assert.match(read('plugins/local/asodeltolstaff/classes/hook_callbacks.php'),
    /enforce_profile_privacy[\s\S]*profile_privacy::enforce_own_profile/);
  assert.match(privacy, /control_view_profile/);
  assert.match(privacy, /enrol_get_users_courses/);
  assert.match(privacy, /is_enrolled/);
  assert.match(privacy, /TRAINER_ROLES/);
  assert.match(privacy, /AUDITOR_ROLES/);
  assert.match(privacy, /VIEWPROFILE_PREVENT/);
  assert.match(privacy, /enforce_own_profile/);
  assert.match(privacy, /groups_get_all_groups/);
  assert.match(privacy, /array_intersect_key/);
  assert.match(privacy, /user\/view\.php/);
  assert.match(privacy, /user\/profile\.php/);
  assert.match(read('plugins/local/asodeltolstaff/lib.php'), /control_view_profile/);
  assert.match(privacy, /VIEWPROFILE_PREVENT/);
  assert.match(privacy, /VIEWPROFILE_FORCE_ALLOW/);
  assert.match(privacy, /optional_param\('course'/);
  assert.match(privacy, /requestedcourseid/);
  assert.match(privacy, /target->id/);
  assert.doesNotMatch(privacy, /ASODELTOL-AF8/);
  assert.doesNotMatch(privacy, /['"]id['"]\s*=>\s*4/);
  assert.match(privacy, /local\/asodeltolstaff\/profile\.php/);
});

test('scoped endpoint emits own minimal fields and course-authorised hidden fields', () => {
  assert.match(endpoint, /numero_documento/);
  assert.match(endpoint, /fullname\(\$target\)/);
  assert.match(endpoint, /\$USER->email/);
  assert.match(endpoint, /moodle\/user:viewhiddendetails/);
  assert.match(endpoint, /context_course::instance/);
  assert.match(endpoint, /profile_privacy::control_view_profile/);
  assert.match(endpoint, /get_string\('city'\)/);
  assert.doesNotMatch(endpoint, /address|fecha_nacimiento|empresa_institucion|direccion_completa/);
  assert.doesNotMatch(upgrade, /role_assign\(/);
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
