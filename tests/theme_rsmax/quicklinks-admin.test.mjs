import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const load = path => readFileSync(path, 'utf8');
const legacy = load('plugins/theme/rsmax/classes/local/course_utilities.php');
const groups = load('plugins/theme/rsmax/classes/local/course_quicklink_groups.php');
const controller = load('plugins/theme/rsmax/quicklinks.php');
const form = load('plugins/theme/rsmax/classes/form/course_quicklinks_form.php');
const template = load('plugins/theme/rsmax/templates/course_utilities.mustache');

test('editor remains limited to authorized course managers, with CSRF protection', () => {
  assert.match(controller, /require_course_login\(\$course\)/);
  assert.match(controller, /require_capability\('moodle\/course:manageactivities', \$context\)/);
  assert.match(controller, /require_sesskey\(\)/);
  assert.match(groups, /require_capability\('moodle\/course:manageactivities'/);
  assert.match(template, /\{\{#manageurl\}\}/);
  assert.match(legacy, /self::can_manage\(/);
});

test('multi-select groups store only current-course section/module identifiers and preserve order', () => {
  assert.match(groups, /'coursequickgroups_' \. \$courseid/);
  assert.match(groups, /get_fast_modinfo\(\$course\)/);
  assert.match(groups, /get_section_info_all\(\)/);
  assert.match(groups, /modinfo->sections\[/);
  assert.match(groups, /'section:' \. \$section->id/);
  assert.match(groups, /'cm:' \. \$cm->id/);
  assert.match(groups, /function validate\(/);
  assert.match(groups, /isset\(\$allowed\[\$value\]\)/);
  assert.match(groups, /array_keys\(\$unique\)/);
  assert.match(groups, /json_encode\(\['version' => 1\] \+ \$choices, JSON_THROW_ON_ERROR\)/);
  assert.match(groups, /empty\(\$cm->uservisible\)/);
  assert.match(groups, /empty\(\$section->uservisible\)/);
});

test('Moodle editor offers multiple existing sections/resources, never free-form URLs', () => {
  assert.match(form, /addElement\('autocomplete', \$field/);
  assert.match(form, /'multiple' => true/);
  assert.match(form, /'tags' => false/);
  assert.match(form, /add_action_buttons/);
  assert.match(form, /addElement\('static', 'supportinfo'/);
  assert.doesNotMatch(form, /addElement\('text'.*(?:url|link)/i);
  assert.match(controller, /course_quicklink_groups::options\(/);
  assert.match(controller, /course_quicklink_groups::save\(/);
  assert.doesNotMatch(controller, /INSERT INTO|UPDATE .*course/);
});

test('one destination opens directly, multiple destinations expand accessible list', () => {
  assert.match(groups, /count\(\$targets\) > 1/);
  assert.match(groups, /count\(\$targets\) === 1/);
  assert.match(legacy, /course_quicklink_groups::apply\(/);
  assert.match(template, /<details class="rsmax-courseutilities-multiple">/);
  assert.match(template, /<summary class="rsmax-courseutilities-link">/);
  assert.match(template, /\{\{#targets\}\}/);
  assert.match(template, /\{\{#url\}\}/);
  assert.match(template, /\{\{\^url\}\}/);
});

test('CAU stays centralized, existing single-link choices still work before migration', () => {
  assert.match(groups, /if \(\$item\['key'\] === 'support'\)/);
  assert.match(groups, /if \(\$saved === null\)/);
  assert.match(legacy, /'coursequicklinks_' \. \$courseid/);
  assert.match(legacy, /support_url\(\)/);
  assert.match(controller, /course_utilities::choices_for_course\(/);
});
