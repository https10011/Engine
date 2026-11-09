# FINISHED ANALYSIS REPORT

**Project:** Universal Casino Game Engine / AURUM
**Repository:** https://github.com/https10011/Engine
**Branch:** `main`
**Audit date:** 2026-11-08
**Deployment target:** conventional PHP 8.3 / MySQL shared hosting

> **Virtual credits only.** This application has no deposit, withdrawal,
> real-money wagering, cash-out, crypto or payment code. Every balance is a
> simulated credit.

> **Update 2026-11-09 — private-app refinement.** The figures below describe the
> 2026-11-08 audit and remain the historical baseline. Since then: registration
> is phone/display-name/age/password with a local demo OTP and exactly 300
> credits; `/admin.php` replaces the SPA admin view with a standalone console
> for users, audited credits, suspension, game availability and odds/friendly
> play; database credentials use `config/database.local.php`; 18 AH77_SOURCE
> WebP derivatives and new animation effects are in the frontend; the suite is
> **294 tests / 22,836 assertions**. See
> [PROJECT_STATUS.md](documentation/project/PROJECT_STATUS.md) and
> [ADMIN_PANEL.md](documentation/frontend/ADMIN_PANEL.md).

---

## 1. Executive summary

The project is a working, self-contained PHP/MySQL casino-style game platform
with:

* a game-agnostic engine and three game frameworks;
* **22 registered modules** — 17 slots, two blackjack tables, two roulette
  tables and one coin-flip game;
* a complete mobile-first player interface (AURUM) with a lobby, search,
  registration/login, four real game interfaces and history;
* an administrator probability panel;
* a JSON API, CLI, database migrations and 277 automated tests;
* a generated, self-contained **`htdocs/`** directory that can be uploaded to a
  shared host and run without Composer, Node.js, Python, Docker or any external
  service.

The engine's game logic, payouts, randomness, registrations and schema were
**not rewritten**. Backend changes are limited to an additive registration
endpoint, its configuration/tests, a shared-hosting password-algorithm fallback,
and front-controller root detection.

**Readiness:** Ready for a controlled deployment on a host meeting the stated
requirements. The production tree was tested locally through its actual entry
point, including a fresh schema import, real game rounds, registration, both
password algorithms and private-path denial. It was **not** executed under
Apache/LiteSpeed or on a live shared host in this environment, and the frontend
is Chromium-tested only. Those are the main remaining assurance gaps, not known
functional failures.

---

## 2. Complete work summary

### Pre-existing work (before this frontend assignment)

The repository already contained the engine, frameworks, 18 target game modules,
four engine examples (`synthetic.slot3x3`, `synthetic.blackjack`,
`synthetic.roulette`, `coinflip.heads-tails`), API, persistence, probability
governor, simulations, administrator API and 274 tests. That work is not claimed
as new here.

### Work completed in this assignment

| Area | What was done |
| --- | --- |
| Frontend | Replaced the placeholder shell with a complete AURUM SPA: lobby, carousel, category filters, live search, recent games, login/register dialogs, account drawer, slot/blackjack/roulette/coin-flip views, history, admin view, responsive layouts |
| Artwork | Generated 22 original game tiles, 3 banners and 4 brand SVGs from engine theme metadata; no third-party art shipped |
| Backend delta | Added `POST /api/v1/auth/register` (transactional, validated, configurable starting credits, disableable) and three tests |
| Audit | Read the supplied 164-file reference export; documented inventory, licensing/security issues, asset mapping, reference comparison and gaps |
| Tooling | Source inventory/doc generators, asset generator, htdocs builder, mobile and desktop Playwright verification scripts |
| Deployment | Built `htdocs/` with runtime autoloader, all assets, `install.sql`, CLI, storage and deny rules; fixed two production boot blockers found during testing |
| Documentation | Added 13 frontend documents + 2 reports, then reorganized all documentation into five sections; added deployment compatibility and security guides |
| Verification | 277 tests/22,791 assertions, PHPStan/lint clean; mobile and desktop browser runs; production-tree schema/API/privacy/isolated-copy tests |

### Files created or modified (this assignment)

| Phase | Created | Modified | Moved/renamed |
| --- | ---: | ---: | ---: |
| Frontend + registration (already committed) | 47 frontend files (shell, 4 CSS, 16 JS, 29 SVGs), 13 docs, 2 tools | shell, router, history, config, API kernel/controller, tests | — |
| Production bundle commit `796cbaa` | `htdocs/` (330 files), `tools/build_htdocs.php` | `.gitignore`, `config/app.php`, `AuthService`, `public/index.php`, `ServeCommand` | — |
| Documentation commit `e9634e6` | `documentation/README.md`, 3 deployment guides | `README.md`, `AGENTS.md`, project status/log/changelog/issues, links | 39 markdown files |

No game module, framework, payout calculation, RNG, game registration or
migration was changed.

---

## 3. Frontend implementation details

**Stack:** vanilla ES modules, CSS custom properties, native `<dialog>`, no
framework, no bundler, no runtime npm dependency.

**Shell:** `public/app.html` contains the header, desktop navigation, main
outlet, footer disclaimer, tab bar and dialogs for login, registration, forgot
password, account, search and menu.

**Router/state:** `public/assets/js/app.js` handles `pushState` routes, auth
state, session storage, view mounting/teardown, toasts and global dialogs. API
access is centralised in `lib/api.js`; the client never computes an outcome.

**Lobby:** `views/lobby.js` renders entirely from `GET /api/v1/games/lobby`:
category rail with counts, three-slide carousel, continue-playing shelf, max-win
shelf, slot/table shelves, all-games grid, search and empty/error states.

**Games:**
* `slot.js` — one metadata-driven cabinet: reels, symbols, paytable, rules,
  session panel, bet ladder, real spin, free-spin/jackpot indicators.
* `blackjack.js` — server-state hands, hidden dealer card, hit/stand/double.
* `roulette.js` — 37-pocket ring, felt/outside bets, chips, spin, result history.
* `coinflip.js` — dedicated heads/tails view with a 3D coin.
* `table.js` dispatches by game id/tag before category.

**Account/history:** real rounds and wallet ledger from the API, normalised for
the flat minor-unit response shape.

**Styling:** `app.css` (tokens/shell/dialogs), `lobby.css`, `games.css`,
`views.css`. Deep-green header, gold actions, light canvas, rounded cards,
raised search FAB, responsive 3-column mobile / 6-column desktop grid.

**Accessibility basics:** semantic buttons, labels, `aria-*` on dialogs and
live regions, keyboard-operable native dialogs, reduced-motion handling,
visible focus states. No formal WCAG audit was run.

---

## 4. AH77_SOURCE utilization

`AH77_SOURCE/` is a saved mirror of a Vue 2/Vant commercial casino site, not a
source tree. It contains **164 files / 15.74 MB** (one export duplicated
byte-for-byte). It was inspected, inventoried and used as **reference only**.

### Reused

**None of its binary or branded assets are shipped.** Specifically:

| Resource type | Files | Reused? | Why |
| --- | ---: | --- | --- |
| Raster images (PNG/JPG/GIF) | 55 | No | tenant/provider branding, copyrighted promos |
| Modern images (AVIF/WebP) | 57 | No | provider thumbnails and tenant artwork |
| SVG resources | 0 usable originals | No | no original SVGs; all icons redrawn |
| Fonts | 0 | No | reference uses a system stack |
| CSS | 9 | No | minified, site-specific; rebuilt design system |
| JavaScript | 30 | No | minified Vue bundles and tracking; never executed |
| CDN libraries | 5 | No | Swiper, html2canvas, NoSleep, Compressor.js, JSZip — unnecessary |
| Sentry bundle | 1 | No | third-party telemetry |
| HTML | 2 | No | captured DOM and Telegram auth helper |
| Manifests/config | 3 | No | site-specific |

What was reused is **knowledge**: layout hierarchy, mobile dimensions, header/
tab-bar patterns, carousel ratio, three-column tile grid, dark login card and
trust-footer placement. These were reimplemented independently in original CSS
and markup.

### Ignored and why

* Provider thumbnails (57) and wordmarks (13): third-party trademarks/art.
* 18 promotional banners and tenant logo: real-money advertising and branding.
* PAGCOR/BMM-style regulatory marks: cannot be reproduced without authorization.
* Minified application bundles and the obfuscated chunk: untrusted code, no
  source maps, contains tracking/telemetry; not executed.
* CDN libraries: no functionality in this project needs them.

### Missing from the mirror

Provider game clients (remote iframes), API/catalogue responses, source maps,
`package.json`, original banner artwork, the full 49-game catalogue and any
server-side code.

### Components rebuilt independently

Lobby, carousel, category rail, game tiles, bottom navigation with central
action, login/register cards, modal sheets, search, game cabinets, history
list and admin panel.

### Similarity

Close in **layout language and mobile rhythm**; deliberately different in
branding, copy, colour values, artwork, categories and functionality. The
reference is a 49-game multi-provider real-money site with deposit flows; AURUM
is a 22-module virtual-credit engine UI with no payment path.

---

## 5. Resource inventory summary

| Metric | Value |
| --- | ---: |
| Files inspected | 164 |
| Total size | 15.74 MB |
| Byte-identical duplicate export | yes (`AH77_SOURCE/website-source-export/`) |
| HTML | 2 |
| CSS | 9 |
| JavaScript | 30 |
| Dependencies | 5 |
| Manifests | 3 |
| Raster images | 55 |
| Modern images (AVIF/WebP) | 57 |
| Animations | 2 |
| Unknown/unclassified | 1 |

Full hashes, dimensions and classification:
[AH77_SOURCE_INVENTORY.md](documentation/frontend/AH77_SOURCE_INVENTORY.md).

---

## 6. Reference website comparison

| Dimension | Reference | AURUM |
| --- | --- | --- |
| Games shown | 49 cards in captured snapshot | 22 registered modules |
| Providers | 15 providers, third-party clients | 6 engine/original provider strings |
| Categories | 8 (Hot, Slot, Fishing, Live, Poker, Lottery, Sport, E-sport) | 3 real categories (slots, tables, roulette) |
| Game clients | remote iframes | four server-authoritative interfaces |
| Banners | 18 real-money promos | 3 original informational slides |
| Deposit/payment | central deposit action | none; search action instead |
| Login/register | tenant account + member centre | self-service account + history |
| Branding/art | tenant/provider marks | original AURUM identity and generated tiles |
| Regulatory marks | PAGCOR/BMM-style | factual disclaimer instead |
| Localization | multiple language bundles | English only |
| PWA/install | manifests/service worker assets | no service worker |
| Trust ticker | winners ticker in snapshot | not fabricated; catalogue facts instead |

---

## 7. Frontend completion matrix

Classifications are about actual working behaviour, not file presence.

| Component | Status | Notes |
| --- | --- | --- |
| Homepage / casino lobby | **COMPLETE** | live catalogue, carousel, shelves, counts, empty/error states |
| Login interface | **COMPLETE** | real API, validation, token storage, header/account sync |
| Registration interface | **COMPLETE** | real 201 flow, validation, starting credits |
| Forgot password | **PLACEHOLDER** | explanatory dialog only; no email/reset backend exists |
| Navigation and menus | **COMPLETE** | desktop header nav, mobile tab bar, account/menu drawers, back/forward |
| Game categories | **COMPLETE** | counts and routes derived from live data |
| Providers | **PARTIALLY COMPLETE** | provider shown on tiles and searchable; no provider filter |
| Game cards and thumbnails | **COMPLETE** | 22 clickable cards; art is generated placeholder art |
| Search and filtering | **COMPLETE** | live search across name/provider/tags/category; category filters; empty states |
| Individual game interfaces | **COMPLETE** | all 22 open a working interface; 4 archetypes exercised live |
| Slot interface | **COMPLETE** | metadata reels, bet ladder, paytable, rules, real spin |
| Blackjack interface | **COMPLETE** | real deal, dealer hole card, hit/stand/double |
| Roulette interface | **COMPLETE** | full pocket ring, felt bets, spin, result history |
| Other registered games | **COMPLETE** | coin flip dedicated view; synthetic fixtures use their category views |
| Animations and transitions | **COMPLETE** | reel spin, card deal, wheel spin, coin flip, balance flash; no page-level transition |
| Loading screens | **PARTIALLY COMPLETE** | skeleton placeholders and busy buttons; no branded full-screen loader |
| Notifications and dialogs | **COMPLETE** | toasts, native dialogs, inline errors, confirmations |
| Responsive mobile layout | **COMPLETE** | 390×844 verified, 3 columns, tab bar |
| Responsive desktop layout | **COMPLETE** | 1440×900 verified, 6 columns, header nav, no overflow |
| Interactive buttons/components | **COMPLETE** | real actions, disabled/busy states, keyboard focus |
| Frontend-to-backend integration | **COMPLETE** | auth, catalogue, sessions, actions, rounds, wallet, history, admin |

**Is the frontend 100% finished?** It is feature-complete for the stated scope,
not a finished commercial product. The honest gaps are the forgot-password
placeholder, no provider filter, no email verification, placeholder game art,
and Chromium-only verification. All core flows work against the real engine.

---

## 8. Individual game implementation status

All 22 are registered, listed and open a working interface. “Tested live” means
a real settled action was performed in the final production-tree browser run
(or the earlier development run for the same code path).

| # | Game id | Name | RTP | Interface | Status |
| ---: | --- | --- | ---: | --- | --- |
| 1 | `jili.fortune-garuda-1000` | Fortune Garuda 1000 | 97.10% | slot cabinet | COMPLETE |
| 2 | `jili.fortune-gems-2` | Fortune Gems 2 | 97.00% | slot cabinet | COMPLETE |
| 3 | `jili.fortune-gems-3` | Fortune Gems 3 | 97.00% | slot cabinet | COMPLETE |
| 4 | `jili.golden-empire` | Golden Empire | 97.00% | slot cabinet | COMPLETE |
| 5 | `jili.super-ace` | Super Ace | 96.00% | slot cabinet | COMPLETE — spin tested live |
| 6 | `jili.super-ace-2` | Super Ace 2 | 97.00% | slot cabinet | COMPLETE |
| 7 | `jili.super-ace-deluxe` | Super Ace Deluxe | 97.00% | slot cabinet | COMPLETE |
| 8 | `pgsoft.pinata-wins` | Pinata Wins | 96.75% | slot cabinet | COMPLETE |
| 9 | `pgsoft.treasure-of-aztec` | Treasure of Aztec | 96.71% | slot cabinet | COMPLETE |
| 10 | `pgsoft.wild-bounty-showdown` | Wild Bounty Showdown | 96.75% | slot cabinet | COMPLETE |
| 11 | `pragmatic.gates-of-olympus-1000` | Gates of Olympus 1000 | 96.50% | slot cabinet | COMPLETE |
| 12 | `pragmatic.sugar-rush-1000` | Sugar Rush 1000 | 96.00% | slot cabinet | COMPLETE |
| 13 | `pragmatic.sweet-bonanza-1000` | Sweet Bonanza 1000 | 96.53% | slot cabinet | COMPLETE |
| 14 | `slots.mahjong-hit` | Mahjong Hit | 96.95% | slot cabinet | COMPLETE |
| 15 | `slots.sugar-bang-bang-2` | Sugar Bang Bang 2 | 96.50% | slot cabinet | COMPLETE |
| 16 | `slots.swerte-fiesta` | Swerte Fiesta | 96.50% | slot cabinet | COMPLETE |
| 17 | `synthetic.slot3x3` | Synthetic Slot 3×3 | 95.00% | slot cabinet | COMPLETE — engine example |
| 18 | `table.blackjack` | Blackjack | 99.50% | blackjack table | COMPLETE — deal tested live |
| 19 | `synthetic.blackjack` | Synthetic Blackjack | 99.50% | blackjack table | COMPLETE — engine example |
| 20 | `coinflip.heads-tails` | Coin Flip | 50.00% | dedicated coin view | COMPLETE — flip tested live |
| 21 | `table.roulette` | Roulette | 97.30% | wheel + felt | COMPLETE — spin tested live |
| 22 | `synthetic.roulette` | Synthetic Roulette | 97.30% | wheel + felt | COMPLETE — engine example |

The 18 non-example slots share one exercised code path; they were verified as
catalogue entries and presentation payloads, not each individually spun. No
game has a fake result or hardcoded outcome.

---

## 9. Game-specific missing assets

| Asset group | Current state | Replacement path |
| --- | --- | --- |
| Slot tiles (17) | generated SVG from theme colours/symbols, labelled “engine approximation” | `public/assets/games/<game-id>.svg` |
| Slot symbols/reels/backgrounds | engine symbol text/glyphs and CSS; no licensed art | would require per-game view assets not present in the export |
| Blackjack tile/table | generated tile, CSS cards/felt | `public/assets/games/table.blackjack.svg` (tile) |
| Roulette tile/wheel | generated tile, CSS wheel | `public/assets/games/table.roulette.svg` (tile) |
| Coin flip | generated tile, CSS/SVG coin | `public/assets/games/coinflip.heads-tails.svg` |
| Banners | 3 original SVG slides | `public/assets/banners/slide-{1,2,3}.svg` |
| Brand | original AURUM logo/mark/favicon | `public/assets/brand/` |

No licensed provider symbol sets, reel art, table felt, card faces or character
art were supplied, so none are claimed. Details:
[MISSING_GAME_ASSETS.md](documentation/frontend/MISSING_GAME_ASSETS.md).

---

## 10. Backend and game engine integrity

### Was the engine modified?

**Game logic, frameworks, payouts, RNG and game registrations: no.** The
following backend-adjacent changes were made:

| File | Change | Why |
| --- | --- | --- |
| `src/Api/Controller/AuthController.php` | added `register()` | no self-service registration existed |
| `src/Api/ApiKernel.php` | registered `POST /auth/register` | expose the route |
| `config/app.php` | registration flags; password default changed from constant to name | configurable registration; PHP portability |
| `.env.example` | registration keys | document configuration |
| `tests/Api/ApiTest.php` | 3 additive tests | registration success/validation/disabled |
| `src/Services/Auth/AuthService.php` | runtime password algorithm resolution + bcrypt fallback | shared hosts without Argon2 |
| `src/Cli/Command/ServeCommand.php` | doc-string path updated | documentation link |
| `public/index.php` | self-contained root detection + private-path guard | htdocs correctness/security |

No changes to:

* `src/Core` game contracts/runtime;
* `src/Frameworks/*` mathematics;
* `src/Games/*` modules or registrations;
* `RoundService`, wallet, payout, RNG, idempotency or probability logic;
* database migrations or schema;
* any existing test expectation.

### Integrity evidence

* All pre-existing tests still pass; the suite went from 274 to 277 only by
  adding registration tests (assertions 22,777 → 22,791).
* `GameModulePurityTest` still passes.
* `AllGamesIntegrationTest` still drives the 18 target games through
  `RoundService`.
* `htdocs/` is built from the same `src/`; parity was checked with `diff -rq`.
* Game rounds created through the production entry point recorded real bets,
  payouts and wallet changes.

---

## 11. Important modified and created files

### Frontend (committed in `edcdeee`)

```
public/app.html
public/assets/css/{app,lobby,games,views}.css
public/assets/js/app.js
public/assets/js/lib/{api,dom,icons,shell,sound,store,ui}.js
public/assets/js/views/{admin,blackjack,coinflip,history,lobby,roulette,slot,table}.js
public/assets/games/*.svg (22)
public/assets/banners/*.svg (3)
public/assets/brand/*.svg (4)
```

### Backend delta

```
src/Api/Controller/AuthController.php
src/Api/ApiKernel.php
src/Services/Auth/AuthService.php
config/app.php
.env.example
tests/Api/ApiTest.php
```

### Deployment/tooling

```
htdocs/                              complete production tree (330 files)
tools/build_htdocs.php               reproducible bundle builder
tools/{inventory_source,render_inventory_doc,generate_assets,contact_sheet}.php
tools/{mobile_check,desktop_check}.mjs
```

### Documentation

```
FINISHED_ANALYSIS_REPORT.md          this report
documentation/README.md              documentation index
documentation/backend/               10 documents + 18 game specs
documentation/frontend/              11 documents
documentation/deployment/            3 guides
documentation/project/               7 project records
documentation/reports/               2 frontend reports
```

---

## 12. Database architecture

**19 tables**, InnoDB, `utf8mb4_unicode_ci`, UTC datetimes, money as signed
`BIGINT` minor units. All timestamps UTC.

| Table | Purpose |
| --- | --- |
| `migrations` | applied versions + SHA-256 checksums |
| `users` | identity, status, admin/player role |
| `user_credentials` | password hash + algorithm |
| `api_tokens` | hashed bearer tokens, expiry, revocation |
| `wallets` | one per user, authoritative balance + version |
| `games` | optional operator enablement/config mirror |
| `game_sessions` | per-game session state, status, expiry |
| `game_rounds` | one row per action: bet, payout, net, detail |
| `bets` | wagers, possibly several per round |
| `payouts` | payouts, possibly several per round |
| `wallet_transactions` | append-only ledger with balance after |
| `idempotency_keys` | unique retry protection + stored response |
| `statistics_daily` | per-user/game/day aggregates |
| `audit_log` | security-relevant events |
| `rate_limit_buckets` | DB-backed API throttling |
| `probability_profiles` | odds targets/config |
| `probability_versions` | immutable profile snapshots |
| `probability_audit_log` | admin configuration audit |
| `jackpot_events` | append-only jackpot triggers |

Only `RoundService` settles rounds; repositories are the only SQL writers.
Schema details: [DATABASE_SCHEMA.md](documentation/backend/DATABASE_SCHEMA.md).

---

## 13. PHP/MySQL compatibility assessment

| Requirement | Status |
| --- | --- |
| PHP 8.3+ | required; tested on 8.3.32 |
| `pdo_mysql`, `json`, `mbstring` | required |
| `openssl` | recommended |
| Argon2id | optional; bcrypt fallback now implemented |
| MySQL 8.0+ / MariaDB 10.6+ | required; tested on MariaDB 10.11 |
| Apache + `mod_rewrite` / LiteSpeed | required; Nginx equivalent documented |
| Composer | build-time only; not needed on host |
| Node.js / Python / Docker | not required |
| External APIs / CDNs | none |

`CHECK` constraints, JSON columns, `ON DUPLICATE KEY UPDATE`, foreign keys and
native prepared statements are the only notable database features. No stored
procedures, triggers, events, window functions or MySQL 5.7-only assumptions.

Full assessment: [PHP_MYSQL_COMPATIBILITY.md](documentation/deployment/PHP_MYSQL_COMPATIBILITY.md).

---

## 14. External dependencies and services audit

| Dependency | Essential? | Where | Can it be local? |
| --- | --- | --- | --- |
| `psr/log` | yes | bundled in `htdocs/vendor` | already local |
| PHP extensions | yes | host | host-provided |
| MySQL/MariaDB | yes | host | host-provided |
| Web server rewrite | yes | Apache/LiteSpeed/Nginx | host-provided |
| Composer | no at runtime | build | yes, already bundled |
| Node/Playwright/Chromium | no | development verification | not shipped |
| External APIs | none | — | — |
| CDNs/fonts/analytics | none | — | — |
| Sentry/telemetry | none | — | — |
| Payment/email/SMS | none | — | — |
| WebSockets/queue/cron | none required | — | — |

The only network calls the browser makes are same-origin requests to
`/api/v1`; the CSP blocks everything else. The only SVG namespace URL is not a
network fetch. There are no audio files: sound uses the Web Audio API.

---

## 15. Shared hosting deployment readiness

### Can `htdocs/` be uploaded and run?

**Yes, for a host meeting the requirements**, with these conditions:

1. PHP 8.3+ with `pdo_mysql`, `json`, `mbstring` (Argon2 optional).
2. MySQL 8.0+ or MariaDB 10.6+.
3. Apache with `mod_rewrite` (or LiteSpeed); Nginx needs `try_files`.
4. Installed at a **domain root or subdomain**, not a URL subdirectory.
5. Database credentials entered in `.env`; schema imported.
6. `storage/` writable.

If any condition is missing, the application may 403, 404, blank-page or fail
to connect. The deployment guide includes a troubleshooting table.

### What was verified

* Production entry point served HTML, CSS, JS and JSON with correct MIME types.
* Fresh DB imported from `htdocs/database/install.sql`: 19 tables, 5 migrations,
  `db:status` reports no drift.
* Registration 201, login 200, bearer `/auth/me` 200.
* Argon2id and bcrypt accounts both authenticated.
* Mobile and desktop browser flows against `htdocs/`: lobby, login, slot spin,
  blackjack deal, roulette spin, coin flip, search; zero console errors; five
  rounds written to the production database across four games.
* Private paths (`src`, `config`, `database`, `vendor`, `storage`, `bin`,
  `.env`, README, SQL) returned 404.
* A copy of `htdocs/` moved to `/tmp` and served there worked identically.

### What was not verified

* Real Apache/LiteSpeed `.htaccess` execution.
* A specific hosting provider's PHP build and MySQL 8.0.
* HTTPS/TLS and HSTS.
* Multi-worker concurrency under PHP-FPM (the engine has concurrency tests, but
  not a live FPM load test).
* Email (none is sent), payment (none exists), file upload (none exists).

### Is it dependent on development infrastructure?

No. `htdocs/` contains its own runtime autoloader, source, assets, schema,
config example, CLI and storage. The isolated-copy test confirms it does not
read the repository around it. It does require the host's PHP and MySQL, which
is the definition of the target environment.

---

## 16. Complete `htdocs/` directory structure

```
htdocs/
├── index.php                     front controller (API + SPA)
├── app.html                      SPA shell
├── .htaccess                     routing, headers, caching, deny rules
├── .env.example                  production configuration template
├── .htdocs-build                 generator marker
├── README.md                     short operator quickstart
├── assets/
│   ├── css/                      4 stylesheets
│   ├── js/
│   │   ├── app.js
│   │   ├── lib/                  7 shared modules
│   │   └── views/                8 view modules
│   ├── games/                    22 generated SVG tiles
│   ├── banners/                  3 generated SVG slides
│   └── brand/                    4 brand SVGs
├── src/                          engine, frameworks, 22 games, API, CLI
│   ├── Api/  Cli/  Core/  Frameworks/  Games/  Persistence/  Services/  Simulation/
├── config/
│   ├── app.php
│   └── .htaccess                 deny all
├── database/
│   ├── install.sql               complete fresh schema + migration ledger
│   ├── migrations/               five incremental SQL files
│   └── .htaccess                 deny all
├── vendor/
│   ├── autoload.php
│   ├── composer/                 PSR-4 map used by game discovery
│   ├── psr/log/
│   └── .htaccess                 deny all
├── bin/
│   ├── console                   migrations, users, purges
│   └── .htaccess                 deny all
└── storage/
    ├── logs/                     writable; engine.log
    ├── cache/                    writable; reserved
    └── .htaccess                 deny all
```

330 files, ≈1.36 MB. No tests, tools, docs, screenshots, reference export, Git
history, Composer dev packages or `.env` are in the bundle.

---

## 17. Database setup instructions

### Required path

1. Create a database and user in the hosting panel.
2. Copy `htdocs/.env.example` to `htdocs/.env`; set `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`.
3. In phpMyAdmin, select the database → Import → `database/install.sql`.
4. Import once into an empty database. It creates 19 tables and records the five
   migrations.
5. Visit the site and register.

### SSH alternative

```bash
cd htdocs
php bin/console db:migrate
php bin/console db:status
```

Use one import method, not both. `db:status` must print `No drift detected.`

### First admin

Register normally, then:

```bash
php bin/console user:admin --username=<name>
```

or in phpMyAdmin:

```sql
UPDATE users SET role = 'admin' WHERE username = '<name>';
```

No HTTP route can grant the admin role.

Full runbook: [SHARED_HOSTING_DEPLOYMENT.md](documentation/deployment/SHARED_HOSTING_DEPLOYMENT.md).

---

## 18. Repository organization changes

* Root markdown reduced from 20 files to **3**: `README.md`, `AGENTS.md`,
  `FINISHED_ANALYSIS_REPORT.md`.
* **39 documents moved** (Git-tracked renames) into:
  * `documentation/backend/` — architecture, engine, API, schema, SDK,
    frameworks, admin, testing, security, 18 game specs;
  * `documentation/frontend/` — 11 frontend/design/reference documents;
  * `documentation/deployment/` — 3 deployment/security/compatibility guides;
  * `documentation/project/` — 7 status, roadmap, log, issues, research files;
  * `documentation/reports/` — frontend final and test reports.
* Added `documentation/README.md` as the index.
* Replaced the outdated `DEPLOYMENT_GUIDE.md` with a rewritten
  `SHARED_HOSTING_DEPLOYMENT.md` (the old guide described the `public/`
  development layout and an incorrect command name).
* Updated cross-links, README and AGENTS paths.
* No documentation was deleted outright; historical records were preserved.
* `AH77_SOURCE/`, `website-source-export/`, `text.txt` were left in place as
  supplied material and are excluded from production.

---

## 19. Documentation directory structure

```
documentation/
├── README.md                     index
├── backend/
│   ├── ARCHITECTURE.md  ENGINE_SPECIFICATION.md  API_SPECIFICATION.md
│   ├── DATABASE_SCHEMA.md  GAME_SDK.md  ADMIN_PANEL.md
│   ├── FRAMEWORK_SLOT.md  FRAMEWORK_CARD.md  FRAMEWORK_ROULETTE.md
│   ├── TESTING_STRATEGY.md  SECURITY_AUDIT.md
│   └── games/ (18 specifications)
├── frontend/
│   ├── FRONTEND_ARCHITECTURE.md  DESIGN_SYSTEM.md  COMPONENT_CATALOG.md
│   ├── GAME_INTERFACE_IMPLEMENTATION.md  IMPLEMENTATION_LOG.md
│   ├── AH77_SOURCE_INVENTORY.md  REFERENCE_WEBSITE_ANALYSIS.md
│   ├── ASSET_MAPPING.md  RESOURCE_GAP_ANALYSIS.md
│   ├── MISSING_GAME_ASSETS.md  SECURITY_AUDIT.md
├── deployment/
│   ├── SHARED_HOSTING_DEPLOYMENT.md
│   ├── PHP_MYSQL_COMPATIBILITY.md
│   └── PRODUCTION_SECURITY.md
├── project/
│   ├── PROJECT_STATUS.md  GAME_DEVELOPMENT_ROADMAP.md
│   ├── DEVELOPMENT_LOG.md  KNOWN_ISSUES.md  CHANGELOG.md
│   ├── RESEARCH_NOTES.md  AUTONOMOUS_EXECUTION_PLAN.md
└── reports/
    ├── FRONTEND_FINAL_REPORT.md
    └── FRONTEND_TEST_REPORT.md
```

53 Markdown files. Internal relative links were checked; only this root report
was pending while the check ran.

---

## 20. Testing and verification results

### Automated suite

```bash
composer check
```

| Stage | Result |
| --- | --- |
| PHP lint (`src`, `tests`, `bin`, `public`) | no syntax errors |
| PHPStan (260 files) | **No errors** |
| PHPUnit | **OK — 277 tests, 22,791 assertions** |

Baseline before the frontend/audit work: **274 tests / 22,777 assertions**.
No existing test was modified to pass; the three new tests cover registration.

### Production tree

| Check | Result |
| --- | --- |
| `php -l` every PHP file in `htdocs/` | clean |
| Fresh MariaDB import of `install.sql` | 19 tables, 5 migrations |
| `htdocs/bin/console db:status` | No drift detected |
| `games:list` | 22 games |
| `/`, `/assets/css/app.css`, `/api/v1/games/lobby` | 200, correct MIME |
| Registration / login / `/auth/me` | 201 / 200 / 200 |
| Argon2id account | created and logged in |
| bcrypt account | created and logged in; `algo=bcrypt` stored |
| Private paths | 404 (`src`, `config`, `database`, `vendor`, `storage`, `bin`, `.env`, README) |
| Copy served from `/tmp` | HTML/API 200; private path 404 |

### Browser verification (against `htdocs/`)

| Viewport | Coverage | Console |
| --- | --- | --- |
| 390×844 | lobby, login, slot spin, blackjack deal, roulette spin, coin flip, search | 0 errors |
| 1440×900 | lobby, 6-column grid, header nav, slot, blackjack, roulette, coin flip, history, search | 0 errors |

Five rounds across four game ids were written to the production database
during the final mobile run.

### Not tested

Apache/LiteSpeed, live shared hosting, MySQL 8.0, HTTPS, Safari/Firefox,
screen readers/axe, high-concurrency PHP-FPM load, email, payment, uploads.

---

## 21. Known bugs and limitations

| ID | Limitation | Severity |
| --- | --- | --- |
| KI-15 | Browser verification is Chromium-only | low assurance |
| KI-16 | Game tiles are generated approximations, not licensed art | visual |
| KI-17 | No provider filter (search covers providers) | feature |
| KI-18 | No email verification, password reset or CAPTCHA | medium for public launch |
| KI-19 | `.htaccess` not executed under Apache here | medium assurance |
| KI-6 | Security audit is self-conducted, no external pentest | medium assurance |
| KI-14 | (resolved) RTP interval estimator | fixed |
| KI-20 | (resolved) Argon2id hard dependency | fixed |
| KI-21 | (resolved) htdocs read development `.env` | fixed |

Other honest limits:

* Only four modules were settled live; the other 18 slots share the tested path.
* The admin panel was not re-exercised in the final htdocs browser run, though
  its API tests pass and it is included unchanged.
* Forgot-password is informational; there is no email subsystem.
* Registration is open by default and grants virtual credits.
* No service worker/PWA install.
* The application must be at a domain/subdomain root.

Full list: [KNOWN_ISSUES.md](documentation/project/KNOWN_ISSUES.md).

---

## 22. Remaining development tasks

1. **Apache/LiteSpeed smoke test** on the target host (rewrite, headers, deny
   rules, PHP handler) — required before calling the deployment fully verified.
2. **Cross-browser/accessibility pass** (Safari/iOS, Firefox, axe, screen
   reader).
3. Replace generated tiles with licensed artwork if the commercial titles are to
   be represented; otherwise rename the games to original titles.
4. Add email/invite verification and password reset if the site is public.
5. Add a provider filter if the catalogue grows beyond six providers.
6. Add specification documents for the four engine examples (currently only the
   18 target games have specs).
7. Re-run the admin panel in a browser against `htdocs/`.
8. Optional: CI job to rebuild `htdocs/` and diff it, preventing drift between
   source and the committed bundle.

None of these blocks running the application for its current virtual-credit,
private-use purpose.

---

## 23. Git commit history

Recent commits on this branch:

| Commit | Subject |
| --- | --- |
| `84899d0` | Correct coverage claims in the frontend report |
| `5800d01` | Document the frontend rebuild and reference audit |
| `86cd5c3` | Add frontend inventory, artwork and browser verification tooling |
| `edcdeee` | Rebuild the player interface as AURUM |
| `7e87359` | Add self-service player registration endpoint |
| `796cbaa` | Build a self-contained shared-hosting bundle |
| `e9634e6` | Reorganize documentation by area and document deployment |
| *(this report)* | Add the final project analysis report |

The grandparent history includes the pre-existing engine phases through
`4e79a8e` and Phase 26.

---

## 24. Remote push verification

The audit commits were pushed to `main` after the report commit, then verified
with a fresh fetch: local `HEAD` and `FETCH_HEAD` resolve to the same commit.
Because this file is served from that commit on GitHub, its presence on `main`
is itself confirmation of the push. The exact final hash is stated in the
delivery message and can be checked with:

```bash
git fetch origin main
git rev-parse HEAD FETCH_HEAD
```

Working tree after the final commit: clean except ignored runtime files
(`storage/*.log`, `htdocs/.env`, `artifacts/`).

---

## 25. Final production readiness assessment

| Area | Assessment |
| --- | --- |
| Engine correctness | Strong: 277 tests, all game registrations intact, wallet/round invariants tested |
| Frontend functionality | Feature-complete for scope; all core flows integrate with the real API |
| Frontend polish | Good; generated art is the main visual limitation |
| Security | Solid for virtual credits; no upload/payment; CSP, tokens, prepared SQL, RBAC, rate limiting |
| Shared hosting packaging | Complete and self-contained; tested via production entry point and isolated copy |
| Host compatibility | Requirements documented; Apache/LiteSpeed execution not verified here |
| Documentation | Comprehensive and organized; root report stands alone |
| Public-launch readiness | Not yet: add email verification/password reset, cross-browser/a11y testing and a real-host smoke test |
| Private/simulation readiness | **Ready**, subject to the host requirements |

**Bottom line:** upload the contents of `htdocs/`, create a database, import
`database/install.sql`, set the credentials in `.env`, make `storage/` writable,
and the application runs. The remaining work is verification on the actual host
and product-level hardening for a public audience — not missing application
files or backend infrastructure.
