// Mobile verification with a real Chromium at a phone viewport.
//
// Run from the repository root:
//   NODE_PATH=$HOME/pw/node_modules node tools/mobile_check.mjs [outDir]
//
// This is a development aid, not part of the shipped frontend. It drives the
// local dev server (port 3000) and writes screenshots into artifacts/.

import { mkdirSync } from 'node:fs';

// Playwright is installed outside the repository so the project keeps no test
// dependency on a browser automation package. Point PW_PLAYWRIGHT at its
// entry module, or install it resolvable as `playwright`.
const { chromium } = await import(process.env.PW_PLAYWRIGHT || 'playwright');

const outDir = process.argv[2] || 'artifacts/verification';
const base = process.env.AURUM_URL || 'http://127.0.0.1:3000';
mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
});
const context = await browser.newContext({
  viewport: { width: 390, height: 844 },
  deviceScaleFactor: 2,
  isMobile: true,
  hasTouch: true,
});
const page = await context.newPage();
const errors = [];
page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });
page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));

await page.goto(base + '/', { waitUntil: 'networkidle' });
await page.waitForSelector('.tile', { timeout: 15000 });
await page.screenshot({ path: `${outDir}/mobile-lobby.png` });

const layout = await page.evaluate(() => ({
  width: innerWidth,
  tabbar: (() => { const n = document.querySelector('.tabbar'); return n ? getComputedStyle(n).display : null; })(),
  nav: (() => { const n = document.querySelector('.app-nav'); return n ? getComputedStyle(n).display : null; })(),
  banner: (() => { const r = document.querySelector('.banner')?.getBoundingClientRect(); return r ? { w: Math.round(r.width), h: Math.round(r.height) } : null; })(),
  cols: getComputedStyle(document.querySelector('.tiles')).gridTemplateColumns,
  tiles: document.querySelectorAll('.tile').length,
}));

// Sign in through the real dialog.
await page.click('[data-action="open-login"]');
await page.waitForSelector('dialog[data-modal="login"][open]');
await page.screenshot({ path: `${outDir}/mobile-login.png` });
await page.fill('dialog[data-modal="login"] input[name="username"]', 'demo');
await page.fill('dialog[data-modal="login"] input[name="password"]', 'demo-password-123');
await page.click('[data-submit="login"]');
await page.waitForSelector('dialog[data-modal="login"]:not([open])', { state: 'attached', timeout: 15000 });
await page.waitForTimeout(700);
await page.screenshot({ path: `${outDir}/mobile-signed-in.png` });

// A slot game, settled once against the real engine.
await page.goto(base + '/slots/jili.super-ace', { waitUntil: 'networkidle' });
await page.waitForSelector('.reels', { timeout: 15000 });
await page.screenshot({ path: `${outDir}/mobile-slot.png` });
// Settle one round against the engine and capture the result.
await Promise.all([
  page.waitForResponse((r) => r.url().includes('/actions') && r.request().method() === 'POST', { timeout: 25000 }),
  page.click('.spin'),
]);
await page.waitForTimeout(900);
await page.screenshot({ path: `${outDir}/mobile-slot-result.png` });

// Blackjack table.
await page.goto(base + '/tables/table.blackjack', { waitUntil: 'networkidle' });
await page.waitForSelector('.table--blackjack', { timeout: 15000 });
await page.screenshot({ path: `${outDir}/mobile-blackjack.png` });
// Deal a real hand.
await Promise.all([
  page.waitForResponse((r) => r.url().includes('/actions') && r.request().method() === 'POST', { timeout: 25000 }),
  page.getByRole('button', { name: 'Deal', exact: true }).click(),
]);
await page.waitForTimeout(700);
await page.screenshot({ path: `${outDir}/mobile-blackjack-dealt.png` });

// Roulette layout.
await page.goto(base + '/tables/table.roulette', { waitUntil: 'networkidle' });
await page.waitForSelector('.felt', { timeout: 15000 });
await page.screenshot({ path: `${outDir}/mobile-roulette.png` });
// One straight-up bet, then spin.
await page.locator('.felt__number').nth(1).click();
await Promise.all([
  page.waitForResponse((r) => r.url().includes('/actions') && r.request().method() === 'POST', { timeout: 25000 }),
  page.click('.spin'),
]);
await page.waitForTimeout(2700);
await page.screenshot({ path: `${outDir}/mobile-roulette-result.png` });

// Coin flip.
await page.goto(base + '/tables/coinflip.heads-tails', { waitUntil: 'networkidle' });
await page.waitForSelector('.coin-stage', { timeout: 15000 });
await page.screenshot({ path: `${outDir}/mobile-coinflip.png` });
// Flip once.
await Promise.all([
  page.waitForResponse((r) => r.url().includes('/actions') && r.request().method() === 'POST', { timeout: 25000 }),
  page.click('.coin__button'),
]);
await page.waitForTimeout(1600);
await page.screenshot({ path: `${outDir}/mobile-coinflip-result.png` });

// Search sheet.
await page.goto(base + '/', { waitUntil: 'networkidle' });
await page.click('[data-action="open-search"]');
await page.waitForSelector('dialog[data-modal="search"][open]');
await page.fill('[data-search-input]', 'ace');
await page.waitForTimeout(400);
await page.screenshot({ path: `${outDir}/mobile-search.png` });

console.log(JSON.stringify({ layout, errors }, null, 2));
await browser.close();
