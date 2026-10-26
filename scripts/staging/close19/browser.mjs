/** Synthetic-only real browser checks. No storage state, HTML dumps, or credentials are exported. */
import {chromium, firefox, webkit} from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const out = path.join(root, 'build/staging/close19');
const f = JSON.parse(fs.readFileSync(path.join(out, 'private/fixture.json'), 'utf8').replace(/^\uFEFF/, ''));
const password = fs.readFileSync(path.join(out, 'private/synthetic-password.txt'), 'utf8').trim();
const adminPassword = fs.readFileSync(path.join(out, 'private/admin-password.txt'), 'utf8').trim();
const base = 'http://127.0.0.1:8054';
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const dir = path.join(out, `browser-${stamp}`); fs.mkdirSync(dir, {recursive: true});
const results = [], issues = [], expectedDenials = [], engines = [];
const clean = e => String(e?.stack || e).split(password).join('[REDACTED]').split(adminPassword).join('[REDACTED]').replace(/((?:sesskey|logintoken|token|password)=)[^&\s"<>]+/gi,'$1[REDACTED]');
async function test(name, run, details = {}) {
  const t = performance.now();
  try { const observed = await run(); results.push({name, status: 'PASS', seconds: (performance.now()-t)/1000, ...details, observed}); }
  catch(e) { results.push({name, status: e.qaBlocked?'BLOCKED':'FAIL', seconds: (performance.now()-t)/1000, ...details, error: clean(e),diagnostic:e.diagnostic}); console.log(clean(e).slice(0,400)); }
  console.log(name, results.at(-1).status);
}
function contextSnapshot(action='snapshot') {
  if(process.env.CLOSE19_BROWSER_ONLY==='1') {
    assert.ok(['snapshot','allow-synthetic-trainer-messages'].includes(action),'Remote browser fixture cannot mutate runtime configuration');
    const snapshot=JSON.parse(fs.readFileSync(path.join(out,'context-final.json'),'utf8').replace(/^\uFEFF/,''));
    assert.equal(snapshot.plugin_version,'2026102506');assert.equal(snapshot.theme_version,'2026102500');
    if(action==='allow-synthetic-trainer-messages')assert.equal(snapshot.synthetic_contacts_ready,true);
    return snapshot;
  }
  return JSON.parse(execFileSync('docker', ['exec','--user','www-data','asodeltol-close19-webserver-1','php','/opt/close19/context.php',action],{encoding:'utf8'}));
}
async function actor(browser, user, viewport={width:1440,height:900}) {
  const context = await browser.newContext({baseURL:base, viewport, acceptDownloads:true});
  const page = await context.newPage(); page.setDefaultTimeout(20000);
  page.on('pageerror', e => issues.push({kind:'pageerror', url:page.url(), error:clean(e)}));
  page.on('console', m => { if(m.type()==='error') (page.qaDenial ? expectedDenials : issues).push({kind:'console',url:page.url(),error:clean(m.text())}); });
  page.on('response', r => { if(r.status()>=400) (page.qaDenial ? expectedDenials : issues).push({kind:'http',url:clean(r.url()),status:r.status()}); });
  await page.goto('/login/index.php',{waitUntil:'networkidle'}); await page.locator('#username').fill(user.username);
  await page.locator('#password').fill(user.admin ? adminPassword : password);
  await Promise.all([page.waitForURL(url=>!url.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
  assert.equal(await page.locator('#loginbtn').count(),0);
  await page.waitForLoadState('networkidle');
  return {context,page};
}
async function goto(page, url) {
  const response=await page.goto(url,{waitUntil:'load'}); assert.equal(response.status(),200);
  await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('.errorcode').count(),0); assert.equal(await page.locator('#loginbtn').count(),0);
}
async function submit(page) {
  await Promise.all([page.waitForNavigation({waitUntil:'load'}), page.locator('#id_submitbutton').click()]);
  await page.waitForLoadState('networkidle');
  await page.waitForFunction(()=>!window.M?.util?.pending_js || window.M.util.pending_js.length===0);
}
async function editor(page, name, text) {
  const textarea=page.locator(`textarea[name="${name}[text]"]`);
  await page.waitForLoadState('networkidle');
  await page.waitForFunction(()=>!window.M?.util?.pending_js || window.M.util.pending_js.length===0);
  if(await textarea.isVisible()) await textarea.fill(text);
  else await page.frameLocator(`iframe[id="${await textarea.getAttribute('id')}_ifr"]`).locator('body').fill(text);
}
async function upload(page, opener, filename) {
  await opener.click(); const dialog=page.locator('.moodle-dialogue:visible').last();
  await dialog.getByText('Upload a file',{exact:true}).click();
  await dialog.locator('input[type=file]').setInputFiles(path.join(out,'private',filename));
  await dialog.locator('.fp-upload-btn').click();
  await dialog.waitFor({state:'hidden'});
}
async function denied(page,url) {
  page.qaDenial=true;
  try { const response=await page.goto(url); assert.ok(response.status()===403 || await page.locator('.errorcode').count()>0); }
  finally { await page.waitForLoadState('networkidle');page.qaDenial=false; }
}
try {
  assert.equal(contextSnapshot().plugin_version,'2026102506');
  const selected=process.argv[2] || 'all';
  const specs=[['chromium',chromium,{}],['edge',chromium,{channel:'msedge'}],['firefox',firefox,{}],['webkit',webkit,{}]];
  for(const [name,type,options] of specs) {
    if(selected==='supported-windows' && name==='webkit')continue;
    if(selected!=='all' && selected!=='probe' && selected!=='supported-windows' && selected!==name) continue;
    if(selected==='probe' && name!=='chromium') continue;
    let browser;
    try {
      browser=await type.launch({headless:true,...options}); engines.push({name,version:browser.version(),hostOS:process.platform,realDevice:false});
      const sizes=selected==='probe' ? [['desktop',{width:1440,height:900}]] : [['desktop',{width:1440,height:900}],['tablet',{width:768,height:1024}],['mobile',{width:390,height:844}]];
      for(const [size,viewport] of sizes) {
        let a;
        await test(`${name}/${size}/login-navigation`,async()=>{
          a=await actor(browser,f.users[engines.length-1+(process.platform==='linux'?5:0)],viewport); const {page}=a;
          const routes=['/?redirect=0',`/course/view.php?id=${f.courseid}`,`/course/section.php?id=${f.sectionid}`,
            `/mod/page/view.php?id=${f.modules.C01.cmid}`,`/mod/assign/view.php?id=${f.assignment}`,
            `/mod/quiz/view.php?id=${f.modules.C09.cmid}`,`/grade/report/user/index.php?id=${f.courseid}`,
            '/local/asodeltolstaff/help.php','/local/asodeltolstaff/support.php'];
          const timings=[];
          for(const url of routes) { const t=performance.now(); await goto(page,url); timings.push({url,seconds:(performance.now()-t)/1000});
            assert.ok(timings.at(-1).seconds<60); const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);
            assert.equal(overflow,false,`Horizontal overflow: ${url}`);
          }
          await page.screenshot({path:path.join(dir,`${name}-${size}.png`),fullPage:true});
          return timings;
        });
        if(a) {
          await test(`${name}/${size}/media-playback`,async()=>{
            await goto(a.page,`/mod/page/view.php?id=${f.media.cmid}`);
            await a.page.waitForLoadState('networkidle');
            for(const id of ['qa19-video','qa19-audio']) {
              const tag=id.endsWith('video') ? 'video' : 'audio';
              try{await a.page.waitForFunction(tag=>document.querySelector(tag)?.readyState>=2,tag,{timeout:15000});}
              catch(error){error.diagnostic=await a.page.locator(tag).first().evaluate(e=>({tag:e.tagName,error:e.error?{code:e.error.code,message:e.error.message}:null,networkState:e.networkState,readyState:e.readyState,mp4:e.canPlayType('video/mp4; codecs="avc1.42E01E, mp4a.40.2"'),mp3:e.canPlayType('audio/mpeg')}));error.qaBlocked=name==='webkit'&&error.diagnostic.mp4==='';throw error;}
              await a.page.locator(tag).first().evaluate(async el=>{el.muted=true; await el.play();});
              await a.page.waitForFunction(tag=>document.querySelector(tag)?.currentTime>0.5,tag,{timeout:15000});
            }
            return a.page.locator('video,audio').evaluateAll(es=>es.map(e=>({tag:e.tagName,time:e.currentTime,readyState:e.readyState})));
          });
          await test(`${name}/${size}/student-denied`,async()=>{
            for(const url of [`/local/asodeltolstaff/reports.php?courseid=${f.courseid}`,`/local/asodeltolstaff/publish.php?courseid=${f.courseid}`,`/course/edit.php?id=${f.courseid}`]) await denied(a.page,url);
          });
          await test(`${name}/${size}/submission-and-message`,async()=>{
            const marker=`QA19 ${name} ${size} ${stamp}`;
            await goto(a.page,`/mod/assign/view.php?id=${f.assignment}`);
            await a.page.getByRole('button',{name:/^(Add|Edit) submission$/}).click();
            await editor(a.page,'onlinetext_editor',marker);
            const add=a.page.locator('.filemanager .fp-btn-add a');
            const manager=a.page.locator('.filemanager[id^="filemanager-"].fm-loaded').first();await manager.waitFor();
            if(!(await manager.getAttribute('class')).includes('fm-maxfiles')) await upload(a.page,add,'material.pdf');
            await submit(a.page); await goto(a.page,`/mod/assign/view.php?id=${f.assignment}`);
            assert.ok((await a.page.locator('#region-main').innerText()).includes(marker));
            assert.ok((await a.page.locator('#region-main').innerText()).includes('material.pdf'));
            contextSnapshot('allow-synthetic-trainer-messages');
            await goto(a.page,`/message/index.php?id=${f.trainer.id}`);
            const txt=a.page.locator('[data-region="send-message-txt"]:visible');
            await txt.fill(marker); await a.page.locator('[data-action="send-message"]:visible').click();
            await a.page.getByText(marker,{exact:true}).filter({visible:true}).first().waitFor();
            return {marker,submissionPersisted:true,messageSent:true};
          });
          if(selected==='probe') {
            await goto(a.page,`/mod/assign/view.php?id=${f.assignment}`);
            // Probe only structural form metadata, never field values or HTML.
            await a.page.getByRole('button',{name:'Add submission',exact:true}).click();
            console.log('SUBMISSION_INPUTS',await a.page.locator('textarea,iframe,input:not([type=hidden]),a.fp-icon').evaluateAll(es=>es.map(e=>({tag:e.tagName,id:e.id,type:e.type,name:e.name,region:e.dataset.region,action:e.dataset.action,text:e.textContent.slice(0,70)}))));
          }
          await a.page.waitForLoadState('networkidle');await a.context.close();
        }
      }
      if(name==='chromium') {
        const recent=new Set(contextSnapshot().cases.filter(c=>Number(c.timecreated)>Date.now()/1000-65).map(c=>Number(c.requesterid)));
        const supportUser=f.users.find((u,i)=>i>=10&&!recent.has(u.id));assert.ok(supportUser,'No fresh synthetic requester available');
        const learner=await actor(browser,supportUser), admin=await actor(browser,{username:'qa_admin',admin:true}), outsider=await actor(browser,f.none);
        try {
          await test('support/request-reply-resolution-private',async()=>{
            await goto(learner.page,'/local/asodeltolstaff/support.php');
            await learner.page.locator('#id_subject').fill('QA19 support '+stamp);
            await learner.page.locator('#id_body').fill('Synthetic request; no personal data.'); await submit(learner.page);
            await learner.page.waitForURL(/caseid=\d+/); const caseid=Number(new URL(learner.page.url()).searchParams.get('caseid'));
            assert.ok(caseid>0); await denied(outsider.page,`/local/asodeltolstaff/support.php?caseid=${caseid}`);
            await goto(admin.page,`/local/asodeltolstaff/support.php?caseid=${caseid}`);
            await admin.page.locator('#id_response').fill('Synthetic QA response and resolution.'); await admin.page.locator('#id_resolved').check(); await submit(admin.page);
            await goto(learner.page,`/local/asodeltolstaff/support.php?caseid=${caseid}`);
            assert.match(await learner.page.locator('#support-case-status').innerText(),/resolved/i);
            assert.ok((await learner.page.locator('.support-case-event').innerText()).includes('Synthetic QA response'));
            const state=contextSnapshot(), c=state.cases.find(c=>Number(c.id)===caseid);
            assert.equal(c.status,'resolved');
            return {caseid,resolutionSeconds:c.timemodified-c.timecreated,approvedSLA:false,notification:state.notifications.filter(n=>n.eventtype==='supportreply' && Number(n.useridto)===supportUser.id && n.contexturl.endsWith('caseid='+caseid)).length};
          });
          await test('admin-unenrolled-reports',async()=>{await goto(admin.page,`/local/asodeltolstaff/reports.php?courseid=${f.courseid}`);return {heading:await admin.page.locator('h2').allTextContents(),links:await admin.page.locator('a[href*="export"]').evaluateAll(es=>es.map(e=>e.getAttribute('href')))};});
          await test('admin-downloads-and-student-denials',async()=>{
            const sizes=[];
            for(const dataset of ['participants','consolidated','sessions','access']) {
              for(const format of ['csv','excel']) {
                const url=`/local/asodeltolstaff/export.php?courseid=${f.courseid}&dataset=${dataset}&format=${format}`;
                const response=await admin.context.request.get(url); assert.equal(response.status(),200);
                const bytes=await response.body(); assert.ok(bytes.length>30); assert.ok(!response.headers()['content-type'].includes('text/html'));
                if(format==='excel') assert.equal(bytes.subarray(0,2).toString(),'PK');
                sizes.push({dataset,format,bytes:bytes.length});
                await denied(learner.page,url);
              }
            }
            return sizes;
          });
          await test('axe-support-local',async()=>{await goto(learner.page,'/local/asodeltolstaff/support.php');const r=await new AxeBuilder({page:learner.page}).analyze();fs.writeFileSync(path.join(dir,'axe.json'),JSON.stringify({violations:r.violations,incomplete:r.incomplete,passes:r.passes.length},null,2));return {violations:r.violations.length,scope:'this page only; no WCAG certification'};});
        } finally {await learner.context.close();await admin.context.close();await outsider.context.close();}
      }
    } catch(e) {results.push({name:`${name}/setup`,status:'FAIL',error:clean(e)});}
    finally {await browser?.close();}
  }
} finally {
  const report={base,pluginVersion:'0.5.0 (2026102506)',syntheticOnly:true,engines,results,issues,expectedDenials,
    counts:{pass:results.filter(r=>r.status==='PASS').length,fail:results.filter(r=>r.status==='FAIL').length,blocked:results.filter(r=>r.status==='BLOCKED').length},
    platformLimitation:'Windows host engines and viewport emulation; not proof of three real operating systems'};
  fs.writeFileSync(path.join(dir,'results.json'),JSON.stringify(report,null,2)); console.log('EVIDENCE',dir,report.counts);
  process.exitCode=report.counts.fail ? 1 : 0;
}
