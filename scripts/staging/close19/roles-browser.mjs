/** Browser report, progress and denial journeys for QA350 profiles. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
const root=new URL('../../../build/staging/close19/',import.meta.url);
const roles=JSON.parse(fs.readFileSync(new URL('private/roles350.json',root),'utf8'));
const check=JSON.parse(fs.readFileSync(new URL('private/roles-check.json',root),'utf8'));
const password=fs.readFileSync(new URL('private/synthetic-password.txt',root),'utf8').trim();
const output=new URL(`roles-browser-${new Date().toISOString().replace(/[:.]/g,'-')}/`,root);fs.mkdirSync(output,{recursive:true});
const browser=await chromium.launch({headless:true});const contexts=[];const results=[];
async function actor(name){const context=await browser.newContext({baseURL:'http://127.0.0.1:8054',acceptDownloads:true});contexts.push(context);
  const page=await context.newPage();await page.goto('/login/index.php');await page.locator('#username').fill(name);await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);return page;}
async function test(name,fn){try{results.push({name,status:'PASS',observed:await fn()});}catch(e){results.push({name,status:'FAIL',error:String(e.stack||e).split(password).join('[REDACTED]').slice(0,1300)});}console.log(name,results.at(-1).status);}
try{
  for(const [profile,username,allowed,denied] of [
    ['sena','qa350_sena',check.learnerids.group1,check.learnerids.group2],
    ['interventoria','qa350_interventoria',check.learnerids.group2,check.learnerids.group1],
    ['trainer','qa350_trainer1',check.learnerids.group1,check.learnerids.group2]]){
    const page=await actor(username);
    await test(`${profile}-report-download-and-progress-scope`,async()=>{
      const report=await page.goto(`/local/asodeltolstaff/reports.php?courseid=${roles.courseid}`);
      assert.equal(report.status(),200);assert.equal(await page.locator('.errorcode').count(),0);
      const downloadPromise=page.waitForEvent('download');
      await page.locator('#id_dataset').selectOption('participants');
      await page.locator('#id_format').selectOption('excel');
      await page.getByRole('button',{name:/download/i}).click();
      const download=await downloadPromise;const file=new URL(`${profile}-participants.xlsx`,output);
      await download.saveAs(fileURLToPath(file));const bytes=fs.readFileSync(file);
      assert.equal(bytes.subarray(0,2).toString(),'PK');
      const consolidatedPromise=page.waitForEvent('download');
      await page.locator('#id_dataset').selectOption('consolidated');
      await page.getByRole('button',{name:/download/i}).click();
      const consolidated=await consolidatedPromise;
      await consolidated.saveAs(fileURLToPath(new URL(`${profile}-consolidated.xlsx`,output)));
      const progress=await page.goto(`/local/asodeltolstaff/progress.php?courseid=${roles.courseid}&userid=${allowed}`);
      assert.equal(progress.status(),200);assert.equal(await page.locator('.errorcode').count(),0);
      const foreign=await page.goto(`/local/asodeltolstaff/progress.php?courseid=${roles.courseid}&userid=${denied}`);
      const blocked=foreign.status()===403||await page.locator('.errorcode').count()>0;
      assert.ok(blocked,'Foreign group progress visible');
      return {reportSha256:createHash('sha256').update(bytes).digest('hex'),reportBytes:bytes.length,
        ownProgressHttp:progress.status(),foreignProgressDenied:blocked};
    });
  }
  const learner=await actor('qa350_003');
  await test('beneficiary-administrative-export-denied',async()=>{
    const r=await learner.goto(`/local/asodeltolstaff/export.php?courseid=${roles.courseid}&format=excel&dataset=participants`);
    assert.ok(r.status()===403||await learner.locator('.errorcode').count()>0,'Student export not denied');
    return {http:r.status(),denied:true};
  });
}finally{for(const context of contexts)await context.close();await browser.close();
  fs.writeFileSync(new URL('results.json',output),JSON.stringify({environment:'http://127.0.0.1:8054',
    time:new Date().toISOString(),commit:execFileSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).trim(),results},null,2));
  console.log('EVIDENCE',fileURLToPath(new URL('results.json',output)));
  if(results.some(r=>r.status==='FAIL'))process.exitCode=1;
}
