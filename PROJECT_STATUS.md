# Project Status

> Single-page truth about where the project stands.
> Last updated: final verification complete.

---

## Current phase

**All phases (0–14) complete, pending publication.** Final verification passed:
composer validation, PHP syntax, PHPStan, PHPCS, the full PHPUnit suite and a
manual API/database check. The only remaining step is the Git commit and push
recorded under *Publication record* below.

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
* `src/Games/` — three synthetic fixtures plus a documented SDK example.

---

## Test status

```
composer check  =>  lint OK, PHPStan OK, 168 tests / 22024 assertions OK
```

`composer test` runs the whole suite (unit, framework, integration,
transaction, API, simulation). Integration tests skip cleanly when no database
is reachable; in this environment they ran against MariaDB. Layer-by-layer
detail is in `TESTING_STRATEGY.md`.

---

## Known blockers

None. Every tool needed for the remaining phases is installed and working.

---

## Publication record

| Item | Value |
| --- | --- |
| Final commit | _recorded on push_ |
| Remote branch | `main` (origin) |
| Push verified | _recorded on push_ |

---

## Honest scope statement

This project delivers the **engine**: core, services, three frameworks, SDK,
persistence, API, CLI, simulation, tests and documentation, plus three
**synthetic** fixtures that exercise each framework. It does **not** deliver
the 18 named commercial clones. Several of those titles could not be verified
at all and are recorded as unverified in `RESEARCH_NOTES.md`.
