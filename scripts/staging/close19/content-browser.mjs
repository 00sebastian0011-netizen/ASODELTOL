/** Native course resources, video playback and interactive activity in Chromium. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const root=new URL('../../../build/staging/close19/',import.meta.url);
const content=JSON.parse(fs.readFileSync(new URL('private/content350.json',root),'utf8'));
const asset=JSON.parse(fs.readFileSync(new URL('private/media350/manifest.json',root),'utf8'));
const state=()=>JSON.parse(execFileSync('docker',['exec','--user','www-data','asodeltol-close19-webserver-1',
  'php','/opt/close19/content-check.php'],{encoding:'utf8'}));
const password=fs.readFileSync(new URL('private/synthetic-password.txt',root),'utf8').trim();
const output=new URL(`content-browser-${new Date().toISOString().replace(/[:.]/g,'-')}/`,root);fs.mkdirSync(output,{recursive:true});
const browser=await chromium.launch({headless:true});const context=await browser.newContext({baseURL:'http://127.0.0.1:8054',acceptDownloads:true});
const page=await context.newPage();const results=[];
async function test(name,fn){try{results.push({name,status:'PASS',observed:await fn()});}catch(e){results.push({name,status:'FAIL',error:String(e.stack||e).split(password).join('[REDACTED]').slice(0,1800)});}console.log(name,results.at(-1).status);}
try{
  await page.goto('/login/index.php');await page.locator('#username').fill('qa350_003');await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
  await test('five-units-fifty-catalog-items-four-general-resources',async()=>{
    const r=await page.goto(`/course/view.php?id=${content.courseid}`);assert.equal(r.status(),200);
    const text=await page.locator('#region-main').innerText();
    for(const unit of ['UT1','UT2','UT3','UT4','UT5'])assert.ok(text.includes(unit));
    assert.equal(content.mapping.length,50);assert.equal(content.general.length,4);
    return {courseid:content.courseid,units:5,items:50,general:4,visibleInBrowser:true};
  });
  await test('four-office-documents-download-identical',async()=>{
    const checked=[],urls=state().fileurls;
    for(const filename of ['material.pdf','material.docx','material.xlsx','material.pptx']){
      const code=filename==='material.pdf'?'UT1.05':filename==='material.docx'?'UT1.06':null;
      const cmid=code ? content.mapping.find(x=>x.code===code)?.cmid : content.auxiliary_resources[filename];
      const rpage=await page.goto(`/mod/resource/view.php?id=${cmid}`);assert.equal(rpage.status(),200,`${filename} module ${cmid}: ${page.url()}`);
      const url=urls[filename];
      const r=await context.request.get(url);assert.equal(r.status(),200,`${filename} file: ${url}`);
      const body=await r.body(),digest=createHash('sha256').update(body).digest('hex');
      assert.equal(digest,asset.files[filename].sha256,`${filename} download mismatch: ${r.url()} ${r.headers()['content-type']} ${body.subarray(0,10).toString()}`);
      checked.push({filename,bytes:body.length,sha256:digest});
    }
    return checked;
  });
  await test('embedded-720p-1080p-audio-and-spanish-vtt-play',async()=>{
    const r=await page.goto(`/mod/page/view.php?id=${content.video_cmid}`);assert.equal(r.status(),200);
    const videos=page.locator('video');assert.equal(await videos.count(),2);
    const proof=[];
    for(let i=0;i<2;i++){
      const video=videos.nth(i);const source=await video.locator('source').getAttribute('src');
      const response=await context.request.get(source);assert.equal(response.status(),200);
      const bytes=await response.body();const name=i===0?'test-720.mp4':'test-1080.mp4';
      assert.equal(createHash('sha256').update(bytes).digest('hex'),asset.files[name].sha256);
      const clock=await video.evaluate(async v=>{v.muted=true;await v.play();await new Promise(resolve=>setTimeout(resolve,650));return {time:v.currentTime,width:v.videoWidth,height:v.videoHeight};});
      assert.ok(clock.time>0.1,`${name} did not play`);assert.equal(clock.height,i===0?720:1080);
      proof.push({file:name,seconds:clock.time,dimensions:[clock.width,clock.height]});
    }
    fs.writeFileSync(new URL('rendered-video-dom.json',output),JSON.stringify(await videos.evaluateAll(es=>es.map(e=>e.outerHTML.slice(0,1400))),null,2));
    const captioned=await page.goto(`/local/asodeltolstaff/media.php?cmid=${content.video_cmid}&video=test-1080.mp4&caption=captions-es.vtt`);
    assert.equal(captioned.status(),200);
    const native=page.locator('video.asodeltol-accessible-video');
    const track=await native.locator('track[srclang="es"]').getAttribute('src');
    const vtt=await context.request.get(track);assert.equal(vtt.status(),200);
    assert.ok((await vtt.text()).startsWith('WEBVTT'));
    await page.goto(`/mod/page/view.php?id=${content.video_cmid}`);
    const audio=page.locator('audio').first();
    const audioclock=await audio.evaluate(async a=>{a.muted=true;await a.play();await new Promise(resolve=>setTimeout(resolve,500));return a.currentTime;});
    assert.ok(audioclock>0.1);
    return {videos:proof,spanishVttLoaded:true,audioSeconds:audioclock};
  });
  await test('infographic-visible-with-alternative-text',async()=>{
    const r=await page.goto(`/mod/page/view.php?id=${content.infographic_cmid}`);assert.equal(r.status(),200);
    const img=page.locator('img[alt*="Infografía sintética"]');assert.equal(await img.count(),1);
    const details=await img.evaluate(i=>({width:i.naturalWidth,height:i.naturalHeight,complete:i.complete}));
    assert.deepEqual(details,{width:1600,height:900,complete:true});return details;
  });
  await test('interactive-feedback-opens-without-grade-item',async()=>{
    const r=await page.goto(`/mod/feedback/view.php?id=${content.feedback_cmid}`);assert.equal(r.status(),200);
    const text=await page.locator('#region-main').innerText();
    fs.writeFileSync(new URL('feedback-visible.txt',output),text.slice(0,1400));
    const before=state().feedback_completed_for_qa350_003;
    if(before===0){
      assert.ok(text.includes('Answer the questions'));
      await page.getByText('Answer the questions',{exact:true}).click();
      const answer=page.locator('input[type="radio"]').first();
      await answer.check();
      const controls=await page.locator('input[type="submit"],button[type="submit"]').evaluateAll(es=>es.map(e=>({id:e.id,name:e.name,value:e.value,text:e.textContent})));
      fs.writeFileSync(new URL('feedback-controls.json',output),JSON.stringify(controls,null,2));
      await page.locator('[name="savevalues"],#id_savevalues').first().click();
    }
    const grade=state();
    assert.equal(grade.feedback_grade_items,0);
    assert.equal(grade.feedback_completed_for_qa350_003,Math.max(1,before));
    return {submittedThisRun:before===0,completionPersisted:true,feedbackGradeItems:0};
  });
  await test('captioned-player-rejects-missing-file-and-unauthorised-user',async()=>{
    const missing=await page.goto(`/local/asodeltolstaff/media.php?cmid=${content.video_cmid}&video=missing.mp4&caption=captions-es.vtt`);
    assert.ok(missing.status()>=400||await page.locator('.errorcode').count()>0);
    const fixture=JSON.parse(fs.readFileSync(new URL('private/fixture.json',root),'utf8'));
    const stranger=await browser.newContext({baseURL:'http://127.0.0.1:8054'});
    try{
      const outsider=await stranger.newPage();await outsider.goto('/login/index.php');
      await outsider.locator('#username').fill(fixture.none.username);await outsider.locator('#password').fill(password);
      await Promise.all([outsider.waitForURL(u=>!u.pathname.includes('/login/')),outsider.locator('#loginbtn').click()]);
      const denied=await outsider.goto(`/local/asodeltolstaff/media.php?cmid=${content.video_cmid}&video=test-720.mp4&caption=captions-es.vtt`);
      const deniedbody=(await outsider.locator('body').innerText()).slice(0,1000);
      fs.writeFileSync(new URL('unauthorised-media-body.txt',output),deniedbody);
      assert.equal(await outsider.locator('video.asodeltol-accessible-video').count(),0,
        `Unauthorised user received playable media: ${denied.status()} ${outsider.url()}`);
      return {missingFileDenied:true,unenrolledUserDenied:true};
    }finally{await stranger.close();}
  });
}finally{
  await context.close();await browser.close();
  fs.writeFileSync(new URL('results.json',output),JSON.stringify({environment:'http://127.0.0.1:8054',
    time:new Date().toISOString(),commit:execFileSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).trim(),results},null,2));
  console.log('EVIDENCE',fileURLToPath(new URL('results.json',output)));
  if(results.some(r=>r.status==='FAIL'))process.exitCode=1;
}
