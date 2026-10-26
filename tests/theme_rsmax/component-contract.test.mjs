import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';

const read = (path) => readFileSync(resolve(process.cwd(), path), 'utf8');
const login = read('plugins/theme/rsmax/templates/core/login_layout.mustache');
const css = read('plugins/theme/rsmax/style/custom-login.css');
const course = read('plugins/theme/rsmax/templates/course_utilities.mustache');
const notices = read('plugins/theme/rsmax/templates/institutional_notices.mustache');
const drawers = read('plugins/theme/rsmax/templates/drawers.mustache');
const provider = read('plugins/theme/rsmax/classes/local/course_utilities.php');
const layout = read('plugins/theme/rsmax/layout/drawers.php');
const strings = read('plugins/theme/rsmax/lang/es/theme_rsmax.php');

test('login preserves Boost main content and announces gratuity for three seconds', () => {
  assert.match(login, /Las acciones de formación ejecutadas en el marco de la convocatoria DNSFT-0001-FCE/);
  assert.match(login, /\{\{\{ output\.main_content \}\}\}/);
  assert.match(login, /role="status"/);
  assert.match(css, /rsmax-login-free-3s\s+3s/);
  assert.match(css, /pointer-events:\s*none/);
});

test('quick links follow the current user through course and activity pages', () => {
  assert.match(layout, /\$incourse\s*&&\s*in_array\(\$PAGE->pagelayout, \['course', 'incourse'\], true\)/);
  assert.match(layout, /!\$PAGE->user_is_editing\(\)/);
  assert.match(course, /\{\{#url\}\}/);
  assert.match(course, /\{\{\^url\}\}/);
  assert.match(course, /rsmax-courseutilities-disabled/);
  assert.match(provider, /empty\(\$cm->uservisible\)/);
  assert.match(provider, /safe_local_path/);
  assert.doesNotMatch(provider, /prospectiva\.edu\.co|google\.com\/spreadsheets/);
});

test('institutional notices reach authenticated RSMax pages without an Interventoria role check', () => {
  assert.match(layout, /\$sitenotices\s*=\s*\$utilitiesenabled\s*&&\s*isloggedin\(\)/);
  assert.match(drawers, /\{\{#sitenotices\}\}\{\{> theme_rsmax\/institutional_notices \}\}/);
  assert.match(notices, /coursefreeannouncement/);
  assert.match(notices, /courseconsenttitle/);
  assert.doesNotMatch(course, /courseconsenttitle/);
});

test('consent is delegated to Moodle tool_policy, not faked by the theme', () => {
  assert.match(provider, /sitepolicyhandler/);
  assert.match(provider, /admin\/tool\/policy\/user\.php/);
  assert.match(notices, /\{\{#policyurl\}\}/);
  assert.match(notices, /\{\{\^policyurl\}\}/);
  assert.match(strings, /courseconsentmissing/);
  assert.doesNotMatch(provider, /INSERT INTO|user_info_data/);
});
