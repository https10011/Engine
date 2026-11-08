// Desktop/laptop verification with a real Chromium.
//
// Run from the repository root:
//   PW_PLAYWRIGHT=file://$HOME/pw/node_modules/playwright/index.mjs \
//     node tools/desktop_check.mjs [outDir]
//
// This is a development aid, not part of the shipped frontend. It drives the
// local dev server (port 3000) and writes screenshots into artifacts/.

import { mkdirSync } from 'node:fs';

const { chromium } = await import(process.env.PW_PLAYWRIGHT || 'playwright');

const outDir = process.argv[2] || 'artifacts/verification';
const base = process.env.AURUM_URL || 'http://127.0.0.1:3000';
mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
});
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
const errors = [];
page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });
page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));

async function shot(name) {
  await page.screenshot({ path: `${outDir}/desktop-${name}.png` });
}

await page.goto(base + '/', { waitUntil: 'networkidle' });
await page.waitForSelector('.tile', { timeout: 15000 });
await shot('lobby');

const layout = await page.evaluate(() => ({
  width: innerWidth,
  tabbar: getComputedStyle(document.querySelector('.tabbar')).display,
  nav: getComputedStyle(document.querySelector('.app-nav')).display,
  cols: getComputedStyle(document.querySelector('.tiles')).gridTemplateColumns,
  overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  tiles: document.querySelectorAll('.tile').length,
}));

// Sign in through the real dialog.
await page.click('[data-action="open-login"]');
await page.waitForSelector('dialog[data-modal="login"][open]');
await page.fill('dialog[data-modal="login"] input[name="username"]', 'demo');
await page.fill('dialog[data-modal="login"] input[name="password"]', 'demo-password-123');
await page.click('[data-submit="login"]');
await page.waitForSelector('dialog[data-modal="login"]:not([open])', { state: 'attached', timeout: 15000 });
await page.waitForTimeout(600);
await shot('signed-in');

for (const [name, path, selector] of [
  ['slot', '/slots/jili.super-ace', '.reels'],
  ['blackjack', '/tables/table.blackjack', '.table--blackjack'],
  ['roulette', '/tables/table.roulette', '.felt'],
  ['coinflip', '/tables/coinflip.heads-tails', '.coin-stage'],
  ['history', '/history', '#main'],
]) {
  await page.goto(base + path, { waitUntil: 'networkidle' });
  await page.waitForSelector(selector, { timeout: 15000 });
  await shot(name);
}

// Inline lobby search at a wide viewport (the sheet is a mobile entry point).
await page.goto(base + '/', { waitUntil: 'networkidle' });
await page.fill('.searchbar input', 'fortune');
await page.waitForTimeout(400);
await shot('search');

console.log(JSON.stringify({ layout, errors }, null, 2));
await browser.close();
