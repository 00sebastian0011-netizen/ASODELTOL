import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import {classifyFiles} from '../../scripts/deploy/classify-changes.mjs';

const observer = fs.readFileSync('plugins/local/asodeltolstaff/classes/final_grade_notification.php', 'utf8');
const events = fs.readFileSync('plugins/local/asodeltolstaff/db/events.php', 'utf8');
const messages = fs.readFileSync('plugins/local/asodeltolstaff/db/messages.php', 'utf8');
const navigation = fs.readFileSync('plugins/local/asodeltolstaff/lib.php', 'utf8');
const spanish = fs.readFileSync('plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php', 'utf8');

test('REQ-20-03: notification runs only after native course completion and never invents a pass rule', () => {
  assert.match(events, /\\core\\event\\course_completed/);
  assert.doesNotMatch(events, /user_graded/);
  assert.match(observer, /\$CFG->libdir \. '\/gradelib\.php'/);
  assert.match(observer, /\$CFG->dirroot \. '\/grade\/querylib\.php'/);
  assert.match(observer, /grade_get_course_grade\(\$userid, \$courseid\)/);
  assert.match(observer, /\$grade->item->hidden/);
  assert.match(observer, /\$grade->grade === null/);
  assert.match(observer, /course_completions/);
  assert.match(observer, /already_notified/);
  assert.doesNotMatch(observer, /gradepass|passing|aprobado|reprobado/i);
  assert.match(messages, /coursegradefinalized/);
  assert.ok(spanish.includes('no establece una nota aprobatoria'));
});

test('REQ-20-03: course navigation exposes own grades and progress without replacing Moodle gradebook', () => {
  assert.match(navigation, /local\/asodeltolstaff\/progress\.php/);
  assert.match(navigation, /local_asodeltolstaff_progress/);
  assert.match(navigation, /local\/asodeltolstaff:viewsessions/);
  const progress = fs.readFileSync('plugins/local/asodeltolstaff/progress.php', 'utf8');
  assert.match(progress, /grade\/report\/user\/index\.php/);
  assert.match(progress, /'userid'\s*=>\s*\$userid/);
});

test('REQ-20-03 files are deployable plugin changes, never Moodle core', () => {
  const classification = classifyFiles([
    'plugins/local/asodeltolstaff/classes/final_grade_notification.php',
    'plugins/local/asodeltolstaff/classes/observer.php',
    'plugins/local/asodeltolstaff/db/events.php',
    'plugins/local/asodeltolstaff/db/messages.php',
    'plugins/local/asodeltolstaff/lib.php',
    'plugins/local/asodeltolstaff/version.php',
  ]);
  assert.equal(classification.deployable, true);
  assert.notEqual(classification.risk, 'blocked');
});
