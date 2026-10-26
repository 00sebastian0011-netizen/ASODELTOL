/** Bidirectional Moodle message UI, two synthetic QA350 accounts only. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
const root=new URL('../../../build/staging/close19/',import.meta.url);
const password=fs.readFileSync(new URL('private/synthetic-password.txt',root),'utf8').trim();
const identity=JSON.parse(fs.readFileSync(new URL('private/identity-check.json',root),'utf8').replace(/^\uFEFF/,''));
const roles=JSON.parse(fs.readFileSync(new URL('private/roles350.json',root),'utf8'));
const learnerid=identity.users.find(x=>x.username==='qa350_003').id,senaid=roles.observers.sena.id;
const out=new URL(`message-browser-${new Date().toISOString().replace(/[:.]/g,'-')}/`,root);fs.mkdirSync(out,{recursive:true});
const browser=await chromium.launch({headless:true}),rows=[];
const actor=async(username)=>{const context=await browser.newContext({baseURL:'http://127.0.0.1:8054'}),page=await context.newPage();
  await page.goto('/login/index.php');await page.locator('#username').fill(username);await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);return {context,page};};
async function test(name,fn){try{rows.push({name,status:'PASS',observed:await fn()});}catch(e){rows.push({name,status:'FAIL',error:String(e.stack||e).split(password).join('[REDACTED]').slice(0,1300)});}console.log(name,rows.at(-1).status);}
try{
  const learner=await actor('qa350_003'),sena=await actor('qa350_sena');
  const marker='QA350 mensaje sintético '+Date.now(),reply='QA350 respuesta sintética '+Date.now();
  const composer=page=>page.locator('[data-region="send-message-txt"]:visible');
  const send=page=>page.locator('[data-action="send-message"]:visible');
  await test('beneficiary-message-arrives-at-sena',async()=>{
    await learner.page.goto(`/message/index.php?id=${senaid}`);
    await composer(learner.page).fill(marker);await send(learner.page).click();
    await learner.page.getByText(marker,{exact:true}).filter({visible:true}).first().waitFor();
    await sena.page.goto(`/message/index.php?id=${learnerid}`);
    await sena.page.getByText(marker,{exact:true}).filter({visible:true}).first().waitFor();
    return {sender:learnerid,receiver:senaid,receivedInSenaInbox:true};
  });
  await test('sena-reply-arrives-at-beneficiary',async()=>{
    await composer(sena.page).fill(reply);await send(sena.page).click();
    await sena.page.getByText(reply,{exact:true}).filter({visible:true}).first().waitFor();
    await learner.page.reload();
    await learner.page.getByText(reply,{exact:true}).filter({visible:true}).first().waitFor();
    await learner.page.screenshot({path:fileURLToPath(new URL('reply.png',out)),fullPage:true});
    return {sender:senaid,receiver:learnerid,receivedInBeneficiaryInbox:true};
  });
  await Promise.all([learner.context.close(),sena.context.close()]);
}finally{await browser.close();}
fs.writeFileSync(new URL('results.json',out),JSON.stringify({executedAt:new Date().toISOString(),environment:'isolated Moodle 8054',rows},null,2));
console.log('EVIDENCE',fileURLToPath(out));if(rows.some(x=>x.status!=='PASS'))process.exitCode=1;
