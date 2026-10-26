/**
 * Separate ASODELTOL QA executor.
 *
 * It consumes only MCP-created job manifests. It never accepts code, shell,
 * SQL, arbitrary paths, or credentials over MCP. The account vault and the
 * case configuration stay on the VPS. Playwright state is created ephemerally
 * on the VPS and removed before this process exits.
 */
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';
import {execFile} from 'node:child_process';
import {promisify} from 'node:util';

const execFileAsync = promisify(execFile);
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const stateDir = path.resolve(process.env.ASODELTOL_VALIDATION_STATE_DIR || path.join(root, 'build/anexo14/validation-state'));
const jobsDir = path.join(stateDir, 'jobs');
const catalogFile = path.resolve(process.env.ASODELTOL_VALIDATION_CATALOG_FILE || path.join(root, 'docs/anexo14/qa/production-test-catalog.json'));
const maxCases = 5;
const maxTimeoutMs = 120000;

function now() { return new Date().toISOString(); }
function readJson(file) { return JSON.parse(fs.readFileSync(file, 'utf8')); }
function writeJson(file, value) {
  const temp = `${file}.tmp`;
  fs.writeFileSync(temp, `${JSON.stringify(value)}\n`, {encoding: 'utf8', mode: 0o600});
  fs.renameSync(temp, file);
}
function safeText(value, max = 240) { return typeof value === 'string' ? value.slice(0, max) : undefined; }
function validBase(value) {
  try {
    const url = new URL(value);
    return url.protocol === 'https:' && url.hostname === 'aulas.prospectiva.edu.co' && !url.username && !url.password;
  } catch { return false; }
}
function safeEvidenceId(validationId, caseId) {
  return `ev-${crypto.createHash('sha256').update(`${validationId}:${caseId}`).digest('hex').slice(0, 16)}`;
}
function audit(event, fields = {}) {
  fs.mkdirSync(stateDir, {recursive: true, mode: 0o700});
  const safe = {at: now(), event};
  for (const key of ['validation_id', 'status', 'reason']) if (typeof fields[key] === 'string') safe[key] = fields[key].slice(0, 160);
  if (Array.isArray(fields.case_ids)) safe.case_ids = fields.case_ids.slice(0, maxCases).map(String);
  fs.appendFileSync(path.join(stateDir, 'audit.jsonl'), `${JSON.stringify(safe)}\n`, {encoding: 'utf8', mode: 0o600});
}
function compactResult(result) {
  const safe = {case_id: result.case_id, requirements: Array.isArray(result.requirements) ? result.requirements.slice(0, maxCases) : [], status: result.status};
  for (const key of ['reason', 'method', 'sha256', 'expected_sha256', 'current_time']) if (result[key] !== undefined) safe[key] = typeof result[key] === 'string' ? safeText(result[key], 240) : result[key];
  for (const key of ['http_status', 'bytes']) if (Number.isFinite(result[key])) safe[key] = result[key];
  if (Array.isArray(result.checks)) safe.checks = result.checks.slice(0, maxCases).map(check => ({
    url: safeText(check.url, 240), status: check.status, http_status: check.http_status, bytes: check.bytes,
    viewport: check.viewport, denied: check.denied, overflow: check.overflow,
  }));
  return safe;
}
function reportEvidence(validationId, result) {
  const item = {evidence_id: safeEvidenceId(validationId, result.case_id), case_id: result.case_id, status: result.status, method: result.method || 'executor', generated_at: now(), limitation: 'Sanitized metadata only; raw browser state and downloaded bytes remain on the VPS or are discarded.'};
  if (typeof result.sha256 === 'string' && /^[a-f0-9]{64}$/i.test(result.sha256)) item.sha256 = result.sha256;
  if (Number.isFinite(result.bytes)) item.bytes = result.bytes;
  return item;
}

async function loginAndCreateState(config, vault) {
  let playwright;
  try { playwright = await import('playwright'); } catch { throw new Error('executor_dependency_unavailable'); }
  if (!validBase(config.base_url)) throw new Error('executor_base_url_not_allowed');
  if (!vault || typeof vault.username !== 'string' || typeof vault.password !== 'string' || !vault.username || !vault.password) throw new Error('executor_account_vault_invalid');
  const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'asodeltol-qa-'));
  const statePath = path.join(tempDir, 'session.json');
  const browser = await playwright.chromium.launch({headless: true});
  const context = await browser.newContext({baseURL: config.base_url, acceptDownloads: false});
  const page = await context.newPage();
  page.setDefaultTimeout(20000);
  try {
    await page.goto(new URL('/login/index.php', config.base_url).toString(), {waitUntil: 'domcontentloaded'});
    await page.locator('#username').fill(vault.username);
    await page.locator('#password').fill(vault.password);
    await Promise.all([page.waitForURL(url => !url.pathname.includes('/login/')), page.locator('#loginbtn').click()]);
    await context.storageState({path: statePath});
    return {statePath, tempDir, browser};
  } catch {
    await context.close().catch(() => {}); await browser.close().catch(() => {}); fs.rmSync(tempDir, {recursive: true, force: true});
    throw new Error('executor_login_failed');
  }
}

async function runJob(job) {
  const catalog = readJson(catalogFile);
  const cases = new Map((catalog.cases || []).map(item => [item.case_id, item]));
  if (!Array.isArray(job.case_ids) || job.case_ids.length < 1 || job.case_ids.length > maxCases) throw new Error('job_case_limit_invalid');
  const destructive = job.case_ids.filter(caseId => cases.get(caseId)?.kind === 'upload');
  const results = destructive.map(caseId => ({case_id: caseId, requirements: cases.get(caseId).requirements, status: 'BLOCKED', reason: 'Requires later authorization, isolation, backup and recovery', method: 'policy'}));
  const runnable = job.case_ids.filter(caseId => !destructive.includes(caseId));
  if (!runnable.length) return {results, evidence: results.map(resultEvidence => reportEvidence(job.validation_id, resultEvidence)), executor: {method: 'policy', version: '1.0'}};
  const accountFile = process.env.ASODELTOL_QA_ACCOUNT_FILE;
  const configFile = process.env.ASODELTOL_QA_CONFIG_FILE;
  if (!accountFile || !configFile || path.resolve(accountFile).startsWith(root) || path.resolve(configFile).startsWith(root)) {
    return {results: results.concat(runnable.map(caseId => ({case_id: caseId, requirements: cases.get(caseId).requirements, status: 'BLOCKED', reason: 'Authorized VPS account vault and case configuration are unavailable', method: 'preflight'}))), evidence: [], executor: {method: 'preflight', version: '1.0'}};
  }
  let vault; let config;
  try { vault = readJson(accountFile); config = readJson(configFile); } catch { return {results: results.concat(runnable.map(caseId => ({case_id: caseId, requirements: cases.get(caseId).requirements, status: 'BLOCKED', reason: 'Authorized VPS account vault or case configuration is unreadable', method: 'preflight'}))), evidence: [], executor: {method: 'preflight', version: '1.0'}}; }
  let session;
  try { session = await loginAndCreateState(config, vault); } catch (error) {
    return {results: results.concat(runnable.map(caseId => ({case_id: caseId, requirements: cases.get(caseId).requirements, status: 'BLOCKED', reason: error.message, method: 'playwright_login'}))), evidence: [], executor: {method: 'playwright_login', version: '1.0'}};
  }
  try {
    const out = `executor-${job.validation_id}.json`;
    const childEnv = {...process.env, ASODELTOL_PROD_BASE_URL: config.base_url, ASODELTOL_PROD_STORAGE_STATE: session.statePath};
    const mapping = {download_url: 'ASODELTOL_PROD_DOWNLOAD_URL', download_sha256: 'ASODELTOL_PROD_DOWNLOAD_SHA256', video_path: 'ASODELTOL_PROD_VIDEO_PATH', course_path: 'ASODELTOL_PROD_COURSE_PATH', module_paths: 'ASODELTOL_PROD_MODULE_PATHS', quicklink_urls: 'ASODELTOL_PROD_QUICKLINK_URLS', activity_paths: 'ASODELTOL_PROD_ACTIVITY_PATHS', block_selectors: 'ASODELTOL_PROD_BLOCK_SELECTORS', negative_paths: 'ASODELTOL_PROD_NEGATIVE_PATHS', responsive_paths: 'ASODELTOL_PROD_RESPONSIVE_PATHS'};
    for (const [key, envName] of Object.entries(mapping)) if (config[key] !== undefined) childEnv[envName] = typeof config[key] === 'string' ? config[key] : JSON.stringify(config[key]);
    const timeoutMs = Math.min(maxTimeoutMs, Math.max(30000, Number(process.env.ASODELTOL_QA_EXECUTOR_TIMEOUT_MS || 90000)));
    await execFileAsync(process.execPath, [path.join(root, 'scripts/anexo14/production-validation.mjs'), '--run', `--ids=${runnable.join(',')}`, `--out=${out}`], {cwd: root, env: childEnv, timeout: timeoutMs, maxBuffer: 256 * 1024, shell: false});
    const report = readJson(path.join(root, 'build/anexo14/production', out));
    const reportResults = new Map((report.results || []).map(result => [result.case_id, compactResult(result)]));
    for (const caseId of runnable) results.push(reportResults.get(caseId) || {case_id: caseId, requirements: cases.get(caseId).requirements, status: 'BLOCKED', reason: 'Executor did not return a case result', method: 'executor'});
    return {results, evidence: results.map(resultEvidence => reportEvidence(job.validation_id, resultEvidence)), executor: {method: 'playwright+registered-runner', version: '1.0'}};
  } catch (error) {
    return {results: results.concat(runnable.map(caseId => ({case_id: caseId, requirements: cases.get(caseId).requirements, status: 'BLOCKED', reason: error.code === 'ETIMEDOUT' ? 'executor_timeout' : 'executor_failed', method: 'playwright+registered-runner'}))), evidence: [], executor: {method: 'playwright+registered-runner', version: '1.0'}};
  } finally {
    await session.browser.close().catch(() => {});
    fs.rmSync(session.tempDir, {recursive: true, force: true});
  }
}

async function main() {
  if (process.argv.slice(2).some(arg => arg !== '--once')) throw new Error('Only --once is supported');
  fs.mkdirSync(jobsDir, {recursive: true, mode: 0o700});
  const lockPath = path.join(stateDir, 'executor.lock');
  let lock;
  try { lock = fs.openSync(lockPath, 'wx'); } catch { console.log(JSON.stringify({status: 'BUSY'})); return; }
  try {
    const files = fs.readdirSync(jobsDir).filter(name => /^val-[A-Za-z0-9_-]+\.json$/.test(name)).sort();
    const queued = files.map(name => path.join(jobsDir, name)).map(file => ({file, job: readJson(file)})).find(item => item.job.status === 'QUEUED');
    if (!queued) { console.log(JSON.stringify({status: 'NO_JOB'})); return; }
    const job = queued.job;
    job.status = 'RUNNING'; job.executor = {method: 'starting', version: '1.0'}; job.started_at = now(); writeJson(queued.file, job);
    audit('started', {validation_id: job.validation_id, case_ids: job.case_ids, status: 'RUNNING'});
    let outcome;
    try { outcome = await runJob(job); } catch { outcome = {results: job.case_ids.map(caseId => ({case_id: caseId, requirements: [], status: 'BLOCKED', reason: 'executor_failed', method: 'executor'})), evidence: [], executor: {method: 'executor', version: '1.0'}}; }
    job.results = outcome.results.map(compactResult);
    job.evidence = outcome.evidence;
    job.executor = outcome.executor;
    job.finished_at = now();
    job.status = job.results.some(result => result.status === 'FAIL') ? 'FAIL' : job.results.some(result => result.status === 'BLOCKED') ? 'BLOCKED' : 'PASS';
    writeJson(queued.file, job);
    audit('finished', {validation_id: job.validation_id, case_ids: job.case_ids, status: job.status});
    console.log(JSON.stringify({validation_id: job.validation_id, status: job.status, counts: {PASS: job.results.filter(result => result.status === 'PASS').length, FAIL: job.results.filter(result => result.status === 'FAIL').length, BLOCKED: job.results.filter(result => result.status === 'BLOCKED').length}}));
  } finally {
    try { fs.closeSync(lock); } catch {}
    try { fs.unlinkSync(lockPath); } catch {}
  }
}

main().catch(error => { console.error(JSON.stringify({status: 'FAIL', reason: safeText(error.message)})); process.exitCode = 1; });
