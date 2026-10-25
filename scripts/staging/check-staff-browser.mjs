import {spawn, execFileSync} from 'node:child_process';
import {existsSync, mkdirSync, readFileSync, writeFileSync} from 'node:fs';
import {resolve, join} from 'node:path';
import {createServer} from 'node:net';
import {createHash} from 'node:crypto';

const base = 'http://127.0.0.1:8053';
const helpOnly = process.argv.includes('--help-only');
const filesOnly = process.argv.includes('--files-only');
const learner = process.argv.includes('--learner') || filesOnly;
const example = helpOnly || filesOnly ? null : JSON.parse(execFileSync('docker', ['exec','--user','www-data',
    'asodeltol-moodle53-webserver-1','php','/opt/asodeltol/scripts/anexo14-report-context.php'],{encoding:'utf8'}));
const out = resolve('build/staging/anexo14-browser', String(Date.now()));
mkdirSync(out, {recursive: true});
const requested = process.argv.find(arg => arg.startsWith('--browser='))?.split('=')[1] || 'edge';
if (!['edge', 'chrome'].includes(requested)) throw new Error('Navegador no permitido');
const executable = (requested === 'edge' ? ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    'C:/Program Files/Microsoft/Edge/Application/msedge.exe'] :
    ['C:/Program Files/Google/Chrome/Application/chrome.exe']).find(existsSync);
if (!executable) throw new Error('Navegador no disponible: ' + requested);
const reservation = createServer();
await new Promise(resolve => reservation.listen(0,'127.0.0.1',resolve));
const port = reservation.address().port;
await new Promise(resolve => reservation.close(resolve));
const browser = spawn(executable, ['--headless=new', '--disable-gpu', '--no-first-run',
    '--remote-debugging-address=127.0.0.1', `--remote-debugging-port=${port}`,
    `--user-data-dir=${join(out, 'private-profile')}`, 'about:blank'], {stdio: 'ignore', windowsHide: true});
let ws;
let closeBrowser;
let diagnose;
let fileStep='not-started';
const checks = [];
const failures = [];
const pause = ms => new Promise(r => setTimeout(r, ms));
try {
    let target;
    for (let i = 0; i < 40 && !target; i++) {
        await pause(250);
        try {
            target = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find(t => t.type === 'page');
        } catch {}
    }
    if (!target) throw new Error('CDP no disponible');
    ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {ws.onopen = resolve; ws.onerror = reject;});
    let id = 0;
    const pending = new Map();
    ws.onclose=()=>{
        for(const task of pending.values()) task.reject(new Error('Private browser connection closed'));
        pending.clear();
    };
    ws.onmessage = ({data}) => {
        const message = JSON.parse(String(data));
        if (message.id && pending.has(message.id)) {
            const task = pending.get(message.id);
            pending.delete(message.id);
            if (message.error) task.reject(new Error(message.error.message));
            else task.resolve(message.result);
        }
        if (message.method === 'Runtime.exceptionThrown') failures.push({type: 'javascript-exception'});
        if (message.method === 'Network.responseReceived' && message.params.response.status >= 400) {
            const response = message.params.response;
            failures.push({type: 'http', status: response.status, path: new URL(response.url).pathname});
        }
        if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') failures.push({type:'console-error'});
    };
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        if(ws.readyState!==WebSocket.OPEN) {reject(new Error('Private browser connection unavailable')); return;}
        const key = ++id;
        const timer=setTimeout(()=>{pending.delete(key);reject(new Error('CDP timeout: '+method));},45000);
        pending.set(key, {resolve:value=>{clearTimeout(timer);resolve(value);},
            reject:error=>{clearTimeout(timer);reject(error);}});
        ws.send(JSON.stringify({id:key, method, params}));
    });
    closeBrowser=()=>send('Browser.close');
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
        if (result.exceptionDetails) throw new Error('Browser evaluation failed: ' +
            String(result.exceptionDetails.exception?.description || result.exceptionDetails.text).split('\n')[0]);
        return result.result.value;
    };
    diagnose=async()=>{
        if(!filesOnly) return;
        const detail=await evaluate(`({step:${JSON.stringify(fileStep)},
            controls:Array.from(document.querySelectorAll('button,input,a.fp-repo-name')).map(e=>
                ({tag:e.tagName,type:e.type,id:e.id,name:e.name,classes:e.className,text:e.innerText?.slice(0,90)})),
            notices:Array.from(document.querySelectorAll('.alert,.toast')).map(e=>e.innerText)})`);
        writeFileSync(join(out,'private-files-diagnosis.json'),JSON.stringify(detail,null,2));
        const png=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        writeFileSync(join(out,'private-files-blocked.png'),Buffer.from(png.data,'base64'));
    };
    await send('Page.enable');
    await send('Network.enable');
    await send('Runtime.enable');
    if(filesOnly) {
        mkdirSync(join(out,'downloads'),{recursive:true});
        await send('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:join(out,'downloads')});
    }
    await send('Page.navigate', {url:base + '/login/index.php'});
    let loginReady=false;
    for (let i=0; i<120; i++) {
        try {loginReady=await evaluate('Boolean(document.querySelector("#loginbtn")&&document.querySelector("#username")&&document.querySelector("#password"))');} catch {}
        if(loginReady) break;
        await pause(500);
    }
    if(!loginReady) throw new Error('Formulario de login no disponible en el navegador privado');
    let password = readFileSync(resolve('build/staging/moodle53/secrets/' + (learner?'student':'admin') + '-password.txt'), 'utf8').trim();
    await evaluate(`(() => {document.querySelector('#username').value=${JSON.stringify(learner?'af8.synthetic.g01':'asodeltol_admin')}; document.querySelector('#password').value=${JSON.stringify(password)}; document.querySelector('#loginbtn').click(); return true;})()`);
    password = '';
    let authenticated = false;
    for (let i=0; i<60; i++) {
        await pause(250);
        try {authenticated = await evaluate('Boolean(document.querySelector(\'a[href*="/login/logout.php"]\'))');} catch {}
        if (authenticated) break;
    }
    if (!authenticated) throw new Error('Login real de navegador no demostrado');
    checks.push({id:'BROWSER-LOGIN', status:'PASS'});
    for (const [name, width, height] of [['desktop',1440,900], ['tablet',768,1024], ['mobile',375,812]]) {
        await send('Emulation.setDeviceMetricsOverride', {width,height,deviceScaleFactor:1,mobile:name!=='desktop'});
        if(filesOnly) {
            fileStep=name+'-navigate';
            const waitFor=async expression=>{
                for(let i=0;i<120;i++) {
                    await pause(250);
                    try {if(await evaluate(expression)) return;} catch {}
                }
                throw new Error('Private files UI timeout');
            };
            await send('Page.navigate',{url:base+'/user/files.php'});
            await waitFor(`Boolean(document.querySelector('.filemanager:not(.fm-loading) .fp-btn-add a'))`);
            fileStep=name+'-open-picker';
            await evaluate(`document.querySelector('.filemanager .fp-btn-add a').click()`);
            await waitFor(`Boolean(document.querySelector('.fp-repo-name'))`);
            fileStep=name+'-upload-repository';
            await evaluate(`(() => {const entry=Array.from(document.querySelectorAll('.fp-repo-name'))
                .find(e=>/subir|upload/i.test(e.innerText)); if(!entry) throw new Error('Upload repository unavailable');
                entry.closest('a,[role=button],[role=tab]')?.click();})()`);
            await waitFor(`Boolean(document.querySelector('.fp-upload-form input[type=file]'))`);
            fileStep=name+'-set-input';
            const filename='asodeltol-synthetic-private-'+name+'-'+Date.now()+'.png';
            const bytes=readFileSync(resolve('build/staging/moodle53/artifacts/anexo14-files/synthetic.png'));
            const fixture=join(out,filename);
            writeFileSync(fixture,bytes);
            const input=await send('Runtime.evaluate',{expression:`document.querySelector('.fp-upload-form input[type=file]')`});
            await send('DOM.setFileInputFiles',{objectId:input.result.objectId,files:[fixture]});
            fileStep=name+'-upload';
            await evaluate(`document.querySelector('.fp-upload-form .fp-upload-btn').click()`);
            await waitFor(`Boolean(document.querySelector('.filemanager')?.innerText.includes(${JSON.stringify(filename)}))`);
            fileStep=name+'-save';
            await evaluate(`document.querySelector('#userfilesform input[name=submitbutton]').click()`);
            await waitFor(`Boolean(document.querySelector('.alert-success,.toast'))`);
            fileStep=name+'-reload';
            await send('Page.navigate',{url:base+'/user/files.php'});
            await waitFor(`Boolean(document.querySelector('.filemanager:not(.fm-loading)')?.innerText.includes(${JSON.stringify(filename)}))`);
            const downloaded=await evaluate(`(async()=>{
                const url='/pluginfile.php/'+M.cfg.contextid+'/user/private/'+${JSON.stringify(filename)}+'?forcedownload=1';
                const response=await fetch(url); const data=await response.arrayBuffer();
                const hash=await crypto.subtle.digest('SHA-256',data);
                return {status:response.status,bytes:data.byteLength,
                    sha256:Array.from(new Uint8Array(hash)).map(x=>x.toString(16).padStart(2,'0')).join(''),
                    width:innerWidth,scrollWidth:document.documentElement.scrollWidth};})()`);
            fileStep=name+'-native-download';
            await evaluate(`(() => {const file=Array.from(document.querySelectorAll('.fp-filename'))
                .find(e=>e.innerText.trim()===${JSON.stringify(filename)});
                if(!file) throw new Error('Persisted file absent in manager'); file.click();})()`);
            await waitFor(`Array.from(document.querySelectorAll('.fp-file-download')).some(e=>e.getBoundingClientRect().height>0)`);
            await evaluate(`Array.from(document.querySelectorAll('.fp-file-download')).find(e=>e.getBoundingClientRect().height>0).click()`);
            const nativePath=join(out,'downloads',filename);
            for(let i=0;i<120&&!existsSync(nativePath);i++) await pause(250);
            const nativeHash=existsSync(nativePath)?createHash('sha256').update(readFileSync(nativePath)).digest('hex'):null;
            const png=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
            writeFileSync(join(out,'private-files-'+name+'.png'),Buffer.from(png.data,'base64'));
            checks.push({...downloaded,id:'PRIVATE-FILES-'+name,status:downloaded.status===200&&downloaded.bytes===bytes.length
                &&downloaded.sha256===createHash('sha256').update(bytes).digest('hex')
                &&nativeHash===downloaded.sha256&&downloaded.scrollWidth<=downloaded.width+2?'PASS':'FAIL',filename,httpStatus:downloaded.status,
                persistedAfterReload:true,nativeUploadPicker:true,nativeDownloadClick:true,nativeDownloadSha256:nativeHash});
            continue;
        }
        if(helpOnly) {
            await send('Page.navigate',{url:base+'/my/'});
            await pause(1500);
            let linked=false;
            for(let i=0;i<40;i++) {
                linked=await evaluate(`Boolean(document.querySelector('a[href*="/local/asodeltolstaff/help.php"]'))`);
                if(linked) break;
                await pause(250);
            }
            if(!linked) throw new Error('Falta enlace nativo a la ayuda');
            const visibleExpression=`(() => Array.from(document.querySelectorAll('a[href*="/local/asodeltolstaff/help.php"]')).find(a=>a.getBoundingClientRect().width>0&&a.getBoundingClientRect().height>0))()`;
            let visible=await evaluate('Boolean('+visibleExpression+')');
            if(!visible) {
                for(let i=0;i<40&&!visible;i++) {
                    await evaluate(`(() => {const drawer=document.querySelector('#theme_boost-drawers-primary');
                        if(drawer&&!drawer.classList.contains('show')) document.querySelector('button[data-target="theme_boost-drawers-primary"]')?.click();})()`);
                    await pause(500);
                    visible=await evaluate('Boolean('+visibleExpression+')');
                }
            }
            if(!visible) {
                const diagnosis=await evaluate(`({drawer:document.querySelector('#theme_boost-drawers-primary')?.className,
                    links:Array.from(document.querySelectorAll('#theme_boost-drawers-primary a')).map(a=>new URL(a.href).pathname),
                    ready:document.readyState})`);
                writeFileSync(join(out,'help-menu-diagnosis.json'),JSON.stringify(diagnosis,null,2));
                const png=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                writeFileSync(join(out,'help-menu-blocked.png'),Buffer.from(png.data,'base64'));
                throw new Error('El enlace de ayuda no es visible al abrir la navegación');
            }
            await evaluate(visibleExpression+'.click()');
            let guide=false;
            for(let i=0;i<60;i++) {
                await pause(250);
                guide=await evaluate('Boolean(document.querySelector("#asodeltol-user-guide"))');
                if(guide) break;
            }
            const detail=await evaluate(`({length:document.querySelector('#asodeltol-user-guide')?.innerText.length||0,
                sections:document.querySelectorAll('#asodeltol-user-guide h3').length,
                width:innerWidth,scrollWidth:document.documentElement.scrollWidth})`);
            const png=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
            writeFileSync(join(out,'help-'+name+'.png'),Buffer.from(png.data,'base64'));
            checks.push({id:'HELP-'+name,status:guide&&detail.length>400&&detail.sections===5
                &&detail.scrollWidth<=detail.width+2?'PASS':'FAIL',visibleLink:visible,...detail});
            continue;
        }
        await send('Page.navigate', {url:base + '/local/asodeltolstaff/index.php?courseid=2'});
        let loaded = false;
        for (let i=0; i<80; i++) {
            await pause(250);
            loaded = await evaluate('Boolean(document.body?.innerText.includes("M11-AF8-G01-01") && document.body?.innerText.includes("M11-AF8-G02-01"))');
            if (loaded) break;
        }
        const metrics = await evaluate('({width:innerWidth,scrollWidth:document.documentElement.scrollWidth})');
        const png = await send('Page.captureScreenshot', {format:'png',captureBeyondViewport:false});
        writeFileSync(join(out, `${name}.png`), Buffer.from(png.data,'base64'));
        checks.push({id:'STAFF-'+name, status:loaded && metrics.scrollWidth<=metrics.width+2?'PASS':'FAIL', ...metrics});
        await evaluate(`(() => { const button=document.querySelector('form[action*="reports.php"] button');
            if(!button) throw new Error('Falta acceso a reportes'); button.click(); return true; })()`);
        let reportsLoaded = false;
        for(let i=0;i<60;i++) {
            await pause(250);
            try {reportsLoaded=await evaluate('Boolean(document.querySelector("#id_dataset") && document.querySelector("#id_format"))');} catch {}
            if(reportsLoaded) break;
        }
        if(!reportsLoaded) throw new Error('La navegación al formulario de reportes no terminó');
        const form = await evaluate(`(() => {
            const dataset=document.querySelector('#id_dataset'); dataset.value='consolidated';
            const format=document.querySelector('#id_format'); format.value='csv';
            return {dataset:dataset.value,format:format.value,action:new URL(dataset.form.action).pathname,
                width:innerWidth,scrollWidth:document.documentElement.scrollWidth}; })()`);
        const reportPng = await send('Page.captureScreenshot', {format:'png',captureBeyondViewport:false});
        writeFileSync(join(out, `reports-${name}.png`),Buffer.from(reportPng.data,'base64'));
        checks.push({id:'REPORTS-'+name,status:form.dataset==='consolidated' && form.format==='csv'
            && form.action==='/local/asodeltolstaff/export.php' && form.scrollWidth<=form.width+2?'PASS':'FAIL',...form});
        await send('Page.navigate',{url:base+'/local/asodeltolstaff/officehours.php?courseid=2'});
        let officeReady=false;
        for(let i=0;i<80;i++) {
            await pause(250);
            try {officeReady=await evaluate('Boolean(document.querySelector("select[name=groupid]")&&document.querySelector("select[name=trainerid]")&&document.querySelector("input[name=sesskey]"))');} catch {}
            if(officeReady) break;
        }
        const office=await evaluate(`(() => {
            const date=(prefix)=>new Date(...['year','month','day','hour','minute'].map((part,index)=>{
                const value=Number(document.querySelector('select[name="'+prefix+'['+part+']"]')?.value);
                return index===1?value-1:value;
            })).getTime();
            return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
                groups:document.querySelector('select[name=groupid]')?.options.length||0,
                trainers:document.querySelector('select[name=trainerid]')?.options.length||0,
                defaultMinutes:(date('end')-date('start'))/60000}; })()`);
        const officePng=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        writeFileSync(join(out,'officehours-'+name+'.png'),Buffer.from(officePng.data,'base64'));
        checks.push({id:'OFFICEHOURS-'+name,status:officeReady&&office.groups>0&&office.trainers>0
            &&office.defaultMinutes>=60&&office.scrollWidth<=office.width+2?'PASS':'FAIL',...office});
        await send('Page.navigate',{url:base+'/local/asodeltolstaff/session.php?courseid=2&id='+example.sample_session_id});
        let editReady=false;
        for(let i=0;i<80;i++) {
            await pause(250);
            try {editReady=await evaluate(`Boolean(document.querySelector('select[name="scheduledstart[year]"]'))`);} catch {}
            if(editReady) break;
        }
        const edit=await evaluate(`(() => {
            const actual={};
            for(const field of ['scheduledstart','scheduledend','reportedstart','reportedend']) {
                const enabled=document.querySelector('input[name="'+field+'[enabled]"]');
                actual[field]=enabled&&!enabled.checked?null:Object.fromEntries(['year','month','day','hour','minute'].map(part=>
                    [part,Number(document.querySelector('select[name="'+field+'['+part+']"]')?.value)]));
            }
            return {actual,width:innerWidth,scrollWidth:document.documentElement.scrollWidth}; })()`);
        const same=JSON.stringify(edit.actual)===JSON.stringify(example.sample_session_display_dates);
        const editPng=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        writeFileSync(join(out,'session-edit-'+name+'.png'),Buffer.from(editPng.data,'base64'));
        checks.push({id:'SESSION-EDIT-'+name,status:editReady&&same&&edit.scrollWidth<=edit.width+2?'PASS':'FAIL',
            savedDatesPreserved:same,width:edit.width,scrollWidth:edit.scrollWidth});
    }
    checks.push({id:'CONSOLE-NETWORK',status:failures.length?'FAIL':'PASS',failures});
} catch (error) {
    await diagnose?.().catch(()=>{});
    checks.push({id:'EXECUTION',status:'BLOCKED',detail:error.message});
} finally {
    await Promise.race([closeBrowser?.().catch(()=>{}),pause(1000)]);
    ws?.close();
    browser.kill();
    const report = {environment:base,browser:requested==='edge'?'Microsoft Edge Chromium':'Google Chrome',checks,
        actor:learner?'af8.synthetic.g01':'asodeltol_admin',
        observed_errors:failures,
        scope:filesOnly?'Archivos privados: carga con selector nativo y guardado real, recarga y descarga por bytes/hash en navegador; tamaños emulados, no hardware móvil.':
            helpOnly?'Ayuda: beneficiario autenticado, enlace visible en navegación y contenido real.':
            'Admin sintético, login, listado staff y navegación real al selector de reportes; no acredita otros sistemas operativos, Safari/Firefox ni todo el curso.'};
    writeFileSync(join(out,'summary.json'),JSON.stringify(report,null,2));
    console.log(JSON.stringify(report));
    process.exitCode=checks.some(c=>c.status!=='PASS')?1:0;
}
