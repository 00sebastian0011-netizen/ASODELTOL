import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const read = path => readFileSync(path, 'utf8');
const menu = read('plugins/local/asodeltolstaff/classes/hook_callbacks.php');
const guide = read('plugins/local/asodeltolstaff/help.php');
const es = read('plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php');
const en = read('plugins/local/asodeltolstaff/lang/en/local_asodeltolstaff.php');
const links = read('plugins/theme/rsmax/classes/local/course_utilities.php');
const editor = read('plugins/theme/rsmax/classes/form/course_quicklinks_form.php');
const controller = read('plugins/theme/rsmax/quicklinks.php');
const form = read('plugins/local/asodeltolstaff/support.php');

test('primary navigation groups guide and CAU, while keeping reports separate', () => {
  assert.match(menu, /\$help\s*=\s*\$hook->get_primaryview\(\)->add\(get_string\('helpandsupport'/);
  assert.match(menu, /\$help->add\(get_string\('platformhelp'/);
  assert.match(menu, /\$help->add\(get_string\('supportchannel'/);
  assert.match(menu, /support::recipient\(\)\s*!==\s*null/);
  assert.match(menu, /\$hook->get_primaryview\(\)->add\(get_string\('reportsdownloads'/);
  assert.doesNotMatch(menu, /\$hook->get_primaryview\(\)->add\(get_string\('supportchannel'/);
  assert.match(es, /\['helpandsupport'\]\s*=\s*'Ayuda y soporte'/);
  assert.match(es, /\['supportchannel'\]\s*=\s*'Centro de Atención \(CAU\)'/);
  assert.match(en, /\['helpandsupport'\]/);
});

test('guide and course shortcut go to the same Staff support workflow', () => {
  assert.match(guide, /new moodle_url\('\/local\/asodeltolstaff\/support.php'\)/);
  assert.match(links, /new \\moodle_url\('\/local\/asodeltolstaff\/support.php'\)/);
  assert.match(form, /\\local_asodeltolstaff\\support::send\(/);
  assert.match(form, /\\local_asodeltolstaff\\support::visible_cases\(/);
});

test('course-specific or legacy links cannot override institutional CAU', () => {
  assert.match(links, /\$key\s*===\s*'support'\s*&&\s*\$id\s*!==\s*0/);
  assert.match(links, /Legacy per-course CAU URLs cannot replace/);
  assert.match(links, /Keep the one CAU destination selected by support_url/);
  assert.match(editor, /addElement\('static', 'supportinfo'/);
  assert.match(editor, /addElement\('hidden', \$field, 0\)/);
  assert.match(controller, /\$key\s*===\s*'support'\s*\?\s*0/);
});
