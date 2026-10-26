/** Submit and read a synthetic glossary entry through real Moodle forms. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const root=new URL('../../../build/staging/close19/',import.meta.url);
const content=JSON.parse(fs.readFileSync(new URL('private/content350.json',root),'utf8'));
const fixture=JSON.parse(fs.readFileSync(new URL('private/fixture.json',root),'utf8'));
const password=fs.readFileSync(new URL('private/synthetic-password.txt',root),'utf8').trim();
const adminPassword=fs.readFileSync(new URL('private/admin-password.txt',root),'utf8').trim();
const cmid=content.mapping.find(x=>x.code==='UT1.08'&&x.type==='glossary').cmid;
const stamp=new Date().toISOString().replace(/[:.]/g,'-');
const output=new URL(`glossary-browser-${stamp}/`,root);fs.mkdirSync(output,{recursive:true});
const browser=await chromium.launch({headless:true});
const rows=[];
const login=async(username,secret=password)=>{
  const context=await browser.newContext({baseURL:'http://127.0.0.1:8054'}),page=await context.newPage();
  await page.goto('/login/index.php');await page.locator('#username').fill(username);await page.locator('#password').fill(secret);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
  return {context,page};
};
async function test(name,fn){try{rows.push({name,status:'PASS',observed:await fn()});}catch(e){rows.push({name,status:'FAIL',error:String(e.stack||e).split(password).join('[REDACTED]').split(adminPassword).join('[REDACTED]').slice(0,1500)});}console.log(name,rows.at(-1).status);}
try{
  const own=await login('qa350_003');
  const outsider=await login(fixture.other.username);
  const trainer=await login('qa350_trainer1');
  const admin=await login('qa_admin',adminPassword);
  const concept='QA350 prueba glosario '+stamp;
  await test('beneficiary-can-submit-and-read-glossary-entry',async()=>{
    const r=await own.page.goto(`/mod/glossary/view.php?id=${cmid}`);assert.equal(r.status(),200);
    const add=own.page.getByRole('button',{name:'Add entry'});
    assert.ok(await add.count(),JSON.stringify({text:(await own.page.locator('#region-main').innerText()).slice(0,1000)}));
    await add.click();
    await own.page.locator('#id_concept').fill(concept);
    const definition=own.page.locator('textarea[name="definition_editor[text]"]');
    await definition.fill('Definición sintética comprobada mediante formulario y recarga.');
    await Promise.all([own.page.waitForNavigation(),own.page.locator('#id_submitbutton').click()]);
    await own.page.goto(`/mod/glossary/view.php?id=${cmid}`);
    assert.ok((await own.page.locator('#region-main').innerText()).includes(concept));
    await own.page.screenshot({path:fileURLToPath(new URL('entry.png',output)),fullPage:true});
    return {cmid,conceptSha256:createHash('sha256').update(concept).digest('hex'),persisted:true};
  });
  await test('unenrolled-outsider-cannot-read-hidden-glossary',async()=>{
    const r=await outsider.page.goto(`/mod/glossary/view.php?id=${cmid}`);
    const text=await outsider.page.locator('body').innerText();
    assert.ok(r.status()===403||!text.includes(concept));
    return {status:r.status(),entryAbsent:!text.includes(concept)};
  });
  await test('trainer-can-create-entry-and-admin-can-edit',async()=>{
    await trainer.page.goto(`/mod/glossary/view.php?id=${cmid}`);
    await trainer.page.getByRole('button',{name:'Add entry'}).click();
    const trainerConcept='QA350 tutor glosario '+stamp;
    await trainer.page.locator('#id_concept').fill(trainerConcept);
    await trainer.page.locator('textarea[name="definition_editor[text]"]').fill('Primera versión sintética.');
    await Promise.all([trainer.page.waitForNavigation(),trainer.page.locator('#id_submitbutton').click()]);
    const entryid=execFileSync('docker',['exec','--user','www-data','asodeltol-close19-webserver-1',
      'php','/tmp/glossary-lookup.php',trainerConcept],{encoding:'utf8'}).trim();
    assert.ok(entryid);
    const r=await admin.page.goto(`/mod/glossary/edit.php?cmid=${cmid}&id=${entryid}`);assert.equal(r.status(),200);
    await admin.page.locator('textarea[name="definition_editor[text]"]').fill('Editado por administrador en Moodle local.');
    await Promise.all([admin.page.waitForNavigation(),admin.page.locator('#id_submitbutton').click()]);
    await trainer.page.reload();
    assert.ok((await trainer.page.locator('#region-main').innerText()).includes('Editado por administrador en Moodle local.'));
    return {cmid,entryid:Number(entryid),trainerCreated:true,adminEdited:true};
  });
  await Promise.all([own.context.close(),outsider.context.close(),trainer.context.close(),admin.context.close()]);
}finally{await browser.close();}
fs.writeFileSync(new URL('results.json',output),JSON.stringify({executedAt:new Date().toISOString(),environment:'isolated Moodle 8054',rows},null,2));
console.log('EVIDENCE',output.pathname);
if(rows.some(x=>x.status!=='PASS'))process.exitCode=1;
