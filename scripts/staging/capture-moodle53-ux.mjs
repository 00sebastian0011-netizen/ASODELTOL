#!/usr/bin/env node
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, unlinkSync, writeFileSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { tmpdir } from 'node:os';

const BASE_URL = process.env.MOODLE_URL || 'http://127.0.0.1:8053';
const REPO_ROOT = resolve(process.cwd());
const RUNTIME_ROOT = join(REPO_ROOT, 'build', 'staging', 'moodle53');
const EVIDENCE_BASE = join(RUNTIME_ROOT, 'evidence', 'mission12');

const VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 900, isMobile: false },
    { name: 'tablet', width: 768, height: 1024, isMobile: true },
    { name: 'mobile', width: 375, height: 812, isMobile: true }
];

function findEdgeBinary() {
    const candidates = [
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        process.env.EDGE_PATH
    ].filter(Boolean);

    for (const candidate of candidates) {
        if (existsSync(candidate)) {
            return candidate;
        }
    }
    throw new Error('No se encontró el ejecutable de Microsoft Edge en las rutas estándar.');
}

function parseSetCookieHeaders(headersList) {
    const cookieMap = new Map();
    for (const header of headersList) {
        if (!header) continue;
        const [nameVal] = header.split(';');
        const eqIdx = nameVal.indexOf('=');
        if (eqIdx > 0) {
            const name = nameVal.substring(0, eqIdx).trim();
            const value = nameVal.substring(eqIdx + 1).trim();
            cookieMap.set(name, value);
        }
    }
    return cookieMap;
}

async function obtainMoodleSession(username, password) {
    const loginPageRes = await fetch(`${BASE_URL}/login/index.php`);
    const loginHtml = await loginPageRes.text();
    const tokenMatch = loginHtml.match(/name="logintoken"\s+value="([^"]+)"/);
    if (!tokenMatch) {
        throw new Error(`No se pudo obtener el logintoken para '${username}'.`);
    }
    const logintoken = tokenMatch[1];

    const getHeaders = loginPageRes.headers.getSetCookie ? loginPageRes.headers.getSetCookie() : [loginPageRes.headers.get('set-cookie') || ''];
    const sessionCookieMap = parseSetCookieHeaders(getHeaders);

    const formParams = new URLSearchParams();
    formParams.append('anchor', '');
    formParams.append('username', username);
    formParams.append('password', password);
    formParams.append('logintoken', logintoken);

    const postHeaders = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (sessionCookieMap.size > 0) {
        postHeaders['Cookie'] = Array.from(sessionCookieMap.entries()).map(([k, v]) => `${k}=${v}`).join('; ');
    }

    const loginRes = await fetch(`${BASE_URL}/login/index.php`, {
        method: 'POST',
        headers: postHeaders,
        body: formParams.toString(),
        redirect: 'manual'
    });

    const postHeadersList = loginRes.headers.getSetCookie ? loginRes.headers.getSetCookie() : [loginRes.headers.get('set-cookie') || ''];
    const postMap = parseSetCookieHeaders(postHeadersList);
    for (const [k, v] of postMap.entries()) {
        sessionCookieMap.set(k, v);
    }

    let location = loginRes.headers.get('location') || '';
    if (loginRes.status !== 303 && loginRes.status !== 302 && loginRes.status !== 200) {
        throw new Error(`Login para '${username}' falló con código HTTP ${loginRes.status}.`);
    }

    if (location.includes('testsession=')) {
        const testRes = await fetch(location, {
            headers: {
                'Cookie': Array.from(sessionCookieMap.entries()).map(([k, v]) => `${k}=${v}`).join('; ')
            },
            redirect: 'manual'
        });
        const testHeadersList = testRes.headers.getSetCookie ? testRes.headers.getSetCookie() : [testRes.headers.get('set-cookie') || ''];
        const testMap = parseSetCookieHeaders(testHeadersList);
        for (const [k, v] of testMap.entries()) {
            sessionCookieMap.set(k, v);
        }
        location = testRes.headers.get('location') || '';
    }

    if (location.includes('login/index.php') && !location.includes('testsession=')) {
        throw new Error(`Moodle rechazó las credenciales de '${username}'. Redirigido a: ${location}`);
    }

    const cdpCookies = [];
    for (const [name, value] of sessionCookieMap.entries()) {
        cdpCookies.push({
            name,
            value,
            domain: '127.0.0.1',
            path: '/'
        });
    }

    return cdpCookies;
}

async function captureAll(mode = 'after') {
    const targetDir = join(EVIDENCE_BASE, mode);
    if (!existsSync(targetDir)) {
        mkdirSync(targetDir, { recursive: true });
    }

    const studentSecretPath = join(RUNTIME_ROOT, 'secrets', 'student-password.txt');
    const adminSecretPath = join(RUNTIME_ROOT, 'secrets', 'admin-password.txt');

    if (!existsSync(studentSecretPath) || !existsSync(adminSecretPath)) {
        throw new Error('Faltan archivos de contraseñas sintéticas en build/staging/moodle53/secrets/');
    }

    const studentPassword = readFileSync(studentSecretPath, 'utf8').trim();
    const adminPassword = readFileSync(adminSecretPath, 'utf8').trim();

    console.log(`[CDP] Obteniendo sesiones sintéticas de Moodle...`);
    const studentCookies = await obtainMoodleSession('af8.synthetic.g01', studentPassword);
    const adminCookies = await obtainMoodleSession('asodeltol_admin', adminPassword);
    console.log(`[CDP] Sesiones autenticadas (estudiante: ${studentCookies.length} cookies, admin: ${adminCookies.length} cookies).`);

    const edgeBin = findEdgeBinary();
    const tempProfile = join(tmpdir(), `edge-cdp-${Date.now()}`);
    mkdirSync(tempProfile, { recursive: true });

    const cdpPort = 9222;
    const edgeArgs = [
        '--headless=new',
        `--remote-debugging-port=${cdpPort}`,
        `--user-data-dir=${tempProfile}`,
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        'about:blank'
    ];

    console.log(`[CDP] Iniciando Microsoft Edge headless en puerto ${cdpPort}...`);
    const proc = spawn(edgeBin, edgeArgs, { stdio: 'ignore' });

    let wsUrl = '';
    for (let i = 0; i < 30; i++) {
        await new Promise(r => setTimeout(r, 200));
        try {
            const listRes = await fetch(`http://127.0.0.1:${cdpPort}/json/list`);
            if (listRes.ok) {
                const list = await listRes.json();
                const page = list.find(t => t.type === 'page');
                if (page && page.webSocketDebuggerUrl) {
                    wsUrl = page.webSocketDebuggerUrl;
                    break;
                }
            }
        } catch (e) {
            // Reintentando
        }
    }

    if (!wsUrl) {
        proc.kill('SIGTERM');
        throw new Error('No se pudo conectar con la página de Edge headless.');
    }

    const ws = new WebSocket(wsUrl);
    await new Promise((res, rej) => {
        ws.onopen = res;
        ws.onerror = rej;
    });
    console.log(`[CDP] WebSocket conectado al target de página.`);

    let nextId = 1;
    const callbacks = new Map();
    ws.onmessage = (event) => {
        const text = typeof event.data === 'string' ? event.data : Buffer.from(event.data).toString('utf8');
        try {
            const msg = JSON.parse(text);
            if (msg.id && callbacks.has(msg.id)) {
                const { resolve, reject } = callbacks.get(msg.id);
                callbacks.delete(msg.id);
                if (msg.error) reject(new Error(msg.error.message || JSON.stringify(msg.error)));
                else resolve(msg.result);
            }
        } catch (e) {
            console.error('Error parseando mensaje CDP:', e);
        }
    };

    function send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = nextId++;
            callbacks.set(id, { resolve, reject });
            ws.send(JSON.stringify({ id, method, params }));
        });
    }

    await send('Network.enable');
    await send('Page.enable');
    await send('DOM.enable');

    const views = [
        {
            key: 'login',
            title: 'Inicio de Sesión',
            url: `${BASE_URL}/login/index.php`,
            cookies: null
        },
        {
            key: 'dashboard',
            title: 'Panel del Estudiante (/my/)',
            url: `${BASE_URL}/my/`,
            cookies: studentCookies
        },
        {
            key: 'course',
            title: 'Vista del Diplomado AF8 (/course/view.php?id=2)',
            url: `${BASE_URL}/course/view.php?id=2`,
            cookies: studentCookies
        },
        {
            key: 'staff',
            title: 'Módulo de Asistencia Staff (/local/asodeltolstaff/index.php?courseid=2)',
            url: `${BASE_URL}/local/asodeltolstaff/index.php?courseid=2`,
            cookies: adminCookies
        }
    ];

    const capturedResults = [];

    try {
        for (const view of views) {
            console.log(`\n--- Capturando vista: ${view.title} (${view.key}) ---`);

            await send('Network.clearBrowserCookies');
            if (view.cookies && view.cookies.length > 0) {
                await send('Network.setCookies', { cookies: view.cookies });
            }

            for (const vp of VIEWPORTS) {
                const filename = `${view.key}-${vp.name}.png`;
                const destPath = join(targetDir, filename);

                await send('Emulation.setDeviceMetricsOverride', {
                    width: vp.width,
                    height: vp.height,
                    deviceScaleFactor: 1,
                    mobile: vp.isMobile
                });

                await send('Page.navigate', { url: view.url });

                // Esperar a que los estilos se apliquen
                await new Promise(r => setTimeout(r, 1200));

                const overflowCheck = await send('Runtime.evaluate', {
                    expression: `(() => {
                        const vpWidth = window.innerWidth || document.documentElement.clientWidth;
                        const overflowingElements = [];
                        const allElements = document.querySelectorAll('body *');
                        for (const el of allElements) {
                            if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.tagName === 'NOSCRIPT' || el.offsetParent === null) continue;
                            if (el.closest('.drawer:not(.show), .message-drawer.hidden, [aria-hidden="true"], .modal:not(.show)')) continue;
                            const comp = window.getComputedStyle(el);
                            if (comp.visibility === 'hidden' || comp.display === 'none') continue;
                            const rect = el.getBoundingClientRect();
                            if (rect.width <= 0 || rect.height <= 0) continue;
                            if (rect.right > vpWidth + 2) {
                                // Excluir elementos legítimamente contenidos dentro de un scroll horizontal (ej. tablas responsive)
                                let isInsideScrollContainer = false;
                                let parent = el.parentElement;
                                while (parent && parent !== document.body) {
                                    const style = window.getComputedStyle(parent);
                                    if (style.overflowX === 'auto' || style.overflowX === 'scroll' || parent.classList.contains('table-responsive') || parent.classList.contains('no-overflow')) {
                                        isInsideScrollContainer = true;
                                        break;
                                    }
                                    parent = parent.parentElement;
                                }
                                if (!isInsideScrollContainer) {
                                    overflowingElements.push({
                                        tag: el.tagName.toLowerCase(),
                                        id: el.id || '',
                                        className: (el.className && typeof el.className === 'string') ? el.className.trim().substring(0, 60) : '',
                                        right: Math.round(rect.right),
                                        viewportWidth: vpWidth
                                    });
                                    if (overflowingElements.length >= 5) break;
                                }
                            }
                        }
                        const rootOverflow = document.documentElement.scrollWidth > vpWidth + 2;
                        const hasOverflow = rootOverflow || overflowingElements.length > 0;
                        return {
                            hasOverflow,
                            rootOverflow,
                            scrollWidth: document.documentElement.scrollWidth,
                            clientWidth: vpWidth,
                            overflowingElements
                        };
                    })()`,
                    returnByValue: true
                });

                const overflowResult = overflowCheck.result?.value || { hasOverflow: false, overflowingElements: [] };

                const screenshot = await send('Page.captureScreenshot', {
                    format: 'png',
                    captureBeyondViewport: false
                });

                const imageBuffer = Buffer.from(screenshot.data, 'base64');
                writeFileSync(destPath, imageBuffer);

                const statusTag = overflowResult.hasOverflow ? 'DETECTADO (ERROR)' : 'CORRECTO (0px)';
                console.log(`  ✓ Guardado: ${filename} (${vp.width}x${vp.height}) - Desbordamiento horizontal: ${statusTag}`);
                if (overflowResult.hasOverflow && overflowResult.overflowingElements?.length > 0) {
                    console.log(`    Elementos desbordados:`, JSON.stringify(overflowResult.overflowingElements));
                }

                capturedResults.push({
                    view: view.key,
                    viewport: vp.name,
                    width: vp.width,
                    height: vp.height,
                    filename,
                    path: destPath,
                    sizeBytes: imageBuffer.length,
                    hasHorizontalOverflow: overflowResult.hasOverflow,
                    overflowDetails: overflowResult.hasOverflow ? overflowResult.overflowingElements : []
                });
            }
        }
    } finally {
        ws.close();
        proc.kill('SIGTERM');
        setTimeout(() => {
            try {
                rmSync(tempProfile, { recursive: true, force: true });
            } catch (e) {}
        }, 1000);
    }

    const reportPath = join(targetDir, 'capture-manifest.json');
    writeFileSync(reportPath, JSON.stringify({
        mode,
        timestamp: new Date().toISOString(),
        total_captures: capturedResults.length,
        captures: capturedResults
    }, null, 2));

    console.log(`\n[CDP] Todas las capturas (${capturedResults.length}) se guardaron en: ${targetDir}`);
    return capturedResults;
}

// Limpiar test si existe
if (existsSync('scripts/staging/test-cdp.mjs')) {
    try { unlinkSync('scripts/staging/test-cdp.mjs'); } catch (e) {}
}

const modeArg = process.argv.find(a => a.startsWith('--mode='));
const selectedMode = modeArg ? modeArg.split('=')[1] : 'after';

captureAll(selectedMode).catch(err => {
    console.error('Error durante la captura:', err);
    process.exit(1);
});
