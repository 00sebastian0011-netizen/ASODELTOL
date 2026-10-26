/** Real native-form journeys on the guarded synthetic fixture. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const out=new URL('../../../build/staging/close19/',import.meta.url);
const read=n=>fs.readFileSync(new URL('private/'+n,out),'utf8').replace(/^\uFEFF/,'').trim();
const f=JSON.parse(read('fixture.json')), password=read('synthetic-password.txt'), adminPassword=read('admin-password.txt');
const stamp=new Date().toISOString().replace(/[:.]/g,'-'), rows=[], issues=[], deniedRequests=[];
const dir=new URL(`workflows-${stamp}/`,out);fs.mkdirSync(dir,{recursive:true});
const clean=e=>String(e.stack||e).split(password).join('[REDACTED]').split(adminPassword).join('[REDACTED]');
const state=(action='snapshot')=>JSON.parse(execFileSync('docker',['exec','--user','www-data','asodeltol-close19-webserver-1','php','/opt/close19/context.php',action],{encoding:'utf8'}));
async function test(name,fn){if(process.argv[2]&&!name.startsWith(process.argv[2]))return;try{const observed=await fn();rows.push({name,status:'PASS',observed});}catch(e){rows.push({name,status:'FAIL',error:clean(e)});console.log(clean(e).slice(0,500));}console.log(name,rows.at(-1).status);}
const browser=await chromium.launch({headless:true});
async function login(user,admin=false){const context=await browser.newContext({baseURL:'http://127.0.0.1:8054',viewport:{width:1440,height:900}}),page=await context.newPage();
  page.setDefaultTimeout(20000);page.on('pageerror',e=>issues.push({kind:'pageerror',error:clean(e)}));
  page.on('response',r=>{if(r.status()>=400)(page.denial?deniedRequests:issues).push({url:r.url(),status:r.status()});});
  await page.goto('/login/index.php');await page.locator('#username').fill(user.username);await page.locator('#password').fill(admin?adminPassword:password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);return {context,page};}
async function go(page,url){const r=await page.goto(url);assert.equal(r.status(),200);await page.waitForLoadState('networkidle');assert.equal(await page.locator('.errorcode').count(),0,(await page.locator('body').innerText()).slice(0,1200));}
async function save(page,selector='#id_submitbutton'){try{await Promise.all([page.waitForNavigation(),page.locator(selector).click()]);}catch(e){await page.screenshot({path:fileURLToPath(new URL('form-failure.png',dir)),fullPage:true});const invalid=await page.locator('input:invalid,select:invalid,textarea:invalid').evaluateAll(es=>es.map(e=>({name:e.name,id:e.id,message:e.validationMessage})));throw new Error(clean(e)+' Form feedback: '+(await page.locator('.invalid-feedback:visible, .error:visible, [id^="id_error_"]:visible').allTextContents()).join(' | ')+' Invalid controls: '+JSON.stringify(invalid));}}
async function deny(page,url){page.denial=true;try{const r=await page.goto(url);const redirected=new URL(page.url()).pathname==='/course/view.php'&&new URL(url,'http://127.0.0.1:8054').pathname!=='/course/view.php';assert.ok(r.status()===403||await page.locator('.errorcode').count()>0||redirected,`${r.status()} ${page.url()} ${(await page.locator('#region-main').innerText()).slice(0,350)}`);return {status:r.status(),redirected};}finally{page.denial=false;}}
async function editor(page,name,text){const el=page.locator(`textarea[name="${name}[text]"]`);await page.waitForTimeout(750);if(await el.isVisible())await el.fill(text);else await page.frameLocator(`iframe[id="${await el.getAttribute('id')}_ifr"]`).locator('body').fill(text);}
async function date(page,name,d){await page.locator(`#id_${name}_enabled`).check();for(const [field,value]of Object.entries({day:d.getUTCDate(),month:d.getUTCMonth()+1,year:d.getUTCFullYear(),hour:d.getUTCHours(),minute:d.getUTCMinutes()}))await page.locator(`[name="${name}[${field}]"]`).selectOption(String(value));}
async function upload(page,name,file){await page.locator(`#fitem_id_${name} .fp-btn-choose`).click();const d=page.locator('.moodle-dialogue:visible').last();await d.getByText('Upload a file',{exact:true}).click();await d.locator('input[type=file]').setInputFiles(fileURLToPath(new URL('private/'+file,out)));await d.locator('.fp-upload-btn').click();await d.waitFor({state:'hidden'});}
let trainer,learner,other,admin;
try{
  assert.equal(state().plugin_version,'2026102506');
  trainer=await login(f.trainer);learner=await login(f.users[0]);other=await login(f.other);admin=await login({username:'qa_admin'},true);
  await test('native-evaluation-tools-and-group-gradebook',async()=>{
    const tools=[['assign',f.assignment],['quiz',f.modules.C09.cmid],['forum',f.forum],['feedback',f.feedback]];
    for(const [kind,id]of tools)await go(learner.page,`/mod/${kind}/view.php?id=${id}`);
    await go(learner.page,`/grade/report/user/index.php?id=${f.courseid}`);
    const url=`/grade/report/grader/index.php?id=${f.courseid}&group=${f.groups[0]}`;await go(trainer.page,url);
    await trainer.page.locator('#region-main').getByText(/learner01/).first().waitFor();const text=await trainer.page.locator('#region-main').innerText();assert.ok(text.includes('learner01'));assert.ok(!text.includes('othergroup'));
    await deny(learner.page,url);
    const matrix={courseid:f.courseid,groupid:f.groups[0],tools:tools.map(([kind,id])=>({kind,cmid:id,url:`/mod/${kind}/view.php?id=${id}`})),gradebook:url,
      synthetic:true,officialEvaluationRules:null,approval:'NOT ACCREDITED',gradeItems:state().grade_items,otherGroupExcludedFromGrader:true};
    fs.writeFileSync(new URL('native-evaluation-matrix.json',out),JSON.stringify(matrix,null,2));return matrix;
  });
  await test('trainer-announcement-and-group-notifications',async()=>{
    const title='QA19 announcement '+stamp;
    await go(trainer.page,`/local/asodeltolstaff/communication.php?courseid=${f.courseid}`);
    assert.deepEqual(await trainer.page.locator('#id_groupid option').evaluateAll(es=>es.map(e=>Number(e.value))),[f.groups[0]]);
    await trainer.page.locator('#id_title').fill(title);await trainer.page.locator('#id_body').fill('Synthetic announcement body.');await save(trainer.page);
    const s=state(),notices=s.notifications.filter(n=>n.eventtype==='groupannouncement'&&Number(n.useridfrom)===f.trainer.id);
    const url=notices.at(-1).contexturl,batch=notices.filter(n=>n.contexturl===url);
    assert.equal(batch.length,50);assert.deepEqual([...new Set(batch.map(n=>Number(n.useridto)))].sort((a,b)=>a-b),f.users.map(u=>u.id).sort((a,b)=>a-b));
    await go(learner.page,url);assert.ok((await learner.page.locator('body').innerText()).includes(title));await deny(other.page,url);
    await go(learner.page,'/message/output/popup/notifications.php');await learner.page.getByText(title,{exact:false}).first().waitFor();
    return {title,group:f.groups[0],uniqueRecipients:50,url,otherGroupDenied:true};
  });
  await test('trainer-group-calendar-visible',async()=>{
    const title='QA19 calendar '+stamp;
    await go(trainer.page,`/local/asodeltolstaff/communication.php?courseid=${f.courseid}`);await trainer.page.locator('#id_action').selectOption('schedule');
    await trainer.page.locator('#id_title').fill(title);await trainer.page.locator('#id_body').fill('Synthetic QA timetable, not institutional dates.');
    const start=new Date(Date.now()+86400000),end=new Date(start.getTime()+3600000);await date(trainer.page,'start',start);await date(trainer.page,'end',end);await save(trainer.page);
    const event=state().calendar_events.find(e=>e.name===title);assert.ok(event);assert.equal(Number(event.groupid),f.groups[0]);
    await go(learner.page,`/calendar/view.php?view=upcoming&course=${f.courseid}`);assert.ok((await learner.page.locator('#region-main').innerText()).includes(title));
    await go(other.page,`/calendar/view.php?view=upcoming&course=${f.courseid}`);assert.ok(!(await other.page.locator('#region-main').innerText()).includes(title));return event;
  });
  for(const [kind,file]of [['video','test.mp4'],['resource','material.pdf'],['assign',null]])await test('trainer-publish-'+kind+'-isolated',async()=>{
    const before=new Set(state().published_modules.map(m=>Number(m.cmid))),title=`QA19 ${kind} ${stamp}`;
    await go(trainer.page,`/local/asodeltolstaff/publish.php?courseid=${f.courseid}`);await trainer.page.locator('#id_kind').selectOption(kind);
    await trainer.page.locator('#id_title').fill(title);await trainer.page.locator('#id_reference').fill('Synthetic QA only; no official academic approval');
    if(file)await upload(trainer.page,'asset',file);else{await editor(trainer.page,'body','Synthetic QA practice; no official grade.');await date(trainer.page,'duedate',new Date(Date.now()+172800000));}
    await save(trainer.page);const added=state().published_modules.filter(m=>!before.has(Number(m.cmid)));assert.equal(added.length,1);const m=added[0];
    assert.equal(JSON.parse(m.availability).c[0].id,f.groups[0]);const url=`/mod/${m.kind}/view.php?id=${m.cmid}`;
    if(kind==='resource') {const r=await learner.context.request.get(url);assert.equal(r.status(),200);assert.equal((await r.body()).subarray(0,4).toString(),'%PDF');}
    else {await go(learner.page,url);assert.ok((await learner.page.locator('body').innerText()).includes(title));}
    if(kind==='video'){const frame=learner.page.frames().find(fr=>fr.url().includes('pluginfile'));if(frame?.url())await frame.locator('video').evaluate(async e=>{e.muted=true;await e.play();});}
    await deny(other.page,url);
    if(kind==='assign'){const notices=state().notifications.filter(n=>n.eventtype==='activitypublished'&&n.contexturl.endsWith(`id=${m.cmid}`));assert.equal(notices.length,50);assert.equal(new Set(notices.map(n=>n.useridto)).size,50);assert.ok(!notices.some(n=>Number(n.useridto)===f.other.id));const notice=notices.find(n=>Number(n.useridto)===f.users[0].id);await go(learner.page,'/message/output/popup/notifications.php?notificationid='+notice.id);await learner.page.getByText(title,{exact:false}).filter({visible:true}).first().waitFor();}
    return {cmid:m.cmid,kind,groupRestriction:JSON.parse(m.availability),otherGroupDenied:true};
  });
  await test('trainer-open-unit-preserves-prior-date-and-other-group-denied',async()=>{
    for(let i=0;i<2;i++){
      if(Number(state().release_sections.find(s=>Number(s.section)===i+1).visible)===1)continue;
      await go(trainer.page,`/local/asodeltolstaff/communication.php?courseid=${f.release.courseid}`);await trainer.page.locator('#id_action').selectOption('open');await trainer.page.locator('#id_section').selectOption(String(i+1));await save(trainer.page);
    }
    const s=state().release_sections.filter(s=>Number(s.section)>0);assert.equal(s.length,2);
    for(const section of s){assert.equal(Number(section.visible),1);assert.equal(JSON.parse(section.availability).c[0].id,f.release.groups[0]);}
    assert.deepEqual(JSON.parse(s[1].availability).c[1],JSON.parse(f.release.sections[1].original_availability));
    await go(learner.page,`/course/section.php?id=${s[0].id}`);assert.ok((await learner.page.locator('#region-main').innerText()).includes('QA release sentinel'));
    await deny(other.page,`/course/section.php?id=${s[0].id}`);await deny(learner.page,`/course/section.php?id=${s[1].id}`);return s;
  });
  await test('native-course-fields-save-reload-without-publishing',async()=>{
    await go(admin.page,`/course/edit.php?id=${f.courseid}`);
    await admin.page.locator('#id_fullname').fill('QA19 synthetic representative course - verified');
    await admin.page.locator('#id_idnumber').fill('QA19-verified-'+f.run);await admin.page.locator('#id_visible').selectOption('0');
    if(!await admin.page.locator('[name="customfield_asodeltol_adminroleid"]').isVisible())await admin.page.getByRole('button',{name:'Expand all',exact:true}).click();
    await admin.page.locator('[name="customfield_asodeltol_adminroleid"]').fill('qa19-admin-verified');await admin.page.locator('[name="customfield_asodeltol_auditorroleid"]').fill('qa19-audit-verified');
    await editor(admin.page,'summary_editor','Synthetic course summary, verified; not institutional content.');await admin.page.locator('#id_lang').selectOption('en');
    const dates=await admin.page.locator('[name^="startdate["], [name^="enddate["]').evaluateAll(es=>es.filter(e=>e.tagName==='SELECT').map(e=>({name:e.name,value:e.value})));
    await save(admin.page,'#id_saveanddisplay');await go(admin.page,`/course/edit.php?id=${f.courseid}`);assert.equal(await admin.page.locator('#id_visible').inputValue(),'0');
    assert.equal(await admin.page.locator('#id_fullname').inputValue(),'QA19 synthetic representative course - verified');assert.equal(await admin.page.locator('#id_idnumber').inputValue(),'QA19-verified-'+f.run);
    assert.equal(await admin.page.locator('#id_lang').inputValue(),'en');assert.deepEqual(await admin.page.locator('[name^="startdate["], [name^="enddate["]').evaluateAll(es=>es.filter(e=>e.tagName==='SELECT').map(e=>({name:e.name,value:e.value}))),dates);
    assert.equal(await admin.page.locator('[name="customfield_asodeltol_adminroleid"]').inputValue(),'qa19-admin-verified');
    assert.equal(await admin.page.locator('[name="customfield_asodeltol_auditorroleid"]').inputValue(),'qa19-audit-verified');await deny(learner.page,`/course/edit.php?id=${f.courseid}`);
    return {courseid:f.courseid,hidden:true,roleFieldsAreMetadataNotPermissionGrants:true};
  });
  await test('native-group-fields-save-reload',async()=>{
    const url=`/group/group.php?courseid=${f.courseid}&id=${f.groups[0]}`;await go(admin.page,url);
    await admin.page.locator('#id_name').fill('QA19 G01 verified');await admin.page.locator('#id_idnumber').fill('QA19-G01-verified');
    await editor(admin.page,'description_editor','Synthetic group description, verified.');await admin.page.locator('[name="customfield_asodeltol_available"]').selectOption('2');
    await save(admin.page);await go(admin.page,url);assert.equal(await admin.page.locator('#id_name').inputValue(),'QA19 G01 verified');
    assert.equal(await admin.page.locator('[name="customfield_asodeltol_available"]').inputValue(),'2');await admin.page.locator('[name="customfield_asodeltol_available"]').selectOption('1');await save(admin.page);
    await deny(learner.page,url);return {groupid:f.groups[0],courseid:f.courseid,unavailableSaved:true,availableRestored:true};
  });
  await test('support-unconfigured-no-misleading-form',async()=>{try{state('unconfigure-support');await go(learner.page,'/local/asodeltolstaff/support.php');assert.equal(await learner.page.locator('#id_subject').count(),0);assert.equal(await learner.page.locator('.alert-warning').count(),1);}finally{state('configure-support');}});
  await test('support-concurrent-independent-sessions-one-case',async()=>{
    const a=await login(f.users[49]),b=await login(f.users[49]);
    try{
      const before=state().cases.filter(c=>Number(c.requesterid)===f.users[49].id).length;
      const post=async actor=>{await go(actor.page,'/local/asodeltolstaff/support.php');await actor.page.locator('#id_subject').fill('QA19 concurrent '+stamp);await actor.page.locator('#id_body').fill('Synthetic concurrent support request.');return actor.page.locator('form.mform').evaluate(form=>Object.fromEntries(new FormData(form)));};
      const data=await Promise.all([post(a),post(b)]);
      const responses=await Promise.all([a.context.request.post('/local/asodeltolstaff/support.php',{form:data[0]}),b.context.request.post('/local/asodeltolstaff/support.php',{form:data[1]})]);
      const after=state().cases.filter(c=>Number(c.requesterid)===f.users[49].id);assert.equal(after.length-before,1);
      const newest=after.at(-1),ns=state().notifications.filter(n=>n.eventtype==='supportrequest'&&n.contexturl.endsWith(`caseid=${newest.id}`));assert.equal(ns.length,1);
      return {independentSessions:2,httpStatuses:responses.map(r=>r.status()),newCases:1,notifications:1};
    }finally{await a.context.close();await b.context.close();}
  });
}finally{
  const final=state();assert.equal(final.course_hidden,true);assert.equal(final.category_hidden,true);
  fs.writeFileSync(new URL('context-final.json',dir),JSON.stringify(final,null,2));
  fs.writeFileSync(new URL('results.json',dir),JSON.stringify({pluginVersion:'2026102506',engine:browser.version(),rows,issues,deniedRequests,productionTouched:false},null,2));
  for(const actor of [trainer,learner,other,admin])await actor?.context.close();await browser.close();
  console.log('WORKFLOW_EVIDENCE',dir.pathname,'PASS',rows.filter(r=>r.status==='PASS').length,'FAIL',rows.filter(r=>r.status==='FAIL').length);process.exitCode=rows.some(r=>r.status==='FAIL')?1:0;
}
