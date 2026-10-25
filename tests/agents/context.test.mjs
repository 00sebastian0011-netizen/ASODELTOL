import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {
  collectBrief,
  readRequirementRows,
  assertTrackingMatches,
  validateContextMap,
} from '../../scripts/quality/agent-brief.mjs';

const ROOT = fileURLToPath(new URL('../../', import.meta.url));
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

test('brief uses current plugin/catalog and reconciles exactly 82 original IDs', () => {
  const brief = collectBrief(ROOT);
  assert.equal(brief.total, 82);
  assert.equal(Object.values(brief.counts).reduce((a, b) => a + b, 0), 82);
  assert.ok(/^\d+$/.test(brief.version));
  assert.ok(brief.units > 0 && brief.items > 0);
  assert.ok(Number.isInteger(brief.formal));
});

test('tracking fails closed on missing IDs and contradictory state', () => {
  const rows = readRequirementRows(read('docs/anexo14/MATRIZ_ASODELTOL_82.md'));
  const original = JSON.parse(read('docs/anexo14/TRAZABILIDAD_82_2026-10-25.json'));
  const missing = structuredClone(original);
  missing.rows.pop();
  assert.throws(() => assertTrackingMatches(rows, missing), /82 IDs/);
  const changed = structuredClone(original);
  changed.rows[0].state = 'ESTADO_ARTIFICIAL';
  assert.throws(() => assertTrackingMatches(rows, changed), /Discrepancia/);
});

test('context map is small, unique, has valid paths and no secret values', () => {
  const raw = read('docs/agents/context-map.json');
  const manifest = JSON.parse(raw);
  assert.ok(raw.length < 6000, 'map should stay compact');
  assert.ok(validateContextMap(ROOT, manifest) >= 5);
  assert.doesNotMatch(raw, /(?:password|private_key|token)\s*:\s*"[^"]+"/i);
});

test('agent entrypoint stays compact and README has no obsolete global deployment ban', () => {
  const agents = read('AGENTS.md');
  const readme = read('README.md');
  assert.ok(Buffer.byteLength(agents, 'utf8') < 6500, 'AGENTS.md too long: use domain map');
  assert.match(agents, /docs\/agents\/README\.md/);
  assert.match(readme, /npm run agent:brief/);
  assert.doesNotMatch(readme, /No se autoriza desde este repositorio:/);
  assert.doesNotMatch(readme, /El plugin y sus datos continúan limitados al staging local/);
});
