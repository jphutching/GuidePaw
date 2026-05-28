const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const TOKEN = process.env.MIDDLEWARE_SECRET || '';
  const BASE = 'http://10.147.18.184:3333';

  const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  page.on('console', m => console.log('[CONSOLE]', m.type(), m.text()));
  page.on('pageerror', e => console.log('[PAGE ERROR]', e.message));

  // ── 1. Load dashboard ──
  await page.goto(BASE + '/dashboard');
  await page.waitForTimeout(2500);
  await page.screenshot({ path: '/tmp/dash-1-load.png', fullPage: false });
  const title = await page.textContent('h1');
  console.log('TITLE:', title);

  // ── 2. Session state version ──
  const ver = await page.textContent('#s-ver');
  console.log('VERSION:', ver);

  // ── 3. Check all file tabs ──
  const tabs = ['devlog', 'project', 'boot', 'rules'];
  for (const t of tabs) {
    await page.click(`.tab:has-text("${t.toUpperCase().replace('project','PROJECT STATE').replace('boot','CODEX BOOT').replace('rules','RULES').replace('devlog','DEVLOG')}")`);
    await page.waitForTimeout(1000);
    const content = await page.textContent('#content-' + t).catch(() => '(not found)');
    console.log('TAB', t.toUpperCase() + ':', content.slice(0, 60).replace(/\n/g,' '));
    await page.screenshot({ path: `/tmp/dash-tab-${t}.png` });
  }

  // Back to HANDOFF tab
  await page.click('.tab:first-child');
  await page.waitForTimeout(500);
  const handoff = await page.textContent('#content-handoff').catch(() => '');
  console.log('TAB HANDOFF:', handoff.slice(0, 60).replace(/\n/g,' '));

  // ── 4. Command runner ──
  await page.click('.cmd-btn:has-text("git log")');
  await page.waitForTimeout(3000);
  const cmdOut = await page.textContent('#cmd-output');
  console.log('CMD OUTPUT:', cmdOut.slice(0, 120).replace(/\n/g,' '));
  await page.screenshot({ path: '/tmp/dash-cmd.png' });

  // ── 5. Terminal tab ──
  await page.click('.tab:has-text("TERMINAL")');
  await page.waitForTimeout(2000);
  const termVisible = await page.isVisible('#terminal-container');
  const termHasCanvas = await page.locator('#terminal-container canvas').count();
  console.log('TERMINAL VISIBLE:', termVisible, '| CANVAS COUNT:', termHasCanvas);
  await page.screenshot({ path: '/tmp/dash-terminal.png' });

  // ── Full page screenshot ──
  await page.goto(BASE + '/dashboard');
  await page.waitForTimeout(2500);
  await page.screenshot({ path: '/tmp/dash-full.png', fullPage: true });

  await browser.close();
  console.log('DONE');
})().catch(e => { console.error('ERROR:', e.message); process.exit(1); });
