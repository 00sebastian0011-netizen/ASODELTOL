#!/usr/bin/env node
import fs from 'node:fs/promises';
import process from 'node:process';

const args = process.argv.slice(2);
const valueAfter = (name) => { const i = args.indexOf(name); return i >= 0 ? args[i + 1] : undefined; };
const origin = valueAfter('--url') || 'https://aulaasodeltol.online';
const snapshotPath = valueAfter('--snapshot');
const checks = [];
const add = (id, status, detail) => checks.push({id, status, detail});
try {
  const parsed = new URL(origin);
  if (parsed.protocol !== 'https:' || parsed.pathname !== '/' || parsed.search || parsed.hash || parsed.username || parsed.password) throw new Error('origin must be an HTTPS origin without path, query or credentials');
  if (parsed.origin !== 'https://aulaasodeltol.online') throw new Error('origin is not allowlisted');
} catch (error) { add('origin', 'FAIL', error.message); }
if (!checks.length && !snapshotPath) {
  try {
    const response = await fetch(origin, {redirect: 'manual', signal: AbortSignal.timeout(10000)});
    const location = response.headers.get('location');
    const redirected = location && new URL(location, origin).origin !== origin;
    add('public_origin', 'BLOCKED', redirected ? 'public endpoint redirects outside allowlist' : `HTTP ${response.status}; no authenticated policy claim`);
  } catch { add('public_origin', 'BLOCKED', 'public endpoint unavailable or network check failed'); }
}
if (snapshotPath) {
  try {
    const snapshot = JSON.parse(await fs.readFile(snapshotPath, 'utf8'));
    const policy = snapshot.policy || {};
    const acceptance = snapshot.acceptance_probe || {};
    add('tool_policy_handler', snapshot.sitepolicyhandler === 'tool_policy' ? 'PASS' : 'FAIL', 'sanitized operator snapshot');
    add('mandatory_authenticated_policy', policy.audience === 'loggedin' && policy.optional === false && policy.status === 'active' ? 'PASS' : 'BLOCKED', 'requires validated active policy');
    add('acceptance_api', acceptance.method === 'tool_policy_api' && acceptance.accepted?.status === 1 ? 'PASS' : 'BLOCKED', 'requires authorized synthetic account evidence');
    add('rejection_pending', acceptance.declined?.status !== 1 && acceptance.pending === null ? 'PASS' : 'BLOCKED', 'absence of acceptance is not consent');
  } catch { add('snapshot', 'FAIL', 'snapshot is not valid sanitized JSON'); }
}
const overall = checks.some((check) => check.status === 'FAIL') ? 'FAIL' : checks.some((check) => check.status === 'BLOCKED') ? 'BLOCKED' : 'PASS';
console.log(JSON.stringify({schema: 'asodeltol.policy-preflight.v1', origin: 'https://aulaasodeltol.online', overall, checks}, null, 2));
process.exitCode = overall === 'FAIL' ? 1 : 0;
