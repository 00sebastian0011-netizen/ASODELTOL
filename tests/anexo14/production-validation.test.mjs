import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';

const root = path.resolve(import.meta.dirname, '../..');
const runner = path.join(root, 'scripts/anexo14/production-validation.mjs');
const catalogPath = path.join(root, 'docs/anexo14/qa/production-test-catalog.json');

test('production catalog is unique and declares only read-only cases', () => {
  const catalog = JSON.parse(fs.readFileSync(catalogPath, 'utf8'));
  assert.equal(catalog.scope, 'read_only_production');
  assert.equal(new Set(catalog.cases.map(item => item.case_id)).size, catalog.cases.length);
  assert.ok(catalog.prohibited.includes('upload'));
  assert.ok(catalog.cases.some(item => item.kind === 'negative_permissions'));
});

test('runner reports blocked without production URL or credentials', () => {
  const raw = execFileSync(process.execPath, [runner, '--run', '--ids=REQ-06-02', '--out=test-blocked.json'], {cwd: root, encoding: 'utf8', env: {...process.env, ASODELTOL_PROD_BASE_URL: '', ASODELTOL_PROD_STORAGE_STATE: ''}});
  const summary = JSON.parse(raw);
  assert.equal(summary.counts.BLOCKED, 1);
  const result = JSON.parse(fs.readFileSync(path.join(root, 'build/anexo14/production/test-blocked.json'), 'utf8'));
  assert.equal(result.results[0].status, 'BLOCKED');
  assert.match(result.results[0].reason, /ASODELTOL_PROD_BASE_URL|storage state|Playwright/);
  fs.rmSync(path.join(root, 'build/anexo14/production/test-blocked.json'));
});
