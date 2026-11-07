# Development Log

> Chronological, evidence-based record of engineering work.
> Every phase entry follows the mandatory procedure: announce → implement →
> verify → document → continue.
> Entries record what was implemented, files created/modified, decisions, tests
> run, results, limitations and whether acceptance criteria were met.

---

## Phase 0 — Repository inspection and autonomous planning

**Date:** 2026-11-07
**Status:** Complete
**Objective:** Establish ground truth about the repository, verify the
toolchain, decide conventions, and produce the plan plus initial documentation
before any engine code is written.

### Step 1 — Announcement

* Phase: 0.
* Objective: inspect the repository, verify the toolchain, define
  architecture, boundaries, standards and the testing strategy, and write the
  autonomous execution plan.
* Tasks: 0.1–0.6 from `AUTONOMOUS_EXECUTION_PLAN.md`.
* Expected deliverables: `AUTONOMOUS_EXECUTION_PLAN.md`, `PROJECT_STATUS.md`,
  `DEVELOPMENT_LOG.md`, `ARCHITECTURE.md`, `ENGINE_SPECIFICATION.md`,
  `README.md`, `RESEARCH_NOTES.md`.
* Acceptance criteria: the plan covers every subsequent phase with objectives,
  tasks, deliverables, dependencies, acceptance criteria and verification; the
  architecture matches the directive; no engine implementation has begun.

### Step 2 — Implementation

**Repository inspection.**

```
$ git -C /workspace/project/Engine status --short --branch
## main...origin/main
?? text.txt

$ ls -la
total 12
-rw-r--r-- 1 openhands openhands   47 ... text.txt
```

Finding: the repository is effectively empty — a single `text.txt` placeholder
and no project structure. There is no prior work to preserve beyond that file,
which was left untouched.

**Toolchain verification and installation.**

| Command | Result |
| --- | --- |
| `php -v` | PHP 8.4.27 (cli) |
| `php -m` | pdo_mysql, mysqli, mbstring, json, curl, intl, xml, zip, openssl present |
| `composer --version` | Composer 2.11.1 |
| `mysql --version` | MariaDB 11.8.6 |
| `sudo mysql -e 'CREATE DATABASE casino_engine ...'` | databases + `engine` user created |
| `mysql -u engine -p... -h 127.0.0.1 -e 'SELECT VERSION()' casino_engine` | `11.8.6-MariaDB` — connection verified |

**Research (carried out in parallel with planning; full record in
`RESEARCH_NOTES.md`).**

Sources used: JILI's official catalogue (581 entries) and per-game intro pages;
Pragmatic Play's official game pages; PG Soft's catalogue; SlotCatalog reviews
retrieved through the Internet Archive; Bigwinboard reviews; Wikipedia for
Blackjack, Roulette and Slot machine.

Key findings that shaped the design:

* The 16 slot titles span four different win-evaluation models
  (paylines, ways, cluster, pay-anywhere), several multiplier lifetimes
  (per-cascade, per-tumble, per-feature-persistent, position-keyed), and
  multiple symbol-transformation rules (gold→wild, silver→gold→wild, split
  symbols, stacked wilds, durable symbols).
* Grid shape can change mid-spin (expanding reels, 32,400 ways).
* Feature entry and retrigger thresholds differ between base and bonus games.
* Three named titles (Mahjong Hit, Sugar Bang Bang 2, Swerte Fiesta) could not
  be verified at any provider, and one (Fortune Garuda 1000) does not match any
  verifiable product (the closest is Fortune Garuda 500). These are recorded as
  unverified rather than guessed.
* Provider pages and third-party databases contradict each other in six
  specific cases; all six are recorded with a resolution rule.

**Conventions established.**

* PSR-12, `declare(strict_types=1)` in every file, PSR-4 autoload
  `CasinoEngine\` → `src/`.
* One class per file, one responsibility per class, small methods.
* Repositories are the only SQL writers; services depend on interfaces.
* Money is an integer-minor-unit value object; no floats in balance paths.
* Namespaces: `Core`, `Services`, `Frameworks`, `Games`, `Persistence`, `Api`,
  `Cli`, `Sdk`.

**Files created in this phase.**

| File | Contents |
| --- | --- |
| `README.md` | Overview, documentation map, quick start, requirements |
| `AUTONOMOUS_EXECUTION_PLAN.md` | 15 phases with objectives, tasks, deliverables, dependencies, acceptance criteria, verification, risks, completion criteria |
| `ARCHITECTURE.md` | Layering diagram, authoritative round flow with failure matrix, service invariants, framework boundaries, ADR log |
| `ENGINE_SPECIFICATION.md` | Core contracts, canonical result envelope, state semantics, event catalogue, error taxonomy, versioning, core purity invariant |
| `RESEARCH_NOTES.md` | Evidence-tagged mechanics research for 16 slots + blackjack + roulette, conflicts, limitations, traceability table |
| `PROJECT_STATUS.md` | Environment, phase board, blockers, publication record |
| `DEVELOPMENT_LOG.md` | This file |

**Files modified:** none. `text.txt` was left untouched.

**Architectural decisions taken in this phase:** ADR-1 through ADR-10, recorded
in `ARCHITECTURE.md` §10. The two decisions with the widest impact are ADR-2
(three separate frameworks rather than one forced execution model) and ADR-3
(integer minor units for money), both directly motivated by the research and by
the transaction-safety requirement.

### Step 3 — Verification

| Check | Command | Result |
| --- | --- | --- |
| Repository state | `git status --short --branch` | `## main...origin/main`, only `text.txt` untracked before this phase |
| Toolchain | `php -v`, `composer --version`, `mysql --version` | all present and working |
| Database | live `SELECT VERSION()` as the application user | succeeded |
| Document completeness | manual review against the directive's section 9.1 list | all Phase-0 documents present and non-empty |
| Plan coverage | manual review against directive section 10 | all 15 phases present with the required fields |

No automated tests exist yet by design — Phase 1 establishes the test
infrastructure. This is recorded rather than hidden.

### Step 4 — Documentation completion

Updated: `PROJECT_STATUS.md`, `DEVELOPMENT_LOG.md` (this entry),
`AUTONOMOUS_EXECUTION_PLAN.md` (Phase 0 marked complete). Technical docs
created: `ARCHITECTURE.md`, `ENGINE_SPECIFICATION.md`, `RESEARCH_NOTES.md`.

**Acceptance criteria assessment.**

| Criterion | Met? | Evidence |
| --- | --- | --- |
| Plan covers all subsequent phases | Yes | `AUTONOMOUS_EXECUTION_PLAN.md` §4 (phases 1–14) |
| Architecture consistent with the directive | Yes | `ARCHITECTURE.md` layering maps 1:1 to directive §4 |
| Development sequence technically justified | Yes | Dependency graph in `AUTONOMOUS_EXECUTION_PLAN.md` §5 |
| No unnecessary implementation before the plan | Yes | No source files written; `git status` confirms |

**Known limitations at this point.** Research is behaviourally descriptive, not
mathematically exact, for every commercial title — documented in
`RESEARCH_NOTES.md` §7. Three titles remain unverified.

### Step 5 — Continue

Proceeding automatically to Phase 1.

---

<!-- Subsequent phase entries are appended below as they complete. -->

## Phases 1–14 — Implementation record (backfilled)

The Phase 0 entry above is complete. Phases 1–14 were executed as code and
recorded in commit history; this section backfills the per-phase log that the
procedure requires, so the log matches what the repository actually contains.

Evidence for every phase: `composer check` (lint + PHPStan + PHPUnit) passes;
commit messages record each phase's scope. Command names that changed during
implementation (`migrate`→`db:migrate`, `db:check`→`db:status`,
`bin/console sim:run`→`bin/engine-simulate sim:run`) were corrected in
`AUTONOMOUS_EXECUTION_PLAN.md` so the plan matches the shipped CLI.

### Phase 1 — Project foundation
**Objective.** Composer, PSR-4 autoloading, bootstrap, environment config,
logging, exception handling, PDO abstraction, CLI entry point, static analysis.
**Implemented.** `composer.json` (PHP ≥8.3, PSR-4 `CasinoEngine\`), `src/Core/`
bootstrap and `Config`, `Env`, `src/Core/Logging/`, `src/Core/Exception/`
hierarchy, `src/Core/Database/ConnectionInterface`, `bin/console`,
`phpstan.neon.dist`, `phpcs.xml.dist`, `phpunit.xml`, `config/app.php`, `.env.example`.
**Verified.** `composer install`; `php bin/console list` runs; lint and PHPStan clean.
**Known limitation.** None.
**Acceptance criteria.** Met.

### Phase 2 — Universal engine core
**Objective.** Boot, contracts, registry, discovery, lifecycle, session, runtime,
state, results, events, errors — game-agnostic.
**Implemented.** `src/Core/Engine`, `Registry/GameRegistry`, `GameDiscovery`,
`Runtime/GameRuntime`, lifecycle/session/state/result/action contracts,
`Event/EventDispatcher`, `Value/{Money,Ratio,GameMetadata,RoundResult,GameAction,
GameContext}`.
**Verified.** Registration, discovery, session create/load, action execution,
result normalisation, and safe failure on an invalid module and action.
**Acceptance criteria.** Met.

### Phase 3 — Shared engine services
**Objective.** RNG, wallet, betting, payout, session, statistics, history,
transactions, idempotency.
**Implemented.** `src/Services/Rng` (`SecureRandomGenerator`,
`SeededRandomGenerator`, `RngFactory`), `Wallet/`, `Betting/`, `Payout/`,
`Session/`, `Statistics/`, `History/`, `Round/RoundService`,
`Transaction/TransactionManager`, `Idempotency/`.
**Verified.** `tests/Transaction/WalletIntegrityTest.php` — failure before/after
wager, during payout, duplicate request, concurrent wallet, retry after timeout.
**Known limitation.** Rate limiting is table-backed, not Redis-backed (deliberate).
**Acceptance criteria.** Met.

### Phase 4 — Game SDK and module system
**Objective.** Documented contracts so a new game is addable without core edits.
**Implemented.** `GameInterface`, metadata/config/action/state/result/validation
contracts; `GAME_SDK.md`; `src/Games/CoinFlip` as a worked example;
`tests/Unit/Sdk/{SdkExampleModuleTest,GameModulePurityTest}.php`.
**Verified.** The example registers, initialises and executes; the purity test
fails the build if a module reaches for a global RNG.
**Acceptance criteria.** Met.

### Phase 5 — Slot game framework
**Objective.** Extensible, mathematically correct slot framework.
**Implemented.** `src/Frameworks/Slot/` — reel/symbol configuration, grid
generation, pluggable evaluators (`PaylineEvaluator`, `WaysEvaluator`,
cluster/scatter/composite), cascades, free spins, combo multipliers,
`SlotState`/`SlotConfiguration`.
**Verified.** `tests/Framework/Slot/{EvaluationModelsTest,FeatureRegressionTest}.php`.
**Known limitation.** Frameworks implement shared mechanics; per-title maths are
not replicated (see `KNOWN_ISSUES.md` KI-4).
**Acceptance criteria.** Met.

### Phase 6 — Card game framework
**Objective.** Cards, decks, hands, blackjack, without blackjack rules in core.
**Implemented.** `src/Frameworks/Card/` (`Card`, `Deck`, `Hand`, `Suit`) and
`Card/Blackjack/` (`BlackjackHandEvaluator`, `Rules`, `Settlement`, `Outcome`).
**Verified.** `tests/Framework/Card/CardAndBlackjackTest.php` (multi-deck,
soft-17, every settlement branch).
**Acceptance criteria.** Met.

### Phase 7 — Roulette framework
**Objective.** Configurable variants, bets, evaluation, payouts.
**Implemented.** `src/Frameworks/Roulette/` (`RouletteVariant`, `BetFactory`,
`BetType`, `RouletteEngine`, `RouletteSettlement`).
**Verified.** `tests/Framework/Roulette/RouletteTest.php` (per bet type, French
zero rules).
**Acceptance criteria.** Met.

### Phase 8 — Database and persistence
**Objective.** Migrations, schema, indexes, FKs, repositories, transactions.
**Implemented.** `database/migrations/` (engine tables, rate-limit buckets),
`src/Persistence/` (connection, `MigrationRunner`, repositories).
**Verified.** `db:migrate` from clean, `db:status` drift check, repository tests.
**Acceptance criteria.** Met.

### Phase 9 — HTTP/JSON API
**Objective.** Thin controllers over the same services the CLI uses.
**Implemented.** `src/Api/` (kernel, router, middleware, `AuthController`,
`GameController`, `PlayController`, `WalletController`), `public/index.php`,
`public/.htaccess`.
**Verified.** `tests/Api/ApiTest.php`; manual `curl` transcript against the dev
server (health and games endpoints).
**Acceptance criteria.** Met.

### Phase 10 — Synthetic game integration
**Objective.** Three fixtures through the real runtime.
**Implemented.** `src/Games/Synthetic/{SyntheticSlotGame,SyntheticBlackjackGame,
SyntheticRouletteGame}` and their states/configurations.
**Verified.** `tests/Integration/RoundServiceIntegrationTest.php` (wallet moves,
history recorded, multi-action settlement).
**Acceptance criteria.** Met.

### Phase 11 — Simulation and mathematical testing
**Objective.** CLI simulation, seeded runs, honest statistics.
**Implemented.** `src/Simulation/` (`GameDriver`, `SimulationStatistics`,
`SimulationConsole`), `bin/engine-simulate` (`sim:run`, `sim:compare`).
**Verified.** Seeded runs reproducible; reports observed RTP with standard error
and a 95% CI, labelled as sample observations.
**Acceptance criteria.** Met.

### Phase 12 — Security, reliability and performance audit
**Objective.** Systematic review; fix critical/high findings.
**Implemented.** `SECURITY_AUDIT.md`; hardening across auth, tx boundaries,
validation, rate limiting, output encoding.
**Verified.** Full suite green; grep sweeps for forbidden patterns; PHPStan clean.
**Known limitation.** Self-conducted, no external review (KI-6).
**Acceptance criteria.** Met.

### Phase 13 — Documentation and developer experience
**Objective.** Complete, accurate documentation.
**Implemented.** `FRAMEWORK_{SLOT,CARD,ROULETTE}.md`, `DATABASE_SCHEMA.md`,
`API_SPECIFICATION.md`, `TESTING_STRATEGY.md`, `DEPLOYMENT_GUIDE.md`; the
remaining docs updated to match code.
**Verified.** Commands extracted and run; API examples checked against real
responses; `bin/engine-simulate` paths corrected.
**Acceptance criteria.** Met.

### Phase 14 — Final verification and completion
**Objective.** Full verification and honest completion.
**Verified.** `composer check`: lint OK, PHPStan OK, 168 tests / 22024
assertions OK. Dev server and API exercised manually. Removed empty
directories; corrected stale command names and doc claims.
**Defects found and fixed during final verification:**
1. `RoundService::buildContext()` rebuilt the RNG from a constant seed every
   round, so a seeded session produced the **same outcome every round**. The
   round number is now folded into the seed; a regression test
   (`testSeededRoundsVaryAndStayReproducible`) locks it in.
2. `GameDriver`'s docblock claimed an integration test proved it agreed with the
   round service; no such test existed. One now does
   (`testSimulationDriverAgreesWithTheRoundService`).
3. `ServeCommand` did not refuse to run in production despite the
   documentation saying so. It now refuses.
4. Three documented configuration keys (`PASSWORD_ALGO`,
   `ENGINE_VERSION_OVERRIDE`, and the min-SDK floor) had no consumer code.
   `PASSWORD_ALGO` now selects the hash algorithm, `ENGINE_VERSION_OVERRIDE`
   overrides the reported engine version, and the registry enforces a minimum
   SDK contract version.
5. Command drift in `AUTONOMOUS_EXECUTION_PLAN.md` corrected.
**Acceptance criteria.** Met; publication is the only remaining action.

### Continuing

Proceeding to publication: review the diff, commit, push to `origin/main`, and
record the commit hash in `PROJECT_STATUS.md`.
