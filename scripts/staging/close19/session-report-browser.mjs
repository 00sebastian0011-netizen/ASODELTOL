/** Export actual provisional QA350 session rows for opposite scoped observers. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
const root=new URL('../../../build/staging/close19/',import.meta.url);
const password=fs.readFileSync(new URL('private/synthetic-password.txt',root),'utf8').trim();
const roles=JSON.parse(fs.readFileSync(new URL('private/roles350.json',root),'utf8'));
const out=new URL(`session-report-browser-${new Date().toISOString().replace(/[:.]/g,'-')}/`,root);fs.mkdirSync(out,{recursive:true});
const browser=await chromium.launch({headless:true}),rows=[];
for(const [profile,username] of [['sena','qa350_sena'],['interventoria','qa350_interventoria']]){
  const context=await browser.newContext({baseURL:'http://127.0.0.1:8054',acceptDownloads:true}),page=await context.newPage();
  try{
    await page.goto('/login/index.php');await page.locator('#username').fill(username);await page.locator('#password').fill(password);
    await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
    const r=await page.goto(`/local/asodeltolstaff/reports.php?courseid=${roles.courseid}`);assert.equal(r.status(),200);
    await page.locator('#id_dataset').selectOption('sessions');await page.locator('#id_format').selectOption('excel');
    const event=page.waitForEvent('download');await page.getByRole('button',{name:/download/i}).click();
    const download=await event;const file=new URL(`${profile}-sessions.xlsx`,out);await download.saveAs(fileURLToPath(file));
    const bytes=fs.readFileSync(file);assert.equal(bytes.subarray(0,2).toString(),'PK');
    rows.push({profile,status:'PASS',file:fileURLToPath(file),bytes:bytes.length});
  }catch(e){rows.push({profile,status:'FAIL',error:String(e.stack||e).split(password).join('[REDACTED]').slice(0,1400)});}
  await context.close();console.log(profile,rows.at(-1).status);
}
await browser.close();fs.writeFileSync(new URL('results.json',out),JSON.stringify({executedAt:new Date().toISOString(),rows},null,2));
console.log('EVIDENCE',fileURLToPath(out));if(rows.some(x=>x.status!=='PASS'))process.exitCode=1;
