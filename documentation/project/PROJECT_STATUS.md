# Project Status

> Single-page truth about where the project stands.
> Last updated: 2026-11-09 — private-app refinement, phone/demo-OTP registration, database.local config, standalone admin console, AH77 art.

---

## Current phase

**Engine, 22 registered games, player frontend, standalone administrator
console and the shared-hosting deployment bundle are complete.**

The 18 target games (16 slots, blackjack, roulette) are implemented and verified
end to end through `RoundService`; four engine examples (`coinflip.heads-tails`,
`synthetic.slot3x3`, `synthetic.blackjack`, `synthetic.roulette`) bring the live
catalogue to **22 modules**. The AURUM player interface covers every module with
four interface types.

Players now register with phone, display name, age and password; the phone is
canonicalised and unique, the account receives exactly 300 credits once, and a
local demo OTP step shows a browser notification (no SMS). Google/Facebook
buttons are presentation-only. `/admin.php` is a separate server-rendered
console with users, audited credits/status, game availability, odds and
friendly-play controls.

Verification is **294 tests / 22,836 assertions**, with PHP lint and PHPStan
clean. A self-contained `htdocs/` tree was built, served from a copy at `/tmp`
with only `config/database.local.php` (no `.env`), imported into a fresh
database, and exercised through the production entry point including the admin
console.

Current deployment target: conventional PHP 8.3 / MySQL shared hosting. Use
`documentation/deployment/SHARED_HOSTING_DEPLOYMENT.md`, not the old web-root
layout notes in the historical sections below.

---

## Environment (verified in this container)

| Component | Version | Command used |
| --- | --- | --- |
| PHP CLI | 8.3.32 | `php -v` |
| PHP extensions | pdo_mysql, mbstring, json, openssl, argon2 | `php -m` |
| MySQL-compatible server | MariaDB 10.11 (port 3307 in this container) | `mariadbd --version` |
| Composer | 2.11.1 | `composer --version` |
| Databases created | `casino_engine`, `casino_engine_test`, `casino_engine_htdocs` | `CREATE DATABASE` |
| Application DB user | `engine@127.0.0.1` | `GRANT` |

Code is written for PHP 8.3+ (`composer.json` requires `>=8.3`); this container
runs 8.3.32. The frontend was verified with Chromium 131 via Playwright.
Production target is conventional PHP/MySQL shared hosting; see
[SHARED_HOSTING_DEPLOYMENT.md](../deployment/SHARED_HOSTING_DEPLOYMENT.md).

---

## Repository state

| Item | Value |
| --- | --- |
| Repository | `/workspace/project/Engine` |
| Branch | `main` |
| Remote | `origin` (GitHub, `https10011/Engine`) |
| Pre-existing content | `text.txt` only (essentially an empty repository) |
| Shallow clone | yes — `git fetch --unshallow` if full history is ever needed |
| Source files | 181 PHP files under `src/`, 18 under `tests/` |

---

## Phase status board

| Phase | Title | Status |
| --- | --- | --- |
| 0 | Repository inspection and autonomous planning | **Complete** |
| — | Internet research and game mechanics discovery | **Complete** (`RESEARCH_NOTES.md`) |
| 1 | Project foundation | **Complete** |
| 2 | Universal engine core | **Complete** |
| 3 | Shared engine services | **Complete** |
| 4 | Game SDK and module system | **Complete** |
| 5 | Slot game framework | **Complete** |
| 6 | Card game framework | **Complete** |
| 7 | Roulette framework | **Complete** |
| 8 | Database and persistence | **Complete** |
| 9 | HTTP/JSON API | **Complete** |
| 10 | Synthetic game integration | **Complete** |
| 11 | Simulation and mathematical testing | **Complete** |
| 12 | Security, reliability and performance audit | **Complete** (`SECURITY_AUDIT.md`) |
| 13 | Documentation and developer experience | **Complete** |
| 14 | Final verification and completion | **Complete** (published) |
| 15–26 | Target games, jackpot, admin panel, publication | **Complete** (see below) |
| 27 | Player frontend, audit and shared-hosting package | **Complete** (`htdocs/`, root report) |

---

## Deliverables completed

Documentation (all present and non-empty):

| File | Purpose |
| --- | --- |
| `README.md` | Project overview, documentation map, quick start |
| `AUTONOMOUS_EXECUTION_PLAN.md` | Full phase plan, acceptance criteria, risks |
| `PROJECT_STATUS.md` | This file |
| `DEVELOPMENT_LOG.md` | Phase-by-phase engineering log with evidence |
| `ARCHITECTURE.md` | Layering, round flow, ADR log |
| `ENGINE_SPECIFICATION.md` | Core contracts and runtime semantics |
| `GAME_SDK.md` | How to build a new game module |
| `FRAMEWORK_SLOT.md` | Slot framework reference |
| `FRAMEWORK_CARD.md` | Card framework reference |
| `FRAMEWORK_ROULETTE.md` | Roulette framework reference |
| `DATABASE_SCHEMA.md` | Tables, keys, indexes, migrations |
| `API_SPECIFICATION.md` | Endpoints, payloads, errors |
| `TESTING_STRATEGY.md` | Test layers and how to run them |
| `documentation/deployment/SHARED_HOSTING_DEPLOYMENT.md` | Shared-hosting deployment |
| `SECURITY_AUDIT.md` | Audit findings and fixes |
| `RESEARCH_NOTES.md` | Game mechanics research with evidence tags |
| `KNOWN_ISSUES.md` | Honest limitation list |
| `CHANGELOG.md` | Change history |

Implementation (highlights):

* `src/Core/` — engine bootstrap, registry, discovery, runtime, lifecycle,
  session/state/result contracts, events, errors, config, logging, `Money` and
  `Ratio` value objects.
* `src/Services/` — RNG (CSPRNG + seeded), wallet, betting, payout, session,
  round orchestration, statistics, history, idempotency, transactions, auth.
* `src/Frameworks/Slot/` — reels, symbols, grid generation, pluggable win
  evaluators (payline, ways, cluster, scatter, composite), cascades, free spins,
  multipliers.
* `src/Frameworks/Card/` — card/deck/hand, blackjack rules, settlement.
* `src/Frameworks/Roulette/` — variants, bet factory, settlement.
* `src/Persistence/` — PDO connection, migrations, repositories.
* `src/Api/` — router, middleware, thin controllers, JSON envelope.
* `src/Cli/` — console commands and the dev server.
* `src/Simulation/` — driver, statistics, `bin/engine-simulate`.
* `src/Games/` — the eighteen target games (16 slots under `src/Games/Slots/`,
  blackjack and roulette under `src/Games/Table/`), three synthetic fixtures and
  a documented SDK example.
* `src/Services/Probability/` — probability configuration service, profile
  value object and win-frequency governor, reachable by an authorised
  administrator through the `/api/v1/admin/*` routes and the standalone
  `/admin.php` console.
* `public/app.html`, `public/assets/css/`, `public/assets/js/` — the player
  frontend (lobby, catalog, slot, blackjack, roulette, history). `public/admin.php`
  and `public/assets/{css,js}/admin.*` are the separate operator console.

---

## Test status

```
composer check  =>  lint OK, PHPStan OK, phpcs OK, 273 tests / 22770 assertions OK
```

`composer test` runs the whole suite (unit, framework, games, integration,
transaction, API, simulation). Integration tests skip cleanly when no database
is reachable; in this environment they ran against MariaDB. Layer-by-layer
detail is in `TESTING_STRATEGY.md` and the `DEVELOPMENT_LOG.md` entries.

| Suite | Tests |
| --- | --- |
| `unit` | 46 |
| `framework` | 56 |
| `simulation` | 12 |
| `integration` | 31 |
| `transaction` | 10 |
| `api` | 54 |
| `games` (blackjack, roulette, slot jackpot wiring) | 64 |
| **Total** | **273** |

---

## Known blockers

None. Every tool needed for the remaining phases is installed and working.

---

## Publication record

| Item | Value |
| --- | --- |
| Engine completion commit | `8cdee06968bac1451486baa0aba00703b6992ab9` |
| Phase 21–22 commit | `d0ee39d` — blackjack and roulette table games |
| Phase 23 commit | `15478eb` — all 18 games integrated, multiplier columns widened |
| Phase 24–25 commit | `4696202` — frontend SPA, admin panel, jackpot settlement |
| Phase 26 commit | `b356240` — final verification, KI-13 and KI-14 fixed |
| Remote branch | `main` (origin) → `https://github.com/https10011/Engine.git` |
| Push verified | yes — `git ls-remote origin main` = `b356240da19c41d1cb6e75f1827859d77d521a78` |
| Verified at | Phase 26 audit, 2026-11-08 |

Each directive phase is committed and pushed to `origin/main` as it completes.
The engine's own completion commit is kept here for continuity with the earlier
record.


---

## Phase 15 onward — the 18 named games, frontend and admin panel

The engine (phases 0–14) is complete. Work continues against it under the
directive's phase numbering; `GAME_DEVELOPMENT_ROADMAP.md` is the authoritative
plan and status board.

| Phase | Deliverable | Status |
| --- | --- | --- |
| 15 | Repository audit, roadmap, 18 individual game specifications | **Complete** (`documentation/backend/games/`, `GAME_DEVELOPMENT_ROADMAP.md`) |
| 16 | Shared infrastructure extensions (probability service, slot features, admin role migration) | **Complete** |
| 17 | Slots batch 1 — Super Ace, Piñata Wins, Wild Bounty Showdown, Mahjong Hit | **Complete** |
| 18 | Slots batch 2 — Golden Empire, Sugar Bang Bang 2, Fortune Gems 3, Swerte Fiesta | **Complete** |
| 19 | Slots batch 3 — Super Ace Deluxe, Fortune Garuda 1000, Sugar Rush 1000, Fortune Gems 2 | **Complete** |
| 20 | Slots batch 4 — Treasure of Aztec, Sweet Bonanza 1000, Super Ace 2, Gates of Olympus 1000 | **Complete** |
| 21 | Blackjack | **Complete** (`table.blackjack`) |
| 22 | Roulette | **Complete** (`table.roulette`) |
| 23 | Game integration — all 18 through `RoundService` | **Complete** |
| 24 | Frontend (lobby, catalog, 16 slots, blackjack, roulette) | **Not started — next** |
| 25 | Administrator panel (auth, routes, controllers, frontend) | **Not started** |
| 26 | Final verification and publication | Not started |

### What is actually implemented

**All eighteen target games are registered and playable through the API.**
`php bin/console games:list` reports 22 games: the 18 targets plus 3 synthetic
fixtures and one SDK example.

| # | Game | Registered id |
| --- | --- | --- |
| 1 | Super Ace | `jili.super-ace` |
| 2 | Piñata Wins | `pgsoft.pinata-wins` |
| 3 | Wild Bounty Showdown | `pgsoft.wild-bounty-showdown` |
| 4 | Mahjong Hit | `slots.mahjong-hit` |
| 5 | Golden Empire | `jili.golden-empire` |
| 6 | Sugar Bang Bang 2 | `slots.sugar-bang-bang-2` |
| 7 | Fortune Gems 3 | `jili.fortune-gems-3` |
| 8 | Swerte Fiesta | `slots.swerte-fiesta` |
| 9 | Super Ace Deluxe | `jili.super-ace-deluxe` |
| 10 | Fortune Garuda 1000 | `jili.fortune-garuda-1000` |
| 11 | Sugar Rush 1000 | `pragmatic.sugar-rush-1000` |
| 12 | Fortune Gems 2 | `jili.fortune-gems-2` |
| 13 | Treasure of Aztec | `pgsoft.treasure-of-aztec` |
| 14 | Sweet Bonanza 1000 | `pragmatic.sweet-bonanza-1000` |
| 15 | Super Ace 2 | `jili.super-ace-2` |
| 16 | Gates of Olympus 1000 | `pragmatic.gates-of-olympus-1000` |
| 17 | Blackjack | `table.blackjack` |
| 18 | Roulette | `table.roulette` |

Each slot has its own module, configuration, symbols, strips, paytable,
evaluator, feature composition, metadata, registration, unit tests and
specification document under `documentation/backend/games/`. Blackjack and roulette are complete
tables on the card and roulette frameworks.

### What is implemented

* **Frontend.** `public/app.html` plus `public/assets/css` and
  `public/assets/js` deliver the lobby, game catalog with search/filter, a
  per-game launch view, and the blackjack, roulette and slot interfaces
  (`views/lobby.js`, `views/slot.js`, `views/blackjack.js`, `views/roulette.js`,
  `views/table.js`, `views/history.js`). Every outcome comes from the backend
  API; the client never decides a result.
* **Administrator console.** `public/admin.php` is a server-rendered page with
  its own PHP session, CSRF protection and database role re-check on every
  request. It provides dashboard/user management/credits/status, game
  availability, odds and friendly-play controls, and the audit trail. The
  underlying probability API (`/api/v1/admin/*`) remains for compatibility, but
  the player SPA no longer imports an admin view.

### Test status at this audit

```
composer check  =>  lint OK, PHPStan OK, phpcs OK, 273 tests / 22770 assertions OK
```

| Suite | Tests |
| --- | --- |
| `unit` | 46 |
| `framework` | 56 |
| `simulation` | 12 |
| `integration` | 31 |
| `transaction` | 10 |
| `api` | 54 |
| `games` | 64 |

### Defects found by the Phase 23 integration audit

* **Payout multipliers overflowed `INT UNSIGNED`.** A large win on any ways
  game rolled the whole round back with `SQLSTATE[22003]` because the slot
  framework's exact rational multipliers exceed 4,294,967,295. Fixed by
  `database/migrations/2026_01_02_000003_widen_multiplier_columns.sql`, which
  widens the four multiplier columns to `BIGINT UNSIGNED`.

### Honest scope statement

This project delivers the **engine** (core, services, three frameworks, SDK,
persistence, API, CLI, simulation, tests, documentation) and now **18 individual
game modules** built on it, each with its own specification and tests. The
mechanics are **independent implementations** informed by public research, not
replicas: exact proprietary reel strips, weights, paytables and artwork were not
obtained and are not claimed. Three titles could not be verified at all and are
implemented as clearly labelled configurable approximations (`KNOWN_ISSUES.md`
KI-1, KI-2).

The **frontend and the administrator panel are implemented** and reachable
(`public/app.html`, `/admin` via the `/api/v1/admin/*` routes). The probability
configuration service is reachable by an authorised administrator, the jackpot
mechanic is settled into payouts and recorded to `jackpot_events` for the admin
jackpot history, and the simulation tool reports jackpot, bonus and free-spin
triggers as separate counters.
