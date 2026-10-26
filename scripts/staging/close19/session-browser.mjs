/** Session form, persistence and denied course access in the isolated campus. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
const root=new URL('../../../build/staging/close19/',import.meta.url);
const password=fs.readFileSync(new URL('private/synthetic-password.txt',root),'utf8').trim();
const roles=JSON.parse(fs.readFileSync(new URL('private/roles350.json',root),'utf8'));
const stamp=new Date().toISOString().replace(/[:.]/g,'-');
const code='QA350-SESSION-'+stamp.replace(/[^A-Za-z0-9]/g,'').slice(0,27);
const out=new URL(`session-browser-${stamp}/`,root);fs.mkdirSync(out,{recursive:true});
const browser=await chromium.launch({headless:true}),rows=[];
const login=async(username)=>{const context=await browser.newContext({baseURL:'http://127.0.0.1:8054'}),page=await context.newPage();
  await page.goto('/login/index.php');await page.locator('#username').fill(username);await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);return {context,page};};
async function test(name,fn){try{rows.push({name,status:'PASS',observed:await fn()});}catch(e){rows.push({name,status:'FAIL',error:String(e.stack||e).split(password).join('[REDACTED]').slice(0,1400)});}console.log(name,rows.at(-1).status);}
try{
  const trainer=await login('qa350_trainer1'),student=await login('qa350_003'),othertrainer=await login('qa350_trainer2');
  let sessionid;
  await test('trainer-schedules-session-in-own-group',async()=>{
    const r=await trainer.page.goto(`/local/asodeltolstaff/session.php?courseid=${roles.courseid}`);assert.equal(r.status(),200);
    assert.equal(await trainer.page.locator('.errorcode').count(),0);
    await trainer.page.locator('#id_sessioncode').fill(code);
    await trainer.page.locator('#id_groupid').selectOption(String(roles.groups['1']));
    await trainer.page.locator('#id_trainerid').selectOption(String(roles.trainers['1']));
    await Promise.all([trainer.page.waitForNavigation(),trainer.page.locator('#id_submitbutton').click()]);
    const text=await trainer.page.locator('#region-main').innerText();assert.ok(text.includes(code),text.slice(0,700));
    await trainer.page.reload();assert.ok((await trainer.page.locator('#region-main').innerText()).includes(code));
    const row=trainer.page.locator('tr').filter({hasText:code});
    const attendanceurl=await row.locator('a[href*="attendance.php"]').getAttribute('href');
    sessionid=new URL(attendanceurl,'http://127.0.0.1:8054').searchParams.get('id');
    assert.ok(sessionid);
    await trainer.page.screenshot({path:fileURLToPath(new URL('session.png',out)),fullPage:true});
    return {code,groupid:roles.groups['1'],persistedAfterReload:true};
  });
  await test('beneficiary-cannot-open-management-form',async()=>{
    const r=await student.page.goto(`/local/asodeltolstaff/session.php?courseid=${roles.courseid}`);
    assert.ok(r.status()===403||await student.page.locator('.errorcode').count()>0);
    return {status:r.status(),error:await student.page.locator('.errorcode').count()>0};
  });
  await test('beneficiary-sees-own-group-session',async()=>{
    const r=await student.page.goto(`/local/asodeltolstaff/index.php?courseid=${roles.courseid}`);
    assert.equal(r.status(),200);assert.ok((await student.page.locator('#region-main').innerText()).includes(code));
    const foreign=await othertrainer.page.goto(`/local/asodeltolstaff/index.php?courseid=${roles.courseid}`);
    assert.equal(foreign.status(),200);
    assert.ok(!(await othertrainer.page.locator('#region-main').innerText()).includes(code));
    return {sessionVisibleToOwnBeneficiary:true,foreignGroupSessionHidden:true};
  });
  await test('provisional-attendance-form-persists-and-other-group-is-denied',async()=>{
    const url=`/local/asodeltolstaff/attendance.php?id=${sessionid}`;
    const r=await trainer.page.goto(url);assert.equal(r.status(),200);
    const first=trainer.page.locator('select[name^="status_"]').first();
    const field=await first.getAttribute('name');assert.ok(field);
    await first.selectOption('reported_present');
    await trainer.page.locator('#id_reason').fill('Declaración sintética de QA; no demuestra conexión de 60 minutos.');
    await Promise.all([trainer.page.waitForNavigation(),trainer.page.locator('#id_submitbutton').click()]);
    await trainer.page.goto(url);
    assert.equal(await trainer.page.locator(`select[name="${field}"]`).inputValue(),'reported_present');
    const denied=await othertrainer.page.goto(url);
    assert.ok(denied.status()===403||await othertrainer.page.locator('.errorcode').count()>0);
    return {sessionid:Number(sessionid),field,provisionalStatusPersisted:true,foreignTrainerDenied:true};
  });
  await Promise.all([trainer.context.close(),student.context.close(),othertrainer.context.close()]);
}finally{await browser.close();}
fs.writeFileSync(new URL('results.json',out),JSON.stringify({executedAt:new Date().toISOString(),environment:'isolated Moodle 8054',rows},null,2));
console.log('EVIDENCE',fileURLToPath(out));if(rows.some(x=>x.status!=='PASS'))process.exitCode=1;
