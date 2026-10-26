/** Actual Moodle profile forms for synthetic beneficiaries. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const root=new URL('../../../build/staging/close19/',import.meta.url);
const password=fs.readFileSync(new URL('private/synthetic-password.txt',root),'utf8').trim();
const output=new URL(`identity-browser-${new Date().toISOString().replace(/[:.]/g,'-')}/`,root);
fs.mkdirSync(output,{recursive:true});
const browser=await chromium.launch({headless:true});
const results=[];const actors=[];
async function login(username){
  const context=await browser.newContext({baseURL:'http://127.0.0.1:8054'});
  const page=await context.newPage();actors.push(context);
  await page.goto('/login/index.php');await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
  return page;
}
async function check(name,fn){try{results.push({name,status:'PASS',observed:await fn()});}catch(e){results.push({name,status:'FAIL',error:String(e.stack||e).split(password).join('[REDACTED]').slice(0,1500)});}console.log(name,results.at(-1).status);}
async function openProfileFields(page){
  const field=page.locator('[name="profile_field_qa_documento"]');
  if(!await field.isVisible()){
    const expand=page.getByRole('button',{name:/expand all/i});
    if(await expand.count())await expand.first().click();
  }
  if(!await field.isVisible()){
    const section=page.locator('fieldset').filter({has:field});
    const toggle=section.locator('legend a,legend button').first();
    if(await toggle.count())await toggle.click();
  }
  assert.ok(await field.isVisible(),'Moodle profile fields remain collapsed');
}
try{
  const a=await login('qa350_003'),b=await login('qa350_004');
  const document=`QA350-BROWSER-${Date.now()}`;
  await check('self-profile-form-save-and-reload',async()=>{
    const response=await a.goto('/user/edit.php');assert.equal(response.status(),200);
    await openProfileFields(a);
    const fields=await a.locator('[name^="profile_field_"], [name="firstname"], [name="lastname"], [name="email"]').evaluateAll(es=>es.map(e=>({name:e.name,type:e.type})));
    fs.writeFileSync(new URL('fields.json',output),JSON.stringify(fields,null,2));
    await a.locator('[name="profile_field_qa_documento"]').fill(document);
    await a.locator('[name="profile_field_qa_residencia"]').fill('Residencia QA navegador');
    await a.locator('[name="profile_field_qa_caracterizacion"]').fill('Grupo sintético QA');
    await a.locator('#id_updateandrestart,#id_submitbutton').first().click();
    await a.goto('/user/edit.php');await openProfileFields(a);
    assert.equal(await a.locator('[name="profile_field_qa_documento"]').inputValue(),document);
    assert.equal(await a.locator('[name="profile_field_qa_residencia"]').inputValue(),'Residencia QA navegador');
    assert.equal(await a.locator('[name="profile_field_qa_caracterizacion"]').inputValue(),'Grupo sintético QA');
    return {profileFieldsPersisted:3,route:'/user/edit.php'};
  });
  await check('duplicate-document-form-rejected-without-persistence',async()=>{
    await b.goto('/user/edit.php');await openProfileFields(b);const prior=await b.locator('[name="profile_field_qa_documento"]').inputValue();
    await b.locator('[name="profile_field_qa_documento"]').fill(document);
    await b.locator('#id_updateandrestart,#id_submitbutton').first().click();
    const error=await b.locator('.error,.invalid-feedback,[id^="id_error_"]').allTextContents();
    assert.ok(error.some(x=>x.trim()),'Moodle form did not show duplicate-document error');
    await b.goto('/user/edit.php');await openProfileFields(b);assert.equal(await b.locator('[name="profile_field_qa_documento"]').inputValue(),prior);
    return {rejectedBy:'native Moodle form',priorUnchanged:true,errorCount:error.filter(x=>x.trim()).length};
  });
  await check('distinct-document-form-accepted',async()=>{
    await b.locator('[name="profile_field_qa_documento"]').fill(`${document}-B`);
    await b.locator('#id_updateandrestart,#id_submitbutton').first().click();
    await b.goto('/user/edit.php');await openProfileFields(b);assert.equal(await b.locator('[name="profile_field_qa_documento"]').inputValue(),`${document}-B`);
    return {distinctDocumentAccepted:true};
  });
  await check('private-profile-and-direct-edit-denied',async()=>{
    const verification=JSON.parse(execFileSync('docker',['exec','--user','www-data','asodeltol-close19-webserver-1','php','/opt/close19/identity-check.php'],{encoding:'utf8'}));
    const userid=verification.users[0].id;
    await b.goto(`/user/profile.php?id=${userid}`);
    assert.ok(!(await b.locator('body').innerText()).includes(document),'Private document leaked in another profile');
    const response=await b.goto(`/user/edit.php?id=${userid}`);
    const denied=response.status()===403||await b.locator('.errorcode').count()>0||!b.url().includes(`id=${userid}`);
    assert.ok(denied,'Other user edit was not denied');
    const after=JSON.parse(execFileSync('docker',['exec','--user','www-data','asodeltol-close19-webserver-1','php','/opt/close19/identity-check.php'],{encoding:'utf8'}));
    assert.deepEqual(after.users.map(u=>u.document_hash),verification.users.map(u=>u.document_hash));
    fs.writeFileSync(new URL('db-readback.json',output),JSON.stringify(after,null,2));
    return {privateProfile:true,unauthorisedEditDenied:true,databaseUnchanged:true};
  });
}finally{
  for(const c of actors)await c.close();await browser.close();
  const verification={environment:'http://127.0.0.1:8054',engine:'Chromium',time:new Date().toISOString(),commit:execFileSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).trim(),results};
  fs.writeFileSync(new URL('results.json',output),JSON.stringify(verification,null,2));
  console.log('EVIDENCE',fileURLToPath(new URL('results.json',output)));
  if(results.some(r=>r.status==='FAIL'))process.exitCode=1;
}
