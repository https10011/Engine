# Changelog

All notable changes to the Universal Casino Game Engine.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added — Phase 0 (2026-11-07)
- `README.md` — project overview, documentation map, quick start.
- `AUTONOMOUS_EXECUTION_PLAN.md` — 15-phase plan with objectives, tasks,
  deliverables, dependencies, acceptance criteria, verification procedures,
  risks and completion criteria.
- `ARCHITECTURE.md` — layering, authoritative round flow with failure matrix,
  service invariants, framework boundaries, ADR log (ADR-1…ADR-10).
- `ENGINE_SPECIFICATION.md` — core contracts, canonical result envelope, state
  semantics, event catalogue, error taxonomy, versioning, core purity
  invariant.
- `RESEARCH_NOTES.md` — evidence-tagged research for 16 slot titles, blackjack
  and roulette; six recorded provider/database conflicts with resolutions;
  explicit research limitations; research-to-feature traceability table.
- `PROJECT_STATUS.md`, `DEVELOPMENT_LOG.md`, `CHANGELOG.md`,
  `KNOWN_ISSUES.md`.

### Added — Phases 1–14 (2026-11-07)
- **Core** — engine bootstrap, game registry, discovery, runtime, lifecycle,
  session/state/result/action contracts, event dispatcher, error taxonomy,
  configuration, logging, `Money` and `Ratio` value objects.
- **Services** — cryptographically secure RNG with a separate seeded generator,
  virtual wallet, betting, payout, session, round orchestration, statistics,
  history, idempotency and transactional coordination, authentication.
- **Frameworks** — Slot (reels, symbols, grid generation, pluggable payline /
  ways / cluster / scatter / composite evaluators, cascades, free spins,
  multipliers); Card (card, deck, hand, blackjack rules and settlement);
  Roulette (variants, bet factory, settlement).
- **Persistence** — PDO connection, repeatable migrations with drift detection,
  repositories, foreign keys, indexes, `CHECK` constraints.
- **API** — HTTP/JSON kernel, router, middleware, thin controllers, consistent
  response envelope, idempotency and rate limiting. `bin/console serve` for
  local development only.
- **CLI** — user, wallet, session, game-listing and purge commands.
- **Simulation** — `bin/engine-simulate` (`sim:run`, `sim:compare`) with seeded
  reproducibility and observed-statistics reporting.
- **Games** — three synthetic fixtures exercising each framework, plus a
  documented SDK example (`CoinFlip`).
- **Tests** — unit, framework, integration, transaction, API and simulation
  layers: 168 tests, 22024 assertions.
- **Docs** — `FRAMEWORK_SLOT.md`, `FRAMEWORK_CARD.md`, `FRAMEWORK_ROULETTE.md`,
  `DATABASE_SCHEMA.md`, `API_SPECIFICATION.md`, `TESTING_STRATEGY.md`,
  `DEPLOYMENT_GUIDE.md`, `SECURITY_AUDIT.md`, `GAME_SDK.md`.

### Fixed — final verification (2026-11-07)
- Seeded sessions produced the identical outcome every round; the round number
  is now folded into the seed so rounds vary while a session stays reproducible.
- `ServeCommand` now refuses to run when `APP_ENV=production`.
- `PASSWORD_ALGO` now selects the password hash algorithm (was hardcoded to
  Argon2id); `ENGINE_VERSION_OVERRIDE` now overrides the reported engine
  version; the registry enforces a minimum SDK contract version. All three were
  previously documented but had no effect.
- `ApiKernel` now resolves the shared `AuthService` from the container instead
  of building a second instance.
- Corrected command names in `AUTONOMOUS_EXECUTION_PLAN.md` to match the shipped
  CLI (`db:migrate`, `db:status`, `bin/engine-simulate`).

### Added — verification tests (2026-11-07)
- `testSeededRoundsVaryAndStayReproducible` — a seeded session varies per round
  yet replays identically under the same seed.
- `testSimulationDriverAgreesWithTheRoundService` — the simulation driver and
  the authoritative round service pay and wager the same on an identical seed.
