# Autonomous Execution Plan

Universal Casino Game Engine — architecture, development, testing and
documentation.

* **Plan owner:** autonomous engineering agent
* **Plan created:** 2026-11-07
* **Plan status:** active — updated after every phase
* **Authority:** this plan is the agent's authorization to proceed through all
  phases without waiting for owner validation.

---

## 1. Project objectives

Build **one reusable game engine** that can power many independently
implemented casino-style games, without hardcoding any individual title into
shared infrastructure.

Concretely:

1. A **universal engine core** that knows nothing about specific games.
2. **Shared services** (RNG, wallet, betting, payout, session, statistics,
   history, transactions, idempotency) that game modules consume through
   contracts.
3. Three **game frameworks** — slot, card, roulette — because slot games,
   blackjack and roulette do not share an execution model.
4. A documented **Game SDK** so a future developer adds game #19 without
   touching the core.
5. A **MySQL persistence layer** with migrations, foreign keys, indexes and
   transaction safety.
6. A thin **HTTP/JSON API** that calls exactly the same engine services as the
   CLI.
7. A **CLI** for migrations, game listing, sessions and simulation.
8. A **simulation harness** producing observed statistics, clearly separated
   from theoretical values.
9. Three **synthetic game fixtures** that exercise the real frameworks, SDK,
   registry, runtime, services and persistence — not mockups.
10. **Automated tests** across unit, integration, framework, transaction and
    simulation layers.

### Non-goals (explicit)

* Implementing the 18 named commercial clones.
* Frontend, animation, artwork, sound, slot symbols, card art.
* Real-money anything.
* Microservices, Docker in production, Node servers, WebSockets, background
  workers, cloud dependencies.

---

## 2. Technical architecture (summary)

Five areas, in dependency order:

```
+-------------------------------------------------------------+
|  API (thin controllers)        CLI (commands, simulation)    |
+-------------------------------------------------------------+
|  Game SDK  ->  Game modules (Games/Slots/*, Games/Cards/*,   |
|                             Games/Roulette/*)                |
+-------------------------------------------------------------+
|  Frameworks:  Slot  |  Card  |  Roulette                     |
+-------------------------------------------------------------+
|  Shared services: RNG, Wallet, Betting, Payout, Session,     |
|                   Statistics, History, TransactionManager,   |
|                   IdempotencyStore                          |
+-------------------------------------------------------------+
|  Universal Engine Core: bootstrap, registry, discovery,      |
|  lifecycle, runtime, session manager, state store, events,   |
|  result normalisation, errors, config, logging, versioning   |
+-------------------------------------------------------------+
|  Persistence: PDO connection, repositories, migrations       |
+-------------------------------------------------------------+
```

Full detail in [ARCHITECTURE.md](ARCHITECTURE.md).

Key boundaries:

* The **core** must not reference any game identifier, symbol name, paytable
  or rule.
* **Services** own money movement. A game module *requests* a wager; it never
  writes the wallet.
* **Frameworks** own mechanics (reels, cards, wheel) but not money.
* **Modules** own rules, configuration, paytables and outcome evaluation.
* **Controllers/commands** own transport only.

---

## 3. Project constraints (from the directive)

| Constraint | How the plan honours it |
| --- | --- |
| PHP 8.3+ authoritative backend | `composer.json` requires `php >=8.3`, code uses `declare(strict_types=1)` |
| MySQL + PDO | `pdo_mysql`, prepared statements everywhere, no ORM |
| Composer + PSR-4 | `autoload.psr-4` maps `CasinoEngine\` -> `src/` |
| Modular monolith | One deployable app, module directories, no services split |
| Shared hosting deployable | No Docker/Node/workers; env-driven DB; webroot separate from source |
| Virtual PHP credits only | `Money` value object in minor units; no payment code exists |
| Frontend deferred | Only the JSON API contract is produced |
| PHPUnit | `phpunit.xml.dist`, tests under `tests/` |
| Transaction safety | `TransactionManager`, `SELECT ... FOR UPDATE`, idempotency keys |

---

## 4. Development phases

Phases run strictly in order. Each phase follows the 5-step procedure in
section 7. Dependencies are listed per phase.

---

### Phase 0 — Repository inspection and autonomous planning

**Objective.** Establish ground truth about the repository and produce the
plan and initial documentation before writing engine code.

**Tasks.**
0.1 Inspect repository contents, git state, remote, branch.
0.2 Verify toolchain availability (PHP, Composer, MySQL) and install what is
    missing in the development container.
0.3 Establish coding standards, naming, directory layout, architectural
    boundaries.
0.4 Define the testing strategy.
0.5 Identify risks and dependencies.
0.6 Write `AUTONOMOUS_EXECUTION_PLAN.md` and initial docs.

**Deliverables.** `AUTONOMOUS_EXECUTION_PLAN.md`, `PROJECT_STATUS.md`,
`DEVELOPMENT_LOG.md`, `ARCHITECTURE.md`, `ENGINE_SPECIFICATION.md`,
`README.md`, `RESEARCH_NOTES.md` (research started here, completed alongside
Phase 1).

**Acceptance criteria.**
* Plan covers every subsequent phase with objectives, tasks, deliverables,
  dependencies, acceptance criteria and verification.
* Architecture matches the directive's five areas.
* No engine implementation begins before the plan exists.

**Verification.** Manual review of each document; `git status` shows only
documentation added.

---

### Phase 1 — Project foundation

**Depends on:** Phase 0.

**Objective.** A clean, maintainable PHP project that boots, loads
configuration, connects to MySQL, logs, and runs from the CLI.

**Tasks.**
1.1 `composer.json` with PSR-4 autoloading, PHPUnit, static analysis, dev
    scripts.
1.2 `.env.example` and an environment loader (no dotenv dependency needed —
    small hand-rolled parser) plus `Env` accessor.
1.3 `Config` repository supporting nested dot access and environment
    overrides.
1.4 Application bootstrap (`Application`, `Kernel`).
1.5 Exception hierarchy: `EngineException` base plus domain subclasses, with
    safe public messages and internal detail separation.
1.6 Error handler that converts PHP errors/warnings to exceptions in CLI and
    returns safe JSON in HTTP.
1.7 Logging foundation: PSR-3-compatible logger writing to file/stdout with
    levels and context redaction.
1.8 Database connection abstraction over PDO: lazy connect, env-driven DSN,
    sane PDO attributes, transaction helpers.
1.9 `bin/console` CLI entry point with a small command registry.
1.10 Coding standards (PHP_CodeSniffer PSR-12) and static analysis (PHPStan)
     configuration.
1.11 `.gitignore` covering `.env`, `vendor/`, logs, caches.
1.12 Separate `public/` webroot; add `.htaccess` protection files.

**Deliverables.** Working skeleton, `bin/console`, config, logger, PDO
connection, static analysis config, `.gitignore`, `docs/` structure.

**Acceptance criteria.**
* `composer install` succeeds.
* `composer dump-autoload` and autoloading of a class from `src/` works.
* `php -l` passes on every PHP file (scripted check).
* Configuration loads from `.env` with sane defaults.
* A live `SELECT 1` against the configured MySQL database succeeds.
* `php bin/console` prints the command list and exits 0.
* No secret is committed (`git grep` finds no password/DSN literal).

**Verification.** `php -l` sweep, `bin/console list`, DB smoke command
(`db:status`), PHPStan level ≥ 5 clean on `src/`.

---

### Phase 2 — Universal engine core

**Depends on:** Phase 1.

**Objective.** The game-agnostic core: registration, discovery, lifecycle,
sessions, runtime, events, result normalisation.

**Tasks.**
2.1 Contracts: `GameInterface`, `GameMetadataInterface`,
    `GameConfigurationInterface`, `GameSessionInterface`,
    `GameStateInterface`, `GameActionInterface`, `GameResultInterface`,
    `GameValidatorInterface`, `GameFactoryInterface`.
2.2 `GameRegistry` with registration, lookup, duplicate protection,
    metadata enumeration, category filtering.
2.3 `GameDiscovery` — scan configured namespaces/directories for classes
    implementing `GameInterface`, driven by configuration (not hardcoded).
2.4 `ModuleLoader` — instantiate discovered modules through their factory,
    injecting services via constructor.
2.5 `Engine` facade: boot, register, initialise game, create session,
    execute action, close session.
2.6 `SessionManager` — create/load/expire/close sessions; session state
    persistence via a store contract.
2.7 `GameRuntime` — orchestrates a single action: validate → load state →
    delegate to module → normalise result → emit events → return.
2.8 `StateStore` contract plus in-memory and database implementations.
2.9 `EventDispatcher` with ordered listeners and typed events
    (`GameRegistered`, `SessionCreated`, `ActionExecuted`,
    `WagerPlaced`, `PayoutPaid`, `RoundCompleted`, `EngineError`).
2.10 `ResultNormaliser` — a single canonical result envelope for every game
    type.
2.11 Versioning: engine version constant + per-game version in metadata.
2.12 `GameContext` — the service bag handed to modules (RNG, wallet, betting,
    payout, logger, config) so modules never touch containers directly.

**Deliverables.** `src/Core/**`, core contracts, event system, result
envelope.

**Acceptance criteria.**
* A synthetic game registers and is discoverable by the registry.
* The engine initialises a game and creates a session.
* The engine executes an action through contracts and returns a normalised
  result.
* Invalid module definitions (missing metadata, bad factory) are rejected with
  a clear error.
* Invalid actions fail safely without corrupting session state.
* Core contains zero references to any specific game title, symbol or rule.

**Verification.** Unit tests for registry, discovery, runtime, events; a
grep-based "core purity" test asserting no game-specific strings in
`src/Core`.

---

### Phase 3 — Shared engine services

**Depends on:** Phase 2 (contracts), Phase 1 (DB).

**Objective.** Money, randomness, sessions, statistics and history as
independent, testable services.

**Tasks.**
3.1 `Money` value object (integer minor units, currency, arithmetic,
    no floats for balances).
3.2 RNG: `RandomGeneratorInterface`, `SecureRandomGenerator` (CSPRNG via
    `random_int`), `SeededRandomGenerator` (deterministic, tests/simulation
    only), `RngFactory`, statistical smoke tests.
3.3 Wallet: balance read, credit, debit, hold/release, transaction records,
    optimistic + pessimistic concurrency handling.
3.4 Betting: bet validation (min/max/step), wager placement, bet records,
    bet configuration per game.
3.5 Payout: multiplier application, payout validation, payout records,
    rounding policy.
3.6 Session service: create, validate, touch, expire, recover.
3.7 Statistics: per-user and per-game aggregates, totals wagered/returned,
    hit frequency, RTP estimate for a sample.
3.8 History: game rounds, bet history, payout history with pagination.
3.9 `TransactionManager`: `begin/commit/rollback` with nesting guard and a
    callback-based `transactional()` helper.
3.10 Idempotency: `IdempotencyStore` keyed by (user, game, idempotency key)
    storing the canonical result; replay returns the stored result instead of
    re-executing.
3.11 `RoundService` — the single authoritative "play one round" flow:
    idempotency check → transaction → wager → module outcome → payout →
    records → commit.
3.12 Ensure modules cannot bypass: wallet methods are only reachable through
    the round service / wallet service, and the SDK exposes a restricted
    facade.

**Deliverables.** `src/Services/**`, `Money`, RNG implementations,
`RoundService`.

**Acceptance criteria.**
* Every service has an interface and is independently unit-testable.
* Wallet debits/credits are transactional; a forced failure mid-round leaves
  balance unchanged.
* Replaying the same idempotency key does not deduct twice and returns the
  original result.
* Production RNG and test RNG are distinct classes with distinct semantics.
* A game module has no code path that writes the wallet directly.

**Verification.** Unit tests per service; transaction tests with injected
failures; concurrency test using two connections with row locking; idempotency
replay test.

---

### Phase 4 — Game SDK and module system

**Depends on:** Phases 2–3.

**Objective.** A documented, minimal surface for authoring games.

**Tasks.**
4.1 Finalise SDK contracts and document each one.
4.2 `AbstractGameModule` base class handling boilerplate (metadata, config
    defaults, validation hook, result construction).
4.3 `GameMetadata` value object (id, name, category, version, provider,
    volatility, max win, min/max bet, currency).
4.4 Configuration contract + validator with typed, defaulted, validated
    config keys.
4.5 Action contract + typed action parsing.
4.6 Result contract + canonical envelope with game-specific `detail` payload.
4.7 Registration conventions: autoload-discoverable class + explicit
    registration list fallback.
4.8 Two worked example modules in the docs (a minimal one and a framework
    one) plus real fixtures in Phase 10.
4.9 `GAME_SDK.md`: 10 required sections (create, register, configure,
    initialise session, process action, generate/evaluate outcome, betting and
    payout integration, persist history, test, expose via API).

**Deliverables.** `src/Core/Contracts/**` finalised, `src/Sdk/**`,
`GAME_SDK.md`.

**Acceptance criteria.**
* A developer can create a module using only documented SDK.
* A module registers without modifying the core (test asserts this).
* Module initialises and executes through the runtime.
* Module uses shared services through defined interfaces only.
* Invalid module implementations are detected and rejected with a clear
  message.

**Verification.** SDK conformance test suite run against a deliberately
broken module (expects specific errors) and against a valid module (expects
success).

---

### Phase 5 — Slot game framework

**Depends on:** Phases 2–4.

**Objective.** A slot framework covering the mechanics actually observed in
the 16 target titles (see `RESEARCH_NOTES.md`), with small composable
components.

**Tasks.**
5.1 `Symbol`, `SymbolSet`, `SymbolWeights` (weighted symbol tables).
5.2 `ReelStrip` (ordered weighted strips) and `ReelSet` (variable reel
    counts, variable heights).
5.3 `Grid` — immutable outcome grid with helpers (column, row, diagonal,
    adjacency, symbol counts).
5.4 `OutcomeGenerator` — spin strips → grid, using the RNG abstraction.
5.5 Win evaluation strategies behind `WinEvaluatorInterface`:
    * `PaylineEvaluator` (classic fixed paylines, left-to-right)
    * `WaysEvaluator` (1024-ways / megaways-style adjacency multiplication)
    * `ClusterEvaluator` (8+ adjacent clusters, pays-anywhere / cluster)
    * `ScatterPayEvaluator` (pay-anywhere scatter counts)
5.6 `Paytable` and `PaytableEvaluator` (symbol × count → multiplier).
5.7 `WildRule` (substitution, expanding/stacked wilds, wild replication).
5.8 `ScatterRule` (count thresholds, triggers).
5.9 `MultiplierModel` implementations: `StaticMultiplier`,
    `CascadeTrailMultiplier` (x1,x2,x3,x5 / x1..x10 / x2,x4,x6,x10),
    `SymbolMultiplier` (2x–1000x symbols summed at end of tumble),
    `PositionMultiplier` (Sugar Rush-style marked positions doubling to
    x1024).
5.10 `CascadeEngine` — remove winning symbols, drop replacements, repeat,
    emit tumble sequence; supports "gold symbol leaves a wild" and
    "framed symbol turns wild" hooks.
5.11 Feature state machines: `FreeSpinsFeature` (trigger, retrigger, per-game
    multiplier mode), `BonusFeature` contract for game-specific rounds
    (wheel, pick, nudge).
5.12 `SlotEngine` composing generator → evaluator → cascade → features →
    result, driven by a `SlotGameDefinition` built by the module.
5.13 `SlotMathModel` descriptor (reel weights, paytable, feature params) so
    simulation and tests can run without a frontend.
5.14 `SlotResultDetail` canonical payload (grids, winning lines, cascades,
    multipliers, feature transitions).

**Deliverables.** `src/Frameworks/Slot/**`, `FRAMEWORK_SLOT.md`.

**Acceptance criteria.**
* A synthetic slot module runs on the framework with no core changes.
* Variable reel counts/rows and all four evaluator models are supported and
  unit tested.
* Cascade sequences are deterministic under a seeded RNG.
* Free spins trigger, retrigger and apply the correct multiplier mode.
* Framework integrates with engine runtime and shared services.
* Unit + integration tests pass.

**Verification.** Per-component unit tests; a deterministic seeded full-round
test with a hand-built strip where the expected grid is known by hand;
evaluator tests with hand-constructed grids.

---

### Phase 6 — Card game framework

**Depends on:** Phases 2–4.

**Objective.** Cards, decks, hands, turns and rule configuration, sufficient
for blackjack and future card games.

**Tasks.**
6.1 `Card` (rank, suit), `Rank`, `Suit` enums, string notation
    (`As`, `Td`, `7c`).
6.2 `Deck` construction (52, multi-deck, jokers optional), shuffle through the
    RNG abstraction, cut/penetration for shoe games.
6.3 `Shoe` (multi-deck with penetration and reshuffle).
6.4 `Hand` — add card, value, soft/hard, blackjack detection, bust detection,
    splittable, double-able.
6.5 `DealingService` — deal, burn, expose/hide dealer hole card.
6.6 `CardGameState` — player hands, dealer hand, active hand index, phase.
6.7 `TurnManager` — phases (betting → dealing → player turn → dealer turn →
    settlement), legal-action computation, action application.
6.8 `RuleConfiguration` — deck count, dealer hits/stands soft 17, blackjack
    payout, double allowed, split rules (max splits, resplit aces, DAS),
    surrender, insurance, peek/no-peek.
6.9 `HandEvaluator` and `BlackjackSettlement` producing outcome + payout
    multiplier (1:1, 3:2, push, insurance 2:1).
6.10 Integration hooks so a blackjack module is a thin rules/config layer.
6.11 Basic strategy helper (optional, for simulation sanity checks only).

**Deliverables.** `src/Frameworks/Card/**`, `FRAMEWORK_CARD.md`, blackjack
fixture groundwork.

**Acceptance criteria.**
* Card/deck/shuffle correctness under seeded RNG.
* Hand evaluation correct for soft/hard totals, aces, blackjack, bust.
* Dealer behaviour honours rule configuration (S17/H17, peek).
* Split, double, insurance, surrender produce correct settlement multipliers.
* Framework integrates with the engine; tests cover edge cases.

**Verification.** Hand-evaluation table tests; seeded shuffle reproducibility;
full hand scenarios asserted against hand-computed outcomes; a rule-matrix
test.

---

### Phase 7 — Roulette framework

**Depends on:** Phases 2–4.

**Objective.** Wheel, bets, evaluation and payout for European, American and
French variants.

**Tasks.**
7.1 `Wheel` — pocket sequence, colour map; European (single zero), American
    (double zero), French (single zero, La Partage/En Prison flags).
7.2 `RouletteBet` types: straight, split, street, corner, six-line, column,
    dozen, red/black, odd/even, high/low, basket/top-line, snake, five-number
    (American).
7.3 `BetValidator` — legal numbers per bet type, table limits, chip
    denominations.
7.4 `OutcomeGenerator` — spin via RNG abstraction; deterministic under seed.
7.5 `BetEvaluator` — determine which bets win/lose/push.
7.6 `PayoutCalculator` — standard 35:1, 17:1, 11:1, 8:1, 5:1, 2:1, 1:1, plus
    La Partage / En Prison handling.
7.7 `RouletteResultDetail` — winning number, colour, per-bet outcome and
    payout.
7.8 Variant configuration objects.

**Deliverables.** `src/Frameworks/Roulette/**`, `FRAMEWORK_ROULETTE.md`.

**Acceptance criteria.**
* Variants configurable; pocket sets correct (37 vs 38 pockets).
* Every bet type defined, validated and independently evaluated.
* Payout multipliers tested against known values.
* House-edge sanity: observed edge from a large seeded sample approaches the
  theoretical value for the variant (documented as observed, not proof).
* Framework integrates with the engine.

**Verification.** Table-driven tests for all bet types on all variants;
seeded spin reproducibility; statistical smoke test with documented tolerance.

---

### Phase 8 — Database and persistence

**Depends on:** Phases 1, 3.

**Objective.** Complete, documented, repeatable persistence.

**Tasks.**
8.1 Migration runner: ordered files, `migrations` table, idempotent, safe
    re-run, transaction per migration where the engine allows.
8.2 Schema:
    * `users`, `user_credentials` (hashed), `user_sessions`
    * `wallets`, `wallet_transactions`
    * `games`, `game_configurations`
    * `game_sessions`, `game_rounds`, `bets`, `payouts`
    * `idempotency_keys`
    * `statistics_daily` (aggregate rollup), `audit_log`
8.3 Constraints: PKs, FKs with deliberate ON DELETE behaviour, unique keys
    (idempotency, session token, round number per session), indexes for
    history queries.
8.4 Repositories: `UserRepository`, `WalletRepository`, `GameRepository`,
    `SessionRepository`, `RoundRepository`, `BetRepository`,
    `PayoutRepository`, `StatisticsRepository`, `IdempotencyRepository`,
    `AuditRepository`.
8.5 Row locking (`SELECT ... FOR UPDATE`) in wallet mutations.
8.6 Seed data: a demo user, a demo wallet, registered game definitions.
8.7 `DATABASE_SCHEMA.md` documenting every table, column, key and index.

**Deliverables.** `database/migrations/**`, `database/seeds/**`,
`src/Persistence/**`, `DATABASE_SCHEMA.md`.

**Acceptance criteria.**
* Migrations run clean from an empty database and re-run safely.
* FK relationships and indexes verified by querying `information_schema`.
* Wallet transactions remain consistent (covered by Phase 3 transaction tests
  against the real DB).
* Repository operations tested against the real database.
* Sessions/rounds/bets/payouts retrievable end-to-end.
* Schema documentation matches the actual DDL (automated comparison test).

**Verification.** `php bin/console db:migrate` from scratch; schema-vs-docs test;
repository integration tests.

---

### Phase 9 — HTTP/JSON API

**Depends on:** Phases 2–3, 8.

**Objective.** A thin, consistent integration boundary.

**Tasks.**
9.1 Front controller `public/index.php`, router with method+path patterns.
9.2 Request abstraction (method, path, headers, parsed JSON body, query).
9.3 Response abstraction with consistent JSON envelope
    `{ok, data, error, meta}` and correct HTTP status codes.
9.4 Error mapping: validation → 422, auth → 401, forbidden → 403, not found
    → 404, conflict/idempotency mismatch → 409, rate limit → 429, internal →
    500 with no leakage.
9.5 Auth: token issue/verify, password hashing with `password_hash`
    (Argon2id/bcrypt), constant-time comparison.
9.6 Session authorisation: a game session belongs to a user; cross-user access
    rejected.
9.7 Endpoints: auth, games list, game metadata, session create/get, action
    submit, bet submit, result get, wallet balance, transactions, game
    history.
9.8 Idempotency: `Idempotency-Key` header support, replay-safe.
9.9 Request validation layer with per-endpoint schemas.
9.10 CSRF strategy for cookie-based browser use (documented; token-based API
     used by default).
9.11 Rate limiting (simple, DB/file based, shared-hosting friendly).
9.12 `API_SPECIFICATION.md`.

**Deliverables.** `public/index.php`, `src/Api/**`, `API_SPECIFICATION.md`.

**Acceptance criteria.**
* Endpoints work against the dev server with a real database.
* Response format matches documentation (schema test).
* Invalid requests rejected safely; unauthorised rejected.
* Duplicate idempotent requests safe.
* API path and CLI path invoke identical engine services (asserted by a test
  that plays a round through both and compares the round record).

**Verification.** API integration tests using the real router (no HTTP mock);
an end-to-end test over a loopback server.

---

### Phase 10 — Synthetic game integration

**Depends on:** Phases 5–9.

**Objective.** Three fixtures that exercise the real stack.

**Tasks.**
10.1 `SyntheticSlot` — ways-paying, cascading, free spins with doubled
    multiplier trail, plus a second synthetic slot using cluster pays and
    symbol multipliers (proves two different slot models on one framework).
10.2 `SyntheticBlackjack` — full blackjack module on the card framework.
10.3 `SyntheticRoulette` — European roulette module on the roulette framework.
10.4 Register all fixtures via discovery and via explicit registration.
10.5 Ensure each fixture persists sessions, rounds, bets, payouts and history.
10.6 Tests: registration, initialisation, execution, state validation, wallet
    transactions, history recording, invalid action rejection.

**Deliverables.** `src/Games/Synthetic/**`, tests.

**Acceptance criteria.**
* All fixtures register, initialise and execute through the universal runtime.
* Game state and outcomes validated.
* Virtual wallet transactions work and are recorded.
* History recorded and retrievable.
* Tests pass.
* No fixture bypasses services or the runtime.

**Verification.** Integration test suite per fixture plus one combined
end-to-end test through the API.

---

### Phase 11 — Simulation and mathematical testing

**Depends on:** Phases 5–8, 10.

**Objective.** A CLI simulation harness for mathematical analysis without a
frontend.

**Tasks.**
11.1 `sim:run` — game selection, spin/hand count, seeded runs, batch size,
    optional DB-free mode (does not touch production wallets).
11.2 `sim:compare` — run a game with two different seeds/configs.
11.3 Aggregation: total wagered, total returned, observed RTP, hit frequency,
    win/loss distribution, payout distribution, max/min observed result,
    feature trigger frequency.
11.4 Statistical helpers: mean, variance, standard deviation, standard error,
    confidence interval for observed RTP.
11.5 Reports: console table, JSON export, CSV export.
11.6 Clear labelling: theoretical vs observed, sample size, seed.
11.7 Framework-specific simulations: slot (spin-based), blackjack (hand-based
    with a documented strategy), roulette (spin-based).
11.8 Tests for the statistics helpers and reproducibility (same seed → same
    result).

**Deliverables.** `src/Cli/Command/Sim/**`, `src/Simulation/**`, reports
directory (gitignored), tests.

**Acceptance criteria.**
* Simulations run with no browser and no HTTP.
* Seeded runs reproduce byte-identical summaries.
* Statistical calculations unit tested against hand-computed values.
* Output explicitly labels observed estimates.
* Simulation does not mutate production wallets (uses an in-memory wallet or
  an explicitly flagged simulation user).
* Large runs can be executed in controlled batches.

**Verification.** Reproducibility test; statistics unit tests; a documented
sample run recorded in `DEVELOPMENT_LOG.md` with its observed numbers.

---

### Phase 12 — Security, reliability and performance audit

**Depends on:** Phases 1–11.

**Objective.** Find and fix real problems, not produce a report.

**Tasks.**
12.1 Authentication: hashing algorithm, timing, token entropy, expiry,
    revocation.
12.2 Authorisation: every endpoint checks ownership; session/user binding.
12.3 Transactions: verify no path mutates wallet outside a transaction; verify
    rollback on every failure point (before debit, after debit, during payout).
12.4 Duplicate/replay: idempotency coverage on all money-moving endpoints.
12.5 RNG: confirm CSPRNG in production path, no `rand`/`mt_rand` in
    authoritative code, seeded RNG unreachable from production config.
12.6 Input validation: every action payload validated; unknown fields
    rejected.
12.7 SQL injection: grep for string-concatenated SQL; all queries prepared.
12.8 Session handling: fixation, expiry, regeneration on privilege change.
12.9 API security: headers, error leakage, rate limits, body size limits.
12.10 Logging: no secrets, no card/credential leakage; redaction test.
12.11 Resource use: query counts per round, N+1 checks, index usage
     (`EXPLAIN` on hot queries).
12.12 Shared hosting: no shell-outs, no long-running processes, writable-path
     assumptions documented.
12.13 Fix everything critical/high; add regression tests for each fix.

**Deliverables.** `SECURITY_AUDIT.md` (findings + fixes + residual risk),
regression tests.

**Acceptance criteria.**
* Critical and high findings fixed, with a test proving the fix.
* Remaining limitations documented honestly.
* Full test suite passes.

**Verification.** Re-run full suite; run each new regression test; `EXPLAIN`
evidence recorded.

---

### Phase 13 — Documentation and developer experience

**Depends on:** Phases 1–12.

**Objective.** A developer can install, understand, extend and operate the
engine from the docs alone.

**Tasks.**
13.1 Installation and requirements.
13.2 Architecture and directory structure.
13.3 Engine lifecycle and request lifecycle diagrams.
13.4 Game SDK (already produced; re-verify against implementation).
13.5 Framework docs (slot, card, roulette).
13.6 Database schema doc.
13.7 API specification.
13.8 CLI reference (every command, every option, verified output).
13.9 Simulation instructions with a worked example.
13.10 Testing instructions.
13.11 Security considerations.
13.12 Deployment guide including shared-hosting limitations.
13.13 Troubleshooting.
13.14 "Implementing game #19" walkthrough.

**Deliverables.** All documents listed in section 9.1 of the directive, plus
framework docs.

**Acceptance criteria.**
* Documentation matches implementation; no documented command that does not
  exist.
* Examples are executed and verified, or clearly labelled illustrative.
* Setup instructions verified from a clean checkout.

**Verification.** Doc-command verification script that extracts `php bin/console`
invocations from docs and runs them; doc/schema consistency test.

---

### Phase 14 — Final verification and completion

**Depends on:** all phases.

**Objective.** Prove the whole system works and publish it.

**Tasks.**
14.1 `composer validate`, `composer install` from lock.
14.2 `php -l` on every PHP file.
14.3 PHPStan full run.
14.4 PHPUnit full suite with coverage summary.
14.5 Migrations from an empty database; seed; verify schema.
14.6 API end-to-end run against the dev server.
14.7 Framework tests, synthetic fixture tests, simulation reproducibility.
14.8 Security regression tests.
14.9 Documentation consistency checks.
14.10 Codebase review: dead code, duplicates, unused deps, TODOs, empty
     files, fake implementations, hardcoded secrets, inconsistent naming.
14.11 Final report.
14.12 Git: review diff, stage, commit, push, verify, record hash.

**Deliverables.** `FINAL_REPORT.md`, updated `PROJECT_STATUS.md`,
`DEVELOPMENT_LOG.md`, `CHANGELOG.md`, `KNOWN_ISSUES.md`.

**Acceptance criteria.**
* Every check above executed with recorded results.
* No false completion claims: the 18 clones are explicitly *not* delivered.
* Final commit pushed, or the exact blocker documented.

**Verification.** The recorded command outputs in `DEVELOPMENT_LOG.md` and
`FINAL_REPORT.md`.

---

## 5. Phase dependency graph

```
Phase 0
  └─> Phase 1 ──> Phase 2 ──> Phase 3 ──> Phase 4
                    │            │           │
                    │            │           ├─> Phase 5 ─┐
                    │            │           ├─> Phase 6 ─┤
                    │            │           └─> Phase 7 ─┤
                    │            └─> Phase 8 ──────────────┤
                    │                                      │
                    └──────────────> Phase 9 ──────────────┤
                                                           │
                                        Phase 10 <─────────┘
                                          └─> Phase 11 ─> Phase 12 ─> Phase 13 ─> Phase 14
```

Phases 5, 6, 7 are independent of each other. Phase 8 depends on 1 and 3.
Phase 9 depends on 2, 3 and 8. Phase 10 depends on 5–9.

---

## 6. Acceptance criteria (project level)

The project is complete when:

1. `AUTONOMOUS_EXECUTION_PLAN.md` exists and reflects actual work.
2. All 15 phases (0–14) are addressed with recorded evidence.
3. The universal core is implemented and provably game-agnostic.
4. Shared services are implemented, transaction-safe and independently
   testable.
5. The SDK is implemented and documented, and a new module can be added
   without core changes.
6. Slot, card and roulette frameworks are implemented and tested.
7. MySQL persistence with migrations, FKs, indexes and repositories works from
   a clean database.
8. The HTTP/JSON API works and uses the same engine services as the CLI.
9. Three synthetic modules exercise all three frameworks through the real
   runtime.
10. CLI simulation works, is reproducible under a seed, and reports observed
    statistics honestly.
11. Automated tests exist and pass, covering unit, integration, framework,
    transaction and simulation layers.
12. Security and transaction integrity reviewed; critical/high issues fixed.
13. Technical documentation complete and accurate.
14. Known limitations documented honestly.
15. Final commit created and pushed to GitHub, or the blocker documented.

---

## 7. Per-phase execution procedure (mandatory)

1. **Announce and document** — identify phase, objective, tasks, deliverables,
   acceptance criteria; record in `DEVELOPMENT_LOG.md`.
2. **Implement** — inspect existing code first; maintainable, consistent, no
   duplicate implementations.
3. **Verify** — syntax checks, static analysis, automated tests, integration
   tests, database checks, CLI checks, security checks; fix before completing.
4. **Document completion** — update `AUTONOMOUS_EXECUTION_PLAN.md`,
   `PROJECT_STATUS.md`, `DEVELOPMENT_LOG.md`, relevant technical docs,
   `CHANGELOG.md`, `KNOWN_ISSUES.md`. Record what was implemented, files
   created/modified, decisions, tests run, results, limitations, remaining
   issues, and whether acceptance criteria were met. Never write only
   "phase completed".
5. **Continue automatically** — proceed to the next phase without waiting for
   approval. If a phase needs adjustment, update the plan, explain, continue.

---

## 8. Verification procedures

| Area | Procedure |
| --- | --- |
| Syntax | `find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;` |
| Static analysis | `vendor/bin/phpstan analyse` |
| Coding standard | `vendor/bin/phpcs` |
| Unit/integration | `vendor/bin/phpunit` |
| Database | `php bin/console db:migrate`, `php bin/console db:status`, schema-vs-doc test |
| CLI | `php bin/console list`, each command `--help` |
| API | `php bin/console serve` + integration tests + `curl` transcript |
| Simulation | `php bin/engine-simulate sim:run ... --seed=42` twice, diff output |
| Security | grep sweeps, `EXPLAIN`, regression tests, manual review |
| Docs | command extraction script; schema comparison test |

---

## 9. Testing requirements

* **Unit** — every service, value object, evaluator, state machine.
* **Integration** — core↔module, engine↔services, services↔database,
  API↔engine, wallet↔payout.
* **Framework** — representative mechanics for slot, card, roulette.
* **Transaction** — consistency under normal execution, injected failure
  before/after debit, failure during payout, duplicate requests, concurrent
  requests.
* **Simulation** — reproducible seeded runs; statistics verified against
  hand-computed values.
* No mocks where real code paths can be exercised; where a mock is
  unavoidable, it must be justified in a comment.
* Tests must assert real behaviour, not that a method exists.

Coverage target: ≥ 80% line coverage on `src/Core`, `src/Services`,
`src/Frameworks`; recorded in the final report.

---

## 10. Known risks

| # | Risk | Likelihood | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| R1 | Some target titles have no reliable public mechanics data | High | Low | Document as unverified in `RESEARCH_NOTES.md`; design configurability instead of guessing |
| R2 | Shared hosting lacks some PHP extensions | Medium | Medium | Keep dependency set minimal; feature-detect optional extensions |
| R3 | MySQL row locking behaves differently on shared hosts | Medium | High | Use `SELECT ... FOR UPDATE` inside transactions; degrade to optimistic version checks with documented limits |
| R4 | Scope explosion (18 games) | High | High | Explicitly out of scope; ship engine + 3 fixtures |
| R5 | Exact RTP replication impossible without proprietary data | Certain | Medium | Provide configurable math models; label all simulation output as observed, never as the provider's published RTP |
| R6 | Floats corrupt money | Medium | High | Integer minor-unit `Money`; no floats in balance paths |
| R7 | Idempotency gaps leave double-deduction | Medium | High | Idempotency key on every money-moving endpoint + unique DB constraint |
| R8 | Test suite becomes slow | Medium | Low | Keep DB tests on a separate suite; use seeded RNG and small fixtures |
| R9 | Provider terms / asset licensing | Low | Medium | Independent implementation; no proprietary assets; no scraping of protected endpoints |
| R10 | Environment lacks MySQL | Low | High | Install MariaDB in the dev container; keep a DB-free simulation path |

---

## 11. Completion criteria

The assignment is complete when the section 6 criteria are all met and the
final report is written. A large number of files is not completion. A passing
syntax check is not completion. An interface without an implementation is not
completion. Every claim in the final report must be backed by a recorded
command and its output.
