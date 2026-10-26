import test from 'node:test';
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import path from 'node:path';

const script = path.resolve('scripts/anexo14/policy-preflight.mjs');
const run = (args) => new Promise((resolve) => {
  const child = spawn(process.execPath, [script, ...args], {stdio: ['ignore', 'pipe', 'pipe']});
  let out = '';
  child.stdout.on('data', (chunk) => out += chunk);
  child.on('close', (code) => resolve({code, json: JSON.parse(out)}));
});

test('preflight rejects non-authorized origins', async () => {
  const result = await run(['--url', 'https://aulas.prospectiva.edu.co']);
  assert.equal(result.code, 1);
  assert.equal(result.json.overall, 'FAIL');
});

test('preflight reports a sanitized approved-policy snapshot', async () => {
  const file = path.resolve('build/anexo14/policy-preflight-test.json');
  const fs = await import('node:fs/promises');
  await fs.mkdir(path.dirname(file), {recursive: true});
  await fs.writeFile(file, JSON.stringify({
    sitepolicyhandler: 'tool_policy',
    policy: {audience: 'loggedin', optional: false, status: 'active'},
    acceptance_probe: {method: 'tool_policy_api', accepted: {status: 1}, declined: {status: 0}, pending: null},
  }));
  const result = await run(['--snapshot', file]);
  assert.equal(result.code, 0);
  assert.equal(result.json.overall, 'PASS');
  await fs.rm(file);
});

test('preflight keeps absence of response distinct from acceptance', async () => {
  const file = path.resolve('build/anexo14/policy-preflight-negative.json');
  const fs = await import('node:fs/promises');
  await fs.mkdir(path.dirname(file), {recursive: true});
  await fs.writeFile(file, JSON.stringify({
    sitepolicyhandler: 'tool_policy',
    policy: {audience: 'loggedin', optional: false, status: 'active'},
    acceptance_probe: {method: 'tool_policy_api', accepted: {status: 0}, declined: {status: 0}, pending: null},
  }));
  const result = await run(['--snapshot', file]);
  assert.equal(result.json.overall, 'BLOCKED');
  await fs.rm(file);
});
