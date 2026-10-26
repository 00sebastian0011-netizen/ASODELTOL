/** Safe authenticated GET allowlist: no logout, attempts, edits, sesskeys or mutation endpoints. */
import {chromium} from 'playwright';import fs from 'node:fs';import assert from 'node:assert/strict';
const out=new URL('../../../build/staging/close19/',import.meta.url),read=n=>fs.readFileSync(new URL('private/'+n,out),'utf8').replace(/^\uFEFF/,'').trim(),f=JSON.parse(read('fixture.json')),password=read('admin-password.txt');
const browser=await chromium.launch({headless:true}),context=await browser.newContext({baseURL:'http://127.0.0.1:8054'}),page=await context.newPage(),rows=[],discovery=[],menu=[];
try{
 await page.goto('http://127.0.0.1:8054/login/index.php');await page.locator('#username').fill('qa_admin');await page.locator('#password').fill(password);await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
 const urls=new Set(['/?redirect=0',`/course/view.php?id=${f.courseid}`]);
 for(const viewport of [{width:1440,height:900},{width:390,height:844}]){
  await page.setViewportSize(viewport);await page.goto(`http://127.0.0.1:8054/course/view.php?id=${f.courseid}`);await page.waitForLoadState('networkidle');
  discovery.push({viewport,buttons:await page.locator('header button,nav button,nav a[role=button]').evaluateAll(es=>es.map(e=>({text:e.getAttribute('aria-label')||e.innerText,controls:e.getAttribute('aria-controls'),action:e.dataset.action,target:e.dataset.target,visible:!!e.getClientRects().length})))});
  const toggle=page.locator('.primary-navigation [data-bs-toggle="dropdown"]:visible').first();
  if(await toggle.count()){await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'true');menu.push({viewport,action:'native More dropdown opened'});await toggle.click();}
  if(viewport.width<992){
   const button=page.locator('button[data-target="theme_boost-drawers-primary"]:visible');await button.click();
   const drawer=page.locator('#theme_boost-drawers-primary.show');await drawer.waitFor();
   const reports=drawer.locator('a[href*="/local/asodeltolstaff/reports.php"]');assert.ok(await reports.isVisible());
   menu.push({viewport,action:'native mobile drawer opened; authorized reports link visible'});
   await drawer.locator('[data-action="closedrawer"]').click();await page.locator('#theme_boost-drawers-primary.show').waitFor({state:'hidden'});
   menu.push({viewport,action:'native mobile drawer closed'});
  }
  for(const href of await page.locator('nav a[href],.activity a[href],.course-content a[href]').evaluateAll(es=>es.map(e=>e.href))){
   const u=new URL(href);if(u.origin!=='http://127.0.0.1:8054'||u.searchParams.has('sesskey'))continue;
   if(/^\/(?:$|my\/$|my\/courses.php$|course\/(?:view|section).php$|grade\/report\/(?:user|grader)\/index.php$|local\/asodeltolstaff\/(?:help|support|reports|index).php$|mod\/(?:page|quiz|glossary|assign|forum|feedback|resource|url)\/view.php$)/.test(u.pathname)){if(u.pathname==='/mod/url/view.php')u.searchParams.set('redirect','0');urls.add(u.pathname+u.search);}
  }
 }
 for(const item of f.catalog_mapping)urls.add(`/mod/${item.kind}/view.php?id=${item.cmid}`+(item.kind==='url'?'&redirect=0':''));
 for(const url of urls){try{const r=await context.request.get(url,{timeout:60000});const html=(r.headers()['content-type']||'').includes('text/html')?await r.text():'';const ok=r.status()===200&&!html.includes('class="errorcode"')&&!html.includes('id="loginbtn"');rows.push({url,status:ok?'PASS':'FAIL',http:r.status(),bytes:(await r.body()).length});}catch(e){rows.push({url,status:'FAIL',error:String(e.message).split(password).join('[REDACTED]')});}}
}finally{await browser.close();fs.writeFileSync(new URL('links.json',out),JSON.stringify({status:rows.length&&rows.every(r=>r.status==='PASS')?'PASS':'FAIL',rows,menu,discovery,scope:'safe native course/menu routes of synthetic fixture only; external links and mutation routes excluded'},null,2));console.log('LINKS',rows.length,'FAIL',rows.filter(r=>r.status!=='PASS').length);}
