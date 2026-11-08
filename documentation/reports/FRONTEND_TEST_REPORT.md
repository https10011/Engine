# Frontend Test Report

Verification of the AURUM frontend and the one additive API route, with the exact
commands and observed results.

## 1. Environment

| Item | Value |
| --- | --- |
| PHP | 8.3.32 (static CLI build) |
| Database | MariaDB 10.11 on `127.0.0.1:3307`, schema migrated |
| Dev server | `php bin/console serve --host=0.0.0.0 --port=3000` |
| Browser driver | Playwright 1.49.1, Chromium 131.0.6778.33 headless shell |
| Mobile viewport | 390×844, DPR 2, touch, mobile UA |
| Desktop viewport | 1440×900, DPR 1 |
| Accounts | `demo` (player, ₱5,000 start) and `owner` (administrator) |

Browser tooling is installed outside the repository (`~/pw`); the scripts are
`tools/mobile_check.mjs` and `tools/desktop_check.mjs`. They are development aids, not a
shipped dependency. Chromium needs `--no-sandbox`, fonts and the extracted shared
libraries in this container; the scripts already pass `--no-sandbox`,
`--disable-dev-shm-usage` and `--disable-gpu`.

## 2. Server-side suite

```bash
export PATH="$HOME/bin:$PATH"
composer check
```

`composer check` = PHP syntax lint → PHPStan → PHPUnit.

| Stage | Result |
| --- | --- |
| `find src tests bin public -name '*.php' | xargs php -l` | no syntax errors |
| PHPStan (260 files, `phpstan.neon.dist`) | **[OK] No errors** |
| PHPUnit | **OK (277 tests, 22791 assertions)** in 41.6 s |

Baseline before this work was 274 tests / 22777 assertions with MySQL available. The
three registration tests add 3 tests and 14 assertions; nothing was skipped, removed or
weakened. The suite ran with the database reachable, so no test was skipped for a missing
DB (an early no-DB run skipped 95 tests and is not the comparison baseline).

PHPStan printed a startup warning about its optional turbo extension (`Dynamic loading
not supported` in this static PHP build); analysis still completed with no errors.

## 3. Browser flows — mobile (390×844)

Script: `node tools/mobile_check.mjs`, output `artifacts/verification/mobile-*.png`.

| Step | Assertion / observation | Result |
| --- | --- | --- |
| Lobby load | 54 tiles across shelves; 3 columns at 116px; tabbar `block`, header nav `none`; banner 366×150 | pass |
| Console | zero errors, zero page errors across the whole flow | pass |
| Login dialog | opens, fills `demo` / password, API 200, dialog closes, header shows `5,000.00 PHP` and "Signed in as Demo Player" | pass |
| Slot `jili.super-ace` | cabinet renders 5×3 symbols, paytable/rules/session panel, bet ladder; idle face is the game's own symbols | pass |
| Slot spin | one real `spin`; 0.20 stake, 0.14 return, balance 4,999.94; winning cells highlighted, others dimmed | pass |
| Blackjack `table.blackjack` | real `deal`: dealer 4 + hole card, player 2♠ 5♥ total 7, Hit/Stand/Double shown | pass |
| Roulette `table.roulette` | wheel ring shows all 37 pockets, felt and chip sizes fit; one straight-up bet + spin settled (`21 red`, loss) | pass |
| Roulette (earlier run) | winning straight-up bet paid 36.00, balance 5,033.94, history strip dot recorded | pass |
| Coin flip | one real `flip`; tails landed, tails face shown, loss text and count rendered | pass |
| Search sheet | "ace" returns the matching games across categories, no zero rows | pass |

Every game action settled against the real engine; balances changed by the returned
amounts.

## 4. Browser flows — desktop (1440×900)

Script: `node tools/desktop_check.mjs`, output `artifacts/verification/desktop-*.png`.

| Check | Observed | Result |
| --- | --- | --- |
| Lobby grid | 6 columns × 178.5px, 54 tiles, no horizontal overflow (`scrollWidth − clientWidth = 0`) | pass |
| Navigation | tabbar `none`, header nav `flex`, active pill follows the route | pass |
| Login | same API flow, header balance `5,032.94 PHP` | pass |
| Slot | two-column stage (cabinet + 320px side panel), full paytable | pass |
| Blackjack / roulette / coin flip | render and remain usable at width | pass |
| History | rounds and ledger show real amounts, signs and balances | pass |
| Search | inline field filters to "3 games found" | pass |
| Console | zero errors | pass |

## 5. Defects found during verification and fixed

| # | Defect | Cause | Fix |
| ---: | --- | --- | --- |
| 1 | Login/registration did nothing; `Cannot read properties of undefined (reading 'username')` | delegated `submit` listener used `event.currentTarget` (the document) instead of `event.target` (the form) | use `event.target` in both handlers |
| 2 | Invalid `pattern` attribute error in the console | HTML pattern compiles with the `v` flag; trailing `-` must be escaped | `[A-Za-z0-9._\-]{3,64}` |
| 3 | Roulette wheel had no pockets (numbers piled at the top) | `Object.assign(node.style, {'--angle': …})` does not set CSS custom properties | `style.setProperty` for `--*` in `lib/dom.js` |
| 4 | Slot header stacked art above the title with a large gap | long provider title forced a flex line break | `flex: 1 1 0` on titles, provider shortened, badges on their own row |
| 5 | Roulette badges overlapped the title | same flex sizing issue | badges `flex: 1 0 100%` |
| 6 | Tofu boxes for sound and card glyphs | emoji have no font in the verification container (and not all devices) | inline `sound` / `sound-off` SVG and a `♠` glyph |
| 7 | Provider lines truncated as "Pragmatic Play (mech…" | full provenance string used on small tiles | `providerLabel()` keeps the studio only |
| 8 | History showed every amount as 0.00 and every round as #1 | view expected `{amount:{...}}` money objects; endpoints return flat minor-unit integers and unique `round_id` | normalise both shapes, sign debits, use `round_id` |
| 9 | Roulette "No spins yet." stayed after a spin | `paintHistory()` was defined and called once, never after pushing a result | repaint after each spin |
| 10 | Coin showed a mirrored HEADS when tails landed | `filter` on the 3D coin flattened `preserve-3d`, so the backface was visible | removed the filter; shadow moved to the faces |
| 11 | Chip labels (10.00, 50.00) touched the chip border | 52px circle at 0.82rem | 58px circle at 0.76rem |
| 12 | Search sheet showed two close buttons | native `input[type=search]` cancel button plus the sheet's X | hide the native cancel in the sheet's input |
| 13 | Slot idle state was a grid of "?" | no initial face | deterministic face of the game's own symbols |

Items 1–3 were functional bugs, not cosmetic: sign-in and the roulette wheel were broken
before the fixes.

## 6. What was not tested

* Browsers other than Chromium (Safari/iOS WebKit, Firefox) — CSS uses widely supported
  features, but no device lab was available.
* Screen-reader navigation and automated axe/WCAG audit.
* Real currency, deposit or withdrawal flows — none exist.
* The administrator panel was not exercised in this browser run; it was not changed
  except by shared CSS.
* All 22 games are listed from the live catalogue and map to a working interface, but
  only four modules (one per interface) were settled with live actions. The other 18
  slots render through the exercised cabinet; they were not each individually spun in
  this run.
* Load/performance testing and production web-server (Apache/nginx) serving; the built-in
  PHP server and `public/.htaccess` were used.

## 7. Reproduce

```bash
# 1. database and PHP server
#    (start MariaDB on port 3307 if not running; see the workspace notes)
export PATH="$HOME/bin:$PATH"
php bin/console serve --host=0.0.0.0 --port=3000

# 2. server-side checks
composer check

# 3. browser checks (Playwright installed outside the repo)
export LD_LIBRARY_PATH="$HOME/chromium-libs/usr/lib/x86_64-linux-gnu"
export FONTCONFIG_FILE="$HOME/fonts.conf"
export PW_PLAYWRIGHT="file://$HOME/pw/node_modules/playwright/index.mjs"
node tools/mobile_check.mjs
node tools/desktop_check.mjs
```

Screenshots are written to `artifacts/verification/` and are intentionally not committed
(they contain generated test data).
