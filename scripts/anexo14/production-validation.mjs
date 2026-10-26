import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';
import {activitiesComplete, downloadIdentity, PRODUCTION_ORIGIN, safeProductionUrl} from './validation-guards.mjs';

const root = path.resolve(process.env.ASODELTOL_QA_REPO_ROOT || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..'));
const catalogPath = path.resolve(process.env.ASODELTOL_PROD_CATALOG_FILE || path.join(root, 'docs/anexo14/qa/production-test-catalog.json'));
const catalog = JSON.parse(fs.readFileSync(catalogPath, 'utf8'));
const outRoot = path.resolve(process.env.ASODELTOL_QA_REPORT_ROOT || path.join(root, 'build/anexo14/production'));
const env = process.env;

function arg(name) {
  const item = process.argv.find(value => value === `--${name}` || value.startsWith(`--${name}=`));
  return item?.includes('=') ? item.slice(name.length + 3) : item ? true : undefined;
}

function jsonEnv(name) {
  if (!env[name]) return null;
  try { return JSON.parse(env[name]); } catch { return null; }
}

function safeUrl(value, base) {
  return safeProductionUrl(value, base);
}

function displayUrl(value, base) {
  const url = safeUrl(value, base);
  return url ? `${url.origin}${url.pathname}${url.search}` : '[INVALID_URL]';
}

function caseById(ids) {
  const selected = ids?.length ? catalog.cases.filter(item => ids.includes(item.case_id) || item.requirements.some(id => ids.includes(id))) : catalog.cases;
  return selected.length ? selected : catalog.cases;
}

function blocked(testCase, reason, details = {}) {
  return {case_id: testCase.case_id, requirements: testCase.requirements, status: 'BLOCKED', reason, ...details};
}

function pass(testCase, details = {}) {
  return {case_id: testCase.case_id, requirements: testCase.requirements, status: 'PASS', ...details};
}

function fail(testCase, reason, details = {}) {
  return {case_id: testCase.case_id, requirements: testCase.requirements, status: 'FAIL', reason, ...details};
}

function validateBase() {
  const base = env.ASODELTOL_PROD_BASE_URL;
  const url = safeUrl(base || '', base || 'https://invalid.local');
  if (!url || url.origin !== PRODUCTION_ORIGIN) return {base: null, reason: `ASODELTOL_PROD_BASE_URL debe ser ${PRODUCTION_ORIGIN}`};
  return {base: url.origin, reason: null};
}

function validateCatalog() {
  const ids = new Set();
  for (const item of catalog.cases) {
    if (!item.case_id || ids.has(item.case_id) || !item.requirements.length || !item.kind) throw new Error('Catálogo inválido: identificador, requisitos o tipo repetido/faltante.');
    ids.add(item.case_id);
  }
  return {schema_version: catalog.schema_version, cases: catalog.cases.length, prohibited: catalog.prohibited};
}

async function main() {
  if (arg('catalog')) { console.log(JSON.stringify(validateCatalog())); return; }
  const ids = String(arg('ids') || '').split(',').map(value => value.trim()).filter(Boolean);
  const selected = caseById(ids);
  const baseCheck = validateBase();
  const generatedAt = new Date().toISOString();
  const results = [];
  if (!arg('run')) {
    for (const item of selected) results.push(blocked(item, 'Ejecución no iniciada: usar --run después de revisar entradas y autorización.'));
  } else if (baseCheck.reason) {
    for (const item of selected) results.push(blocked(item, baseCheck.reason));
  } else {
    let chromium;
    try { ({chromium} = await import('playwright')); } catch { chromium = null; }
    if (!chromium) {
      for (const item of selected) results.push(blocked(item, 'Playwright no está disponible en node_modules; no instalar dependencias automáticamente.'));
    } else {
      const storageState = env.ASODELTOL_PROD_STORAGE_STATE;
      if (!storageState || !fs.existsSync(storageState) || path.resolve(storageState).startsWith(root)) {
        for (const item of selected) results.push(blocked(item, 'Falta storage state autorizada fuera del repositorio; no se solicitarán credenciales.'));
      } else {
        const browser = await chromium.launch({headless: true});
        const context = await browser.newContext({baseURL: baseCheck.base, storageState, acceptDownloads: true});
        const page = await context.newPage();
        page.setDefaultTimeout(20000);
        const visit = async (value) => {
          const url = safeUrl(value, baseCheck.base);
          if (!url) throw new Error('URL no válida o no HTTPS.');
          const response = await page.goto(url.toString(), {waitUntil: 'domcontentloaded'});
          const finalUrl = safeUrl(page.url(), baseCheck.base);
          if (!finalUrl) throw new Error('unauthorized_redirect');
          const body = await page.locator('body').innerText().catch(() => '');
          const login = page.locator('#loginbtn').count ? await page.locator('#loginbtn').count() : 0;
          return {url: displayUrl(finalUrl, baseCheck.base), status: response?.status() ?? 0, bytes: body.length, loginRequired: login > 0, errorPage: /errorcode|exception/i.test(body)};
        };
        for (const item of selected) {
          try {
            if (item.kind === 'upload') { results.push(blocked(item, 'Escritura en producción no autorizada: falta aislamiento, recuperación y autorización específica.')); continue; }
            if (item.kind === 'download_sha256') {
              const target = safeUrl(env.ASODELTOL_PROD_DOWNLOAD_URL, baseCheck.base);
              const expected = String(env.ASODELTOL_PROD_DOWNLOAD_SHA256 || '').toLowerCase();
              const expectedContentType = String(env.ASODELTOL_PROD_DOWNLOAD_CONTENT_TYPE || '').toLowerCase();
              const expectedFilename = String(env.ASODELTOL_PROD_DOWNLOAD_FILENAME || '');
              if (!target || !/^[a-f0-9]{64}$/.test(expected) || !expectedContentType || !expectedFilename) { results.push(blocked(item, 'Faltan URL, hash, tipo MIME y nombre esperado del recurso')); continue; }
              const response = await context.request.get(target.toString());
              const bytes = await response.body();
              const observed = crypto.createHash('sha256').update(bytes).digest('hex');
              const identity = downloadIdentity({status: response.status(), url: response.url(), headers: response.headers(), expectedSha256: expected, expectedContentType, expectedFilename, bytes: observed});
              results.push(identity.ok ? pass(item, {url: displayUrl(response.url(), baseCheck.base), http_status: response.status(), bytes: bytes.length, sha256: observed, method: 'authenticated_get+sha256+identity'}) : fail(item, identity.reason, {url: displayUrl(response.url(), baseCheck.base), http_status: response.status(), bytes: bytes.length, sha256: observed, expected_sha256: expected, expected_content_type: expectedContentType, expected_filename: expectedFilename, method: 'authenticated_get+sha256+identity'}));
            } else if (item.kind === 'video_playback') {
              if (!env.ASODELTOL_PROD_VIDEO_PATH) { results.push(blocked(item, 'Falta ASODELTOL_PROD_VIDEO_PATH')); continue; }
              const observed = await visit(env.ASODELTOL_PROD_VIDEO_PATH);
              const video = page.locator('video').first();
              if (observed.loginRequired || !(await video.count())) { results.push(fail(item, 'No se encontró un elemento video autenticado', observed)); continue; }
              await video.evaluate(async element => { element.muted = true; await element.play(); });
              await page.waitForFunction(() => Number(document.querySelector('video')?.currentTime || 0) > 0.5);
              results.push(pass(item, {...observed, current_time: await video.evaluate(element => element.currentTime)}));
            } else if (item.kind === 'navigation' || item.kind === 'responsive') {
              const values = item.kind === 'navigation' ? [env.ASODELTOL_PROD_COURSE_PATH, ...((jsonEnv('ASODELTOL_PROD_MODULE_PATHS') || []))] : (jsonEnv('ASODELTOL_PROD_RESPONSIVE_PATHS') || []);
              if (!values.length || values.some(value => typeof value !== 'string')) { results.push(blocked(item, 'Faltan rutas de navegación configuradas')); continue; }
              const checks = [];
              for (const viewport of item.kind === 'responsive' ? [{name: 'desktop', width: 1440, height: 900}, {name: 'mobile', width: 390, height: 844}] : [{name: 'desktop', width: 1440, height: 900}]) {
                await page.setViewportSize(viewport);
                for (const value of values) { const observed = await visit(value); const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2); checks.push({...observed, viewport: viewport.name, overflow}); }
              }
              results.push(checks.every(check => check.status === 200 && !check.loginRequired && !check.errorPage && !check.overflow) ? pass(item, {checks}) : fail(item, 'Una ruta no cargó, pidió login, mostró error o desbordó horizontalmente', {checks}));
            } else if (item.kind === 'quicklinks') {
              const links = jsonEnv('ASODELTOL_PROD_QUICKLINK_URLS');
              if (!Array.isArray(links) || links.length !== 6) { results.push(blocked(item, 'ASODELTOL_PROD_QUICKLINK_URLS debe contener exactamente seis URLs HTTPS')); continue; }
              const checks = []; for (const value of links) checks.push(await visit(value));
              results.push(checks.every(check => check.status === 200 && !check.loginRequired && !check.errorPage) ? pass(item, {checks}) : fail(item, 'Uno o más enlaces rápidos no cargaron correctamente', {checks}));
            } else if (item.kind === 'activities_blocks') {
              const paths = jsonEnv('ASODELTOL_PROD_ACTIVITY_PATHS'); const selectors = jsonEnv('ASODELTOL_PROD_BLOCK_SELECTORS');
              if (!Array.isArray(paths) || !paths.length || !Array.isArray(selectors) || !selectors.length) { results.push(blocked(item, 'Faltan rutas de actividades y selectores de bloques configurados')); continue; }
              const checks = []; for (const value of paths) { const observed = await visit(value); const matches = {}; for (const selector of selectors) matches[selector] = await page.locator(selector).count(); checks.push({...observed, matches}); }
              results.push(activitiesComplete(checks, selectors) ? pass(item, {checks}) : fail(item, 'Una página no cargó o faltan uno o más selectores esperados', {checks}));
            } else if (item.kind === 'negative_permissions') {
              const paths = jsonEnv('ASODELTOL_PROD_NEGATIVE_PATHS');
              if (!Array.isArray(paths) || !paths.length) { results.push(blocked(item, 'Faltan rutas negativas configuradas para el actor autorizado')); continue; }
              const checks = []; for (const value of paths) { const observed = await visit(value); checks.push({...observed, denied: observed.status === 401 || observed.status === 403 || observed.errorPage}); }
              results.push(checks.every(check => check.denied) ? pass(item, {checks}) : fail(item, 'Una ruta protegida fue accesible o no produjo denegación reconocible', {checks}));
            } else results.push(blocked(item, `Tipo de caso no implementado: ${item.kind}`));
          } catch (error) { results.push(fail(item, String(error.message).slice(0, 240))); }
        }
        await context.close(); await browser.close();
      }
    }
  }
  const report = {schema_version: 1, generated_at: generatedAt, environment: baseCheck.base ? 'production-read-only' : 'not_started', base_url: baseCheck.base, version: {status: 'NOT_VERIFIED', note: 'El runner no infiere la versión instalada; consultar el sondeo VPS de solo lectura.'}, selected_case_count: selected.length, counts: Object.fromEntries(['PASS', 'FAIL', 'BLOCKED', 'NOT_APPLICABLE'].map(status => [status, results.filter(row => row.status === status).length])), results, limitations: ['No prueba de subida en producción', 'No aceptación institucional', 'No sincronización Git/VPS inferida', 'No credenciales ni tokens almacenados']};
  fs.mkdirSync(outRoot, {recursive: true});
  const requested = String(arg('out') || `production-${generatedAt.replaceAll(':', '').replaceAll('.', '-')}.json`);
  const out = path.resolve(outRoot, path.basename(requested));
  fs.writeFileSync(out, JSON.stringify(report, null, 2) + '\n', 'utf8');
  console.log(JSON.stringify({file: path.relative(root, out).replaceAll('\\', '/'), counts: report.counts, selected_case_count: selected.length}));
}

main().catch(error => { console.error(JSON.stringify({status: 'FAIL', error: String(error.message).slice(0, 240)})); process.exitCode = 1; });
