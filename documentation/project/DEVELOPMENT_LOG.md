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

---

## Phase 15–20 — Slot games (recorded in the roadmap and specifications)

The audit, the shared infrastructure extensions and the sixteen slot games are
recorded in `GAME_DEVELOPMENT_ROADMAP.md` §2, in each game's specification under
`docs/games/`, and in the commits listed below. The status board at the foot of
the roadmap is the authoritative phase status.

---

## Phase 21 — Blackjack

**Date:** 2026-11-08
**Status:** Complete

### Announcement

* Phase: 21. Objective: a complete, playable blackjack table on the card
  framework, with every rule that varies between real tables configurable.
* Deliverables: `BlackjackConfiguration`, `BlackjackState`, `Blackjack` module,
  `BlackjackStrategy` (basic strategy for simulation), tests, specification.
* Acceptance criteria: registered and listed; a full round executes; every rule
  configurable and tested; settlement verified against constructed hands.

### Implementation

Files created:

| File | Purpose |
| --- | --- |
| `src/Games/Table/Blackjack/BlackjackConfiguration.php` | Typed configuration wrapping `BlackjackRules`; unknown keys rejected |
| `src/Games/Table/Blackjack/BlackjackState.php` | Multi-hand round state, schema-versioned, round-trips |
| `src/Games/Table/Blackjack/Blackjack.php` | The module: deal, hit, stand, double, split, insurance, surrender |
| `src/Frameworks/Card/Blackjack/BlackjackStrategy.php` | Published multi-deck basic strategy, as letter rows |
| `src/Core/Contract/SimulationStrategyInterface.php` | Lets a game drive its own rounds in simulation |
| `tests/Games/Table/BlackjackTest.php` | 51 tests |

Files modified:

| File | Change |
| --- | --- |
| `src/Simulation/GameDriver.php` | Consults `SimulationStrategyInterface` before its built-in continuation rules |
| `src/Simulation/SimulationConsole.php` | Consults the interface for the opening action and the reported policy |
| `phpunit.xml` | Added the `games` suite |

### Defects found and fixed

1. **The opening stake was never wagered.** `GameDriver` derives the amount
   debited from the action's own bet times the outcome's wager multiplier, and
   `deal` reported `Ratio::one()` while `Money` was taken from the request. The
   first simulation reported `RTP 5.58` — a table paying five times everything.
   Every action now states explicitly what it wagers: one for an action that
   opens or extends a hand (deal, double, split, a taken insurance premium),
   zero for a pure decision (hit, stand, a declined premium). After the fix the
   same seed reports `RTP 1.0008` over 20,000 rounds.
2. **The dealer peek ran after the player's natural was paid.** A dealer natural
   beats everything except a player natural, so paying the player first handed
   out 3:2 on hands that should have pushed. The peek now runs first.
3. **Insurance took its premium from the request.** The premium is half the
   original stake; it is now checked against the hand and must be submitted as
   the action's own bet, so a client cannot buy insurance for an arbitrary
   amount.
4. **The dealer drew with every hand busted.** There is nothing left to beat, and
   drawing could turn a settled loss into a meaningless dealer bust.

### Verification

```
php bin/engine-simulate sim:run --game=table.blackjack --rounds=300000 --seed=7
  Observed RTP                     0.9908
  Observed hit frequency           0.5233
  95% CI for mean return           0.9663 .. 0.9733
```

The measured house edge under the default rules (6 decks, S17, peek, DAS, no
surrender) is 0.5–0.9% with the documented basic-strategy policy. That matches
the published figure for this ruleset, which is the check that the wager
accounting, the settlement and the strategy are all correct.

```
vendor/bin/phpunit --filter BlackjackTest  =>  51 tests, 251 assertions OK
composer stan                             =>  No errors
composer cs                               =>  Clean
```

### Limitations

* Basic strategy is used by the simulation only; it is not offered to players as
  a hint, and no card-counting assistance exists.
* The strategy tables are the published multi-deck chart. Deviations for a
  single- or double-deck shoe are not encoded; a table configured with one deck
  still plays the multi-deck chart.

---

## Phase 22 — Roulette

**Date:** 2026-11-08
**Status:** Complete

### Announcement

* Phase: 22. Objective: a complete playable roulette table on the roulette
  framework, supporting the three standard wheels.
* Deliverables: `RouletteConfiguration`, `RouletteState`, `Roulette` module,
  tests, specification.
* Acceptance criteria: registered and listed; a full round executes; every
  supported bet type settles correctly; limits enforced; history recorded.

### Implementation

Files created:

| File | Purpose |
| --- | --- |
| `src/Games/Table/Roulette/RouletteConfiguration.php` | Variant plus per-bet-type and table-total limits |
| `src/Games/Table/Roulette/RouletteState.php` | Bet slip, last spin, capped history, schema-versioned |
| `src/Games/Table/Roulette/Roulette.php` | The module: place_bets, clear_bets, spin |
| `tests/Games/Table/RouletteTest.php` | 46 tests |

A round is **two actions**: `place_bets` validates a slip and parks it on the
state without wagering anything; `spin` commits it and settles. Splitting them is
what lets the interface behave like a real table — the player can build, adjust
and clear a slip before committing — and it is also why a round is atomic only
at the spin. Nothing is at risk until the wheel turns.

### Security decisions

* The slip on the state is **authoritative**. A spin re-validates the submitted
  slip and compares a normalised fingerprint against the recorded one, so a
  client cannot place one slip and spin another. The total stake must equal the
  sum of the individual stakes; a client and server that disagree about what is
  being wagered must not be silently reconciled.
* Every bet is built through the framework's `BetFactory`, so an impossible bet
  (a street that is not three consecutive numbers, a number not on this wheel)
  is rejected before any money moves.
* Per-bet-type and table-total limits are enforced server-side.

### Verification

```
php bin/engine-simulate sim:run --game=table.roulette --rounds=200000 --seed=5
  Observed RTP                     0.9706
  95% CI for mean return           0.9662 .. 0.9750
```

0.9706 is the European wheel's 2.70% house edge, within the interval — the
check that the payout ladder and the settlement are right.

```
vendor/bin/phpunit --filter RouletteTest  =>  46 tests, 616 assertions OK
vendor/bin/phpunit                        =>  226 tests, 22212 assertions OK
composer stan                             =>  No errors
composer cs                               =>  Clean
```

### Limitations

* **En prison** is modelled as a push on the imprisoning spin, which is the rule's
  expected value over that spin; it is not carried forward as a live imprisoned
  bet across two spins. La partage is exact.
* The American five-number bet is supported by the framework but the default
  table configuration is European.

---

## Phase 23 — Game integration

**Date:** 2026-11-08
**Status:** Complete

### Announcement

* Phase: 23. Objective: prove that all eighteen games work through the same
  `RoundService` the API uses — not merely that each game passes its own unit
  test when driven directly.
* Deliverables: `tests/Integration/AllGamesIntegrationTest.php`.
* Acceptance criteria: every one of the eighteen registers, opens a session,
  runs a round, moves the wallet by exactly the wager and the payout, records
  history, rejects a below-minimum bet without touching the wallet, and shares
  no state with another game or another session.

### Implementation

`tests/Integration/AllGamesIntegrationTest.php` — 7 tests, 239 assertions. Each
game is driven through its own `SimulationStrategyInterface` where it has one,
because that is the game itself telling the harness how its round is built;
otherwise the harness falls back to the category's opening action.

Tests:

1. All eighteen target ids are registered.
2. Every game publishes metadata the API can serve (id, name, provider, sane
   bet limits, a positive max-win multiplier).
3. Every game runs a round that moves the wallet by exactly the wager and the
   payout.
4. Every game writes a settled round to the history.
5. A round of one game leaves another game's session byte-for-byte unchanged.
6. Two sessions of the same game keep independent round counts.
7. Every game rejects a below-minimum bet and leaves the wallet untouched.

### Defect found and fixed — payout multipliers overflowed `INT UNSIGNED`

This is the defect the whole phase exists to catch, and it is a real one.

The slot framework keeps payout multipliers as **exact rationals** so a win is
`numerator / denominator` with no float rounding. The denominators come from the
ways and cluster evaluations and legitimately reach `2.5e9` and beyond; a
`BIGINT`-scale payout then multiplies that again. `game_rounds.payout_multiplier_den`
and `payouts.multiplier_den` were `INT UNSIGNED`, which tops out at 4,294,967,295.

Result: the first large win on a ways game failed the round's `INSERT` with
`SQLSTATE[22003] Out of range value for column 'payout_multiplier_den'`, and the
whole round rolled back — a losing round looked fine, a big winning round threw
a 500.

Why no earlier test caught it: the sixteen slot unit tests assert against the
returned `Ratio` and never persist a round, and the pre-existing integration
tests only ever play the synthetic fixtures, whose multipliers are small.

Fix: `database/migrations/2026_01_02_000003_widen_multiplier_columns.sql` widens
`game_rounds.payout_multiplier_num`, `game_rounds.payout_multiplier_den`,
`payouts.multiplier_num` and `payouts.multiplier_den` to `BIGINT UNSIGNED`. No
engine logic and no game mathematics changed; the stored value is the same
rational it always was, it simply fits now.

### Verification

```
vendor/bin/phpunit --filter AllGamesIntegrationTest  =>  7 tests, 239 assertions OK
composer check => lint OK, PHPStan OK, 235 tests / 22534 assertions OK
```

### Remaining work after this phase

* Phase 24 — frontend.
* Phase 25 — administrator panel.
* Phase 26 — final verification and publication.

---

## Phase 24 — Frontend

### What was built

The API was complete and stable, so the frontend was built against the existing
JSON contract. No PHP engine code was changed for this phase.

| File | Role |
| --- | --- |
| `public/app.html` | SPA shell: header, balance, navigation, view mount point |
| `public/assets/css/app.css` | Layout, typography, responsive breakpoints |
| `public/assets/css/games.css` | Reel grids, cards, roulette table, bet chips |
| `public/assets/css/views.css` | Lobby, catalog, admin and history views |
| `public/assets/js/app.js` | Boot, routing, session and balance wiring |
| `public/assets/js/lib/api.js` | Thin fetch wrapper over the JSON API |
| `public/assets/js/lib/store.js` | In-memory session/catalog state |
| `public/assets/js/lib/dom.js` | Element helpers (create, bind, render) |
| `public/assets/js/lib/ui.js` | Buttons, toggles, sliders, toasts |
| `public/assets/js/lib/shell.js` | Header, balance, navigation |
| `public/assets/js/lib/sound.js` | Optional, user-toggled sound effects |
| `public/assets/js/views/lobby.js` | Lobby and game catalog with search/filter |
| `public/assets/js/views/slot.js` | Slot interface: grid, spin, bet, paytable, free spins, multipliers |
| `public/assets/js/views/blackjack.js` | Blackjack: deal, hit, stand, double, split, insurance |
| `public/assets/js/views/roulette.js` | Roulette: wheel, betting table, inside/outside bets, spin |
| `public/assets/js/views/table.js` | Shared table-game chrome |
| `public/assets/js/views/history.js` | Player round history |
| `public/assets/js/views/admin.js` | Administrator panel (Phase 25) |

Each slot view reads the game's own detail payload (symbols, paytable, feature
transitions) so a free-spin game shows its free-spin state, a multiplier game
shows its multipliers and a jackpot game announces the jackpot — the interface
reflects the module's mechanics rather than a shared template with a new title.

### Backend authority

Every spin, deal, hit, stand and bet is sent to the API; the returned payout,
balance and state are rendered. The client holds no RNG and computes no payout.
Sound is off by default and user-controlled.

---

## Phase 25 — Administrator panel and jackpot settlement

### Administrator panel

| Component | Role |
| --- | --- |
| `src/Api/Middleware/AdminMiddleware` | Server-side `admin` role check on every admin route |
| `src/Api/Controller/AdminController` | Overview, global profile, per-game profile, versions, restore, audit, simulation |
| `src/Api/ApiKernel` | `/api/v1/admin/*` route table |
| `public/assets/js/views/admin.js` | Dashboard, sliders, per-game cards, comparison, simulation, history, audit log |
| `tests/Api/AdminApiTest.php` | Authorisation (403 for a player) and endpoint behaviour |

### Jackpot settlement

The jackpot is now a first-class, independently configurable event rather than
a payout folded into the reels' own result:

* `SpinResult::hasJackpot()` and `jackpotTier()` read the feature transitions,
  so a jackpot is distinguishable from an ordinary win.
* `AbstractSlotGame` adds the jackpot prize to the reels' own payout and then
  applies the game's max-win cap, so the two are never double-counted.
* `WinFrequencyGovernor` never discards a jackpot spin. Before this, a low
  any-win target could re-draw a jackpot round and silently deflate the
  configured jackpot probability.
* `RoundService::recordJackpot()` writes the trigger to `jackpot_events` in
  `afterCommit()`, after the money has settled, so the history is append-only
  and cannot roll back a settled round. `ProbabilityRepository` is the only
  writer. `Container` wires it in.
* `SimulationStatistics` counts jackpot, bonus and free-spin triggers from the
  transitions separately and exposes per-feature rates; `AdminController::compare()`
  reports the observed jackpot rate against the configured probability.
* A jackpot module only rolls when `jackpot_probability` is positive, so an
  unconfigured game carries no jackpot event at all.

### Tests added

| Test | Covers |
| --- | --- |
| `tests/Games/Slots/JackpotWiringTest.php` (4) | `hasJackpot()` reads transitions; a non-jackpot spin reports none |
| `tests/Integration/JackpotIntegrationTest.php` (5) | A configured jackpot is paid and recorded; clearing the override stops the roll; the effective profile carries the configuration; an unconfigured game resolves none; the governor does not discard a jackpot round |
| `tests/Simulation/SimulationStatisticsTest.php` | Dedicated trigger counters |

### Verification

```
vendor/bin/phpunit                      =>  272 tests, 22760 assertions OK
composer lint                           =>  no syntax errors
vendor/bin/phpcs --standard=phpcs.xml.dist  =>  0 errors
vendor/bin/phpstan analyse              =>  No errors
```

### Remaining work after this phase

* Phase 26 — final verification and publication: full acceptance-criteria pass,
  deployment documentation refresh, final commit and push, remote verification.

---

## Phase 26 — Final verification and publication

**Date:** 2026-11-08.

### Verification performed

End-to-end acceptance pass over the running application (`php -S` on
`127.0.0.1:8099`), covering the backend, the browser client and the
administrator panel.

| Area | Method | Result |
| --- | --- | --- |
| Health | `GET /api/v1/health` | `version 1.0.0`, 22 games registered |
| Lobby | Browser | nav, category filters, search and 22 game cards render |
| Slot play | Browser + DB | `/slots/jili.super-ace` spin wrote round 12 and debited the wallet |
| Blackjack | Browser + DB | deal → stand → dealer bust, 2× payout of the 100 stake recorded |
| Roulette | Browser + DB | red + straight-up bets, winning number 12, red paid 2×, straight lost, net 0 |
| Admin RBAC | API | anonymous `401`, player token `403`, admin `200` |
| Admin global profile | API | saved any-win 50% (v3), applied to all non-override games |
| Probability governor | API | any-win target 0.10 → observed 0.10115; 0.90 → 0.90445 |
| Audit trail | Browser | each save recorded with administrator identity and version |
| Simulation tool | Browser + API | report renders configured-vs-observed; no wallet or history touched |

### Defects found and fixed

* **KI-13** — `.env` inline comment leaked into the reported engine version.
* **KI-14** — the "RTP 95% interval" was centred on the mean per-round return
  multiple rather than on the ratio-of-sums RTP it labelled; the two diverge
  whenever stakes differ, as with blackjack doubling. Fixed with the
  delta-method standard error of the ratio and a regression test proven to fail
  on the previous implementation.

### Verification

```
vendor/bin/phpunit                      =>  274 tests, 22777 assertions OK
php -l (src + tests)                    =>  no syntax errors
vendor/bin/phpcs --standard=phpcs.xml.dist  =>  0 errors
vendor/bin/phpstan analyse              =>  No errors
```

---

# Phase 27 — Player frontend, audit and shared-hosting package (2026-11-08)

> This entry is current. Paths in earlier entries refer to the repository
> layout at the time; all documentation is now indexed in
> [documentation/README.md](../README.md).

### Scope

Build the player-facing frontend requested for the supplied reference site,
without changing game mechanics, then audit the whole repository and package a
self-contained deployable application.

### Delivered

* **AURUM frontend** (`public/app.html`, `public/assets/`) — mobile-first lobby,
  generated brand/game artwork, carousel, category filters, search, recent
  games, login/registration, account drawer, history and four real game
  interfaces covering all 22 registered modules. No third-party provider or
  tenant art is shipped; no deposit/payment UI exists.
* **Additive API** — `POST /api/v1/auth/register` with transaction, validation,
  configurable starting credits and an off switch; three tests.
* **Production bundle** — `htdocs/` assembled by `tools/build_htdocs.php`:
  front controller, SPA, assets, `src/`, config, migrations, `install.sql`,
  CLI, runtime `vendor/` (`--no-dev`) and per-directory deny rules.
* **Documentation** reorganized into `backend/`, `frontend/`, `deployment/`,
  `project/` and `reports/`; root report added.
* **Verification tooling** — source inventory, asset generator, mobile and
  desktop Playwright checks.

### Defects found and fixed

* Delegated form handler read `event.currentTarget` (the document) instead of
  the form; login and registration did nothing.
* Invalid HTML `pattern` under the `v` flag (unescaped hyphen).
* CSS custom properties assigned via `Object.assign` never applied; the
  roulette wheel had no pockets.
* History view expected money objects but the API returned flat minor units;
  every amount rendered as 0.00.
* Roulette history strip was not repainted after a spin.
* Coin backface appeared mirrored because a CSS `filter` flattened
  `preserve-3d`.
* Slot/roulette header flex wrapping, emoji tofu, provider truncation, chip text
  overflow, duplicate search close button.
* **Production boot bug:** `htdocs/index.php` resolved its root to the enclosing
  repository because the parent contained `vendor/`, so the production tree read
  the development `.env`. Fixed by detecting the self-contained layout from its
  own `src/` and `config/`.
* **Shared-hosting compatibility:** `PASSWORD_ARGON2ID` was a hard boot
  dependency and the config default was an integer constant. Now the name is
  resolved at runtime with a bcrypt fallback.

### Verification

```
composer check                          =>  277 tests, 22791 assertions OK
PHPStan (260 files)                     =>  No errors
php -l over htdocs/                     =>  no syntax errors
fresh DB + htdocs/database/install.sql  =>  19 tables, 5 migrations, no drift
mobile Chromium 390x844 (htdocs)        =>  lobby/login/spin/deal/flip/search, 0 errors
desktop Chromium 1440x900 (htdocs)      =>  6 columns, no overflow, 0 errors
htdocs copy served from /tmp            =>  HTML/API 200, private paths 404
```

Real rounds were written to the production database (four game ids, five
rounds) through the production entry point. `htdocs/.env` was generated only
locally and is gitignored.

### Limitations

Chromium only; no Apache/LiteSpeed execution of `.htaccess`; no live shared
host; no email/verification; generated placeholder game art; 18 target game
specs but no specs for the four examples. Full list in
[KNOWN_ISSUES.md](KNOWN_ISSUES.md).
