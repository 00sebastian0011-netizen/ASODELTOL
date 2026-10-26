/** Public, unauthenticated policy surface probe. No credentials, cookies or response bodies are retained. */
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const base = 'https://aulaasodeltol.online';
const paths = ['/', '/login/index.php', '/admin/tool/policy/index.php', '/admin/tool/policy/user.php', '/admin/settings.php?section=toolpolicy'];
const output = path.join(root, 'build/anexo14/production/policy-public-probe-20261026.json');

function safeLocation(value) {
  if (!value) return null;
  try { const url = new URL(value); return url.origin === base ? `${url.origin}${url.pathname}` : '[EXTERNAL_REDIRECT_REDACTED]'; } catch { return '[INVALID_REDIRECT]'; }
}

const checks = [];
for (const requestPath of paths) {
  try {
    const response = await fetch(`${base}${requestPath}`, {redirect: 'manual', signal: AbortSignal.timeout(15000)});
    checks.push({path: requestPath, status: response.status, location: safeLocation(response.headers.get('location')), content_type: response.headers.get('content-type')});
  } catch (error) {
    checks.push({path: requestPath, status: 'BLOCKED', error: error.name});
  }
}

try {
  const login = await fetch(`${base}/login/index.php`, {signal: AbortSignal.timeout(15000)}).then(response => response.text());
  checks.push({page: 'login-markers', tool_policy_marker: login.includes('tool_policy') || login.includes('tool/policy'), privacy_marker: /privacidad|privacy|política/i.test(login), consent_marker: /consentimiento|consent|datos personales/i.test(login), login_form_marker: login.includes('loginbtn')});
} catch (error) { checks.push({page: 'login-markers', status: 'BLOCKED', error: error.name}); }

const report = {generated_at: new Date().toISOString(), environment: 'production-public-unauthenticated', base, checks, limitations: ['No administrative session', 'No user accounts or agreement records inspected', 'HTTP redirects do not prove tool_policy activation or policy acceptance']};
fs.mkdirSync(path.dirname(output), {recursive: true});
fs.writeFileSync(output, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
console.log(JSON.stringify({file: path.relative(root, output).replaceAll('\\', '/'), checks: checks.length}));
