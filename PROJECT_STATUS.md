# Project Status

> Single-page truth about where the project stands.
> Last updated: Phase 23 audit (all 18 games integrated).

---

## Current phase

**Engine phases 0–14 complete and published. Directive phases 15–23 complete.**

All eighteen target games (16 slots, blackjack, roulette) are implemented,
registered and verified end to end through `RoundService`; see the phase board
below and `GAME_DEVELOPMENT_ROADMAP.md`.

**Next: Phase 24 — the frontend.** Nothing user-facing exists yet; `public/`
holds only the front controller and `.htaccess`. Phase 25 (the administrator
panel) follows. Neither may be described as complete until it exists and its
tests pass.

---

## Environment (verified in this container)

| Component | Version | Command used |
| --- | --- | --- |
| PHP CLI | 8.4.27 | `php -v` |
| PHP extensions | pdo_mysql, mysqli, mbstring, json, curl, intl, xml, zip, openssl | `php -m` |
| MySQL-compatible server | MariaDB 11.8.6 | `mysql --version` |
| Composer | 2.11.1 | `composer --version` |
| Databases created | `casino_engine`, `casino_engine_test` | `CREATE DATABASE` |
| Application DB user | `engine@localhost` / `engine@127.0.0.1` | `GRANT` |

Code is written for PHP 8.3+ (`composer.json` requires `>=8.3`); this container
runs 8.4.27. The dev container is Debian 13 (trixie). Production target is
conventional PHP/MySQL shared hosting; see `DEPLOYMENT_GUIDE.md`.

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
| 14 | Final verification and completion | **Complete** (publication pending) |

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
| `DEPLOYMENT_GUIDE.md` | Shared-hosting deployment |
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
  value object and win-frequency governor (backend only; not yet reachable by an
  administrator — see Phase 25 below).

---

## Test status

```
composer check  =>  lint OK, PHPStan OK, 235 tests / 22534 assertions OK
```

`composer test` runs the whole suite (unit, framework, games, integration,
transaction, API, simulation). Integration tests skip cleanly when no database
is reachable; in this environment they ran against MariaDB. Layer-by-layer
detail is in `TESTING_STRATEGY.md` and the Phase 23 entry in
`DEVELOPMENT_LOG.md`.

---

## Known blockers

None. Every tool needed for the remaining phases is installed and working.

---

## Publication record

| Item | Value |
| --- | --- |
| Engine completion commit | `8cdee06968bac1451486baa0aba00703b6992ab9` |
| Phase 21–22 commit | `d0ee39d` — blackjack and roulette table games |
| Phase 23 commit | recorded in the Phase 23 log entry (`DEVELOPMENT_LOG.md`) |
| Remote branch | `main` (origin) → `https://github.com/https10011/Engine.git` |
| Push verified | yes — `git log origin/main` matches the local branch head |
| Verified at | Phase 23 audit, 2026-11-08 |

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
| 15 | Repository audit, roadmap, 18 individual game specifications | **Complete** (`docs/games/`, `GAME_DEVELOPMENT_ROADMAP.md`) |
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
specification document under `docs/games/`. Blackjack and roulette are complete
tables on the card and roulette frameworks.

### What is NOT implemented yet

* **Frontend: nothing exists.** `public/` contains only `index.php` and
  `.htaccess`. There is no lobby, no catalog, no game interface, no CSS and no
  JavaScript. Every game is reachable only through the JSON API.
* **Administrator panel: backend only, not wired.** `ProbabilityProfile`,
  `ProbabilityConfigService`, `WinFrequencyGovernor`, `ProbabilityRepository`
  and migrations `2026_01_02_000001_create_probability_tables.sql` /
  `2026_01_02_000002_add_admin_role.sql` exist. Missing: admin authentication
  and authorisation middleware, admin routes, admin controllers, the simulation
  verification endpoint and the whole admin frontend. The `users.role` column is
  written by the migration and **read by no code**.

### Test status at this audit

```
composer check  =>  lint OK, PHPStan OK, 235 tests / 22534 assertions OK
```

| Suite | Tests |
| --- | --- |
| Pre-existing engine (unit, framework, API, transaction, simulation) | 168 |
| `tests/Games/Table/BlackjackTest.php` | 51 |
| `tests/Games/Table/RouletteTest.php` | 46 |
| Slot unit tests (16 games) | see `tests/Games/Slots/` |
| `tests/Integration/AllGamesIntegrationTest.php` | 7 |

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

The **frontend and the administrator panel are not yet built**. The backend
probability configuration service exists but is not yet reachable by an
administrator. Neither the frontend nor the admin panel may be described as
complete until they exist and their tests pass.
