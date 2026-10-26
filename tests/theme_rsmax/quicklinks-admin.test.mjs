import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const load = path => readFileSync(path, 'utf8');
const course = load('plugins/theme/rsmax/classes/local/course_utilities.php');
const controller = load('plugins/theme/rsmax/quicklinks.php');
const form = load('plugins/theme/rsmax/classes/form/course_quicklinks_form.php');
const template = load('plugins/theme/rsmax/templates/course_utilities.mustache');

test('course editor requires course membership, editing capability and session key', () => {
  assert.match(controller, /require_course_login\(\$course\)/);
  assert.match(controller, /require_capability\('moodle\/course:manageactivities', \$context\)/);
  assert.match(controller, /require_sesskey\(\)/);
  assert.match(course, /require_capability\('moodle\/course:manageactivities'/);
  assert.match(template, /\{\{#manageurl\}\}/);
  assert.match(course, /self::can_manage\(/);
});

test('selection is per-course, stores approved cmids, and verifies module membership', () => {
  assert.match(course, /'coursequicklinks_' \. \$courseid/);
  assert.match(course, /get_fast_modinfo\(\$course\)/);
  assert.match(course, /validate_choices\(/);
  assert.match(course, /isset\(\$modinfo->cms\[\$id\]\)/);
  assert.match(course, /eligible_module\(/);
  assert.match(course, /json_encode\(\$choices, JSON_THROW_ON_ERROR\)/);
  assert.match(course, /empty\(\$cm->uservisible\)/);
  assert.match(course, /unset\(\$destinations\[\$key\]\)/);
});

test('form offers Moodle activity selectors and optional automatic/disabled modes', () => {
  for (const id of ['announcements','recordings','materials','workshops','live','support']) {
    assert.match(course, new RegExp("'" + id + "' =>"));
  }
  assert.match(course, /coursequickautomatic/);
  assert.match(course, /coursequickdisabled/);
  assert.match(form, /extends \\moodleform/);
  assert.match(form, /addElement\('select'/);
  assert.match(form, /add_action_buttons/);
  assert.doesNotMatch(form, /addElement\('text'.*(?:url|link)/i);
  assert.doesNotMatch(controller, /INSERT INTO|UPDATE .*course/);
});

test('students cannot follow unavailable links nor change settings via display controls', () => {
  assert.match(course, /'manageurl' => self::can_manage/);
  assert.match(course, /!empty\(\$cm->uservisible\)/);
  assert.match(template, /\{\{\^url\}\}/);
  assert.match(template, /\{\{#url\}\}/);
});
