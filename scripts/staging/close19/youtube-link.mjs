/** Actual native course link -> official YouTube sample -> provider media clock. */
import {chromium} from 'playwright';import fs from 'node:fs';
const out=new URL('../../../build/staging/close19/',import.meta.url),read=n=>fs.readFileSync(new URL('private/'+n,out),'utf8').replace(/^\uFEFF/,'').trim(),f=JSON.parse(read('fixture.json')),password=read('synthetic-password.txt');
const browser=await chromium.launch({headless:true}),page=await browser.newPage(),result={test:'native-YouTube-link-playback',sample:'M7lc1UVf-VE',status:'BLOCKED'};
try{
 await page.goto('http://127.0.0.1:8054/login/index.php');await page.locator('#username').fill(f.users[6].username);await page.locator('#password').fill(password);await Promise.all([page.waitForURL(u=>!u.pathname.includes('/login/')),page.locator('#loginbtn').click()]);
 await page.goto(`http://127.0.0.1:8054/mod/url/view.php?id=${f.youtube}&redirect=0`,{waitUntil:'networkidle'});
 let youtube=page;
 if(new URL(page.url()).hostname.endsWith('youtube.com')){result.courseLink=page.url();result.navigation='Native Moodle URL redirect followed';}
 else{const link=page.locator('a[href*="youtube.com/watch"]').first();await link.waitFor();result.courseLink=await link.getAttribute('href');const target=await link.getAttribute('target');
 if(target==='_blank'){const pending=page.waitForEvent('popup');await link.click();youtube=await pending;}else{await Promise.all([page.waitForURL(u=>u.hostname.endsWith('youtube.com')) ,link.click()]);}}
 await youtube.waitForLoadState('domcontentloaded');
 const consent=youtube.getByRole('button',{name:/^(Reject all|Rechazar todo)$/i});if(await consent.isVisible())await consent.click();
 await youtube.locator('video').first().waitFor({timeout:30000});
 if(await youtube.locator('video').first().evaluate(e=>e.paused)){const play=youtube.locator('.ytp-large-play-button:visible,.ytp-play-button:visible').first();await play.click();}
 await youtube.waitForFunction(()=>document.querySelector('video')?.currentTime>0.5,{},{timeout:30000,polling:100});
 result.currentTime=await youtube.locator('video').first().evaluate(e=>e.currentTime);result.finalURL=youtube.url();result.status='PASS';
}catch(e){result.reason=e.message.split(password).join('[REDACTED]');result.visibleText=(await page.locator('body').innerText().catch(()=>'' )).slice(0,500);}
finally{await browser.close();fs.writeFileSync(new URL('youtube-link.json',out),JSON.stringify(result,null,2));console.log(JSON.stringify(result));}
