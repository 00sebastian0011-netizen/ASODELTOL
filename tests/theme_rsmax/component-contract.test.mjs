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
const renderer = read('plugins/theme/rsmax/classes/output/tool_policy_renderer.php');
const scss = read('plugins/theme/rsmax/scss/post.scss');

test('login preserves Boost main content without an institutional notice', () => {
  assert.match(login, /\{\{\{ output\.main_content \}\}\}/);
  assert.doesNotMatch(login, /rsmax-login-free-notice|coursefreeannouncement|DNSFT/);
  assert.doesNotMatch(css, /rsmax-login-free-notice|rsmax-login-free-3s/);
});

test('quick links remain visible through course, activity and editing pages', () => {
  assert.match(layout, /\$incourse\s*&&\s*in_array\(\$PAGE->pagelayout, \['course', 'incourse'\], true\)/);
  const utilitiesBlock = layout.match(/\$courseutilities = false;[\s\S]*?\/\/ Activity pages open/)[0];
  assert.doesNotMatch(utilitiesBlock, /user_is_editing/);
  assert.match(course, /\{\{#url\}\}/);
  assert.match(course, /\{\{\^url\}\}/);
  assert.match(course, /rsmax-courseutilities-disabled/);
  assert.match(provider, /empty\(\$cm->uservisible\)/);
  assert.match(provider, /safe_local_path/);
  assert.doesNotMatch(provider, /prospectiva\.edu\.co|google\.com\/spreadsheets/);
  assert.match(course, /coursequickaccountrequired/);
  assert.match(strings, /Requiere cuenta de ASODELTOL/);
});

test('gratuity notice is contextual to the actual front page and course home', () => {
  assert.match(layout, /\$PAGE->pagelayout === 'frontpage' \|\| \$oncoursehome/);
  assert.match(drawers, /\{\{#institutionalnotice\}\}\{\{> theme_rsmax\/institutional_notices \}\}/);
  assert.match(notices, /coursefreeannouncement/);
  assert.doesNotMatch(notices, /courseconsenttitle|policyurl|courseconsentaction/);
  assert.doesNotMatch(layout, /\$sitenotices/);
});

test('consent is delegated to Moodle tool_policy, not repeated by the theme', () => {
  assert.match(provider, /sitepolicyhandler/);
  assert.match(provider, /get_current_versions_ids/);
  assert.match(provider, /admin\/tool\/policy\/user\.php/);
  assert.match(course, /\{\{#policyurl\}\}/);
  assert.match(renderer, /instanceof page_agreedocs/);
  assert.match(renderer, /acceptancessavedsucessfully/);
  assert.match(strings, /Su elección ha sido guardada/);
  assert.match(scss, /\.path-admin-tool-policy/);
  assert.match(scss, /#1b5e49/);
  assert.doesNotMatch(notices, /policyurl|courseconsentmissing|courseconsenttitle/);
  assert.doesNotMatch(provider, /INSERT INTO|user_info_data/);
});

test('course index uses the accessible ASODELTOL green gradient', () => {
  const preScss = read('plugins/theme/rsmax/scss/pre.scss');
  assert.match(preScss, /\$rsmax-index-gradient-start:\s*#566a40\s*!default/);
  assert.match(preScss, /\$rsmax-index-gradient-end:\s*#104b38\s*!default/);
  assert.match(scss, /background-image:\s*linear-gradient\([\s\S]*?\$rsmax-index-gradient-start 0%[\s\S]*?\$rsmax-index-gradient-end 100%/);
  assert.match(scss, /\.drawerheader\s*\{[\s\S]*?background:\s*\$rsmax-index-gradient-start/);
});
