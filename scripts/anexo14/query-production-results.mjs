import fs from 'node:fs';
import path from 'node:path';

const fileArg = process.argv.find(value => value.startsWith('--file='))?.slice(7);
const idsArg = process.argv.find(value => value.startsWith('--ids='))?.slice(6) || '';
const limit = Number(process.argv.find(value => value.startsWith('--limit='))?.slice(8) || 5);
if (!fileArg) throw new Error('Uso: --file=<resultado.json> [--ids=REQ-06-02,...] [--limit=5]');
const report = JSON.parse(fs.readFileSync(path.resolve(fileArg), 'utf8'));
const ids = new Set(idsArg.split(',').map(value => value.trim()).filter(Boolean));
const rows = report.results.filter(row => !ids.size || row.requirements.some(id => ids.has(id))).slice(0, Math.max(1, limit));
console.log(JSON.stringify({file: path.basename(fileArg), environment: report.environment, generated_at: report.generated_at, rows: rows.map(row => ({case_id: row.case_id, requirements: row.requirements, status: row.status, reason: row.reason, http_status: row.http_status, bytes: row.bytes, sha256: row.sha256, checks: row.checks?.map(check => ({url: check.url, status: check.status, viewport: check.viewport, denied: check.denied, overflow: check.overflow}))}))}, null, 2));
