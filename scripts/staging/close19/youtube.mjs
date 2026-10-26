/** Observe the actual native Moodle YouTube resource, including external failures. */
import {chromium} from 'playwright';
import fs from 'node:fs';
import {fileURLToPath} from 'node:url';
const out=new URL('../../../build/staging/close19/',import.meta.url);
const read=n=>fs.readFileSync(new URL('private/'+n,out),'utf8').replace(/^\uFEFF/,'').trim();
const f=JSON.parse(read('fixture.json')),password=read('synthetic-password.txt');
const browser=await chromium.launch({headless:true}),page=await browser.newPage();
const observed={test:'native-Moodle-YouTube-playback',sample:'M7lc1UVf-VE',officialExample:'https://developers.google.com/youtube/iframe_api_reference',status:'BLOCKED',external:true};
try{
  await page.goto('http://127.0.0.1:8054/login/index.php');await page.locator('#username').fill(f.users[0].username);await page.locator('#password').fill(password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
  await page.goto(`http://127.0.0.1:8054/mod/url/view.php?id=${f.youtube}&redirect=0`,{timeout:30000});
  observed.finalURL=page.url();
  const frame=page.locator('iframe[src*="youtube"]');await frame.first().waitFor({state:'attached',timeout:20000}).catch(()=>{});observed.embeddedFrames=await frame.count();
  observed.mediaNodes=await page.locator('#region-main iframe,#region-main object,#region-main video').evaluateAll(es=>es.map(e=>({tag:e.tagName,src:e.getAttribute('src')||e.getAttribute('data')})));
  if(await frame.count()){
    // Use the real player controls and observe its native media clock. Do not
    // replace the iframe or disturb Moodle/VideoJS's existing API registration.
    const bigplay=page.locator('.vjs-big-play-button:visible').first();
    const embedded=page.frames().find(f=>f.url().includes('youtube.com/embed/'));
    if(await bigplay.count())await bigplay.click();
    else await embedded.locator('.ytp-large-play-button,button[aria-label="Play"],button[aria-label="Play (k)"]').filter({visible:true}).first().click({timeout:20000});
    await embedded.waitForFunction(()=>document.querySelector('video')?.currentTime>0.5,{},{timeout:30000,polling:100});
    observed.currentTime=await embedded.locator('video').evaluate(e=>e.currentTime);
    observed.status=observed.currentTime>0.5?'PASS':'BLOCKED';
  }else{observed.reason='Native reference did not expose a playable embedded YouTube iframe; not accredited.';}
}catch(e){observed.reason=String(e.message).split(password).join('[REDACTED]');observed.player=await page.evaluate(()=>({time:window.qa19Player?.getCurrentTime?.()||0,state:window.qa19Player?.getPlayerState?.()??null,providerError:window.qa19YoutubeError||null})).catch(()=>null);observed.frameText=[];for(const frame of page.frames().filter(f=>f.url().includes('youtube.com/embed/'))){observed.frameText.push((await frame.locator('body').innerText().catch(()=>'' )).slice(0,500));}}
finally{await page.screenshot({path:fileURLToPath(new URL('youtube-observation.png',out))}).catch(()=>{});await browser.close();fs.writeFileSync(new URL('youtube.json',out),JSON.stringify(observed,null,2));console.log(JSON.stringify(observed));}
