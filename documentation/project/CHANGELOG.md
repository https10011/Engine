# Changelog

All notable changes to the Universal Casino Game Engine.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added — private-app refinement (2026-11-09)
- Phone-based player registration (phone, display name, age, password), canonical
  `+63` phone storage and a unique index; internal usernames are generated and
  never chosen by players. First grant is exactly 300 credits, once.
- Local demo OTP registration step with browser notification and in-app fallback;
  exactly six numeric digits are required, any six are accepted, and no SMS or
  third-party provider is involved.
- Google/Facebook "Continue with …" presentation buttons.
- `config/database.php` loader plus git-ignored `config/database.local.php`;
  the production bundle no longer requires database values in `.env`.
- Standalone `/admin.php`: dashboard, player search/detail, audited credit
  adjustments, suspend/reactivate, game availability, odds/jackpot/bonus
  controls, friendly-play preset, audit trail, CSRF, session regeneration,
  lockout and secure logout.
- `game_controls` table and `GameControlService`, enforced by the game catalogue
  and session creation.
- AH77_SOURCE WebP derivatives for 16 slot titles, blackjack card-room art,
  roulette wheel art and three lobby banner crops; local CSS/JS win, jackpot,
  card, roulette and coin effects.
- `user:password` CLI command for rotating the seeded administrator password.
- Admin/game-control integration tests and phone normalisation unit tests.

### Added — AURUM player frontend and shared-hosting bundle (2026-11-08)
- `public/app.html`, `public/assets/{css,js,games,banners,brand}` — a complete
  mobile-first player interface: catalogue lobby, carousel, category filters,
  search, login/registration, slot, blackjack, roulette and coin-flip views,
  history and an administrator probability panel. All outcomes come from the
  API; the client computes nothing.
- `POST /api/v1/auth/register` — additive self-service registration with a
  configurable virtual-credit starting balance and an off switch.
- `htdocs/` — self-contained production application for PHP/MySQL shared
  hosting, with `database/install.sql`, runtime Composer autoloader, deny rules
  and an operator README. Built by `tools/build_htdocs.php`.
- `documentation/` reorganized into backend, frontend, deployment, project and
  reports; `FINISHED_ANALYSIS_REPORT.md` added at the root.
- `tools/build_htdocs.php`, mobile/desktop Playwright checks, source inventory
  and asset generators.

### Fixed — production compatibility (2026-11-08)
- Argon2id is no longer a hard boot dependency: the configured algorithm is
  resolved at runtime and falls back to bcrypt, recording the algorithm used.
- The front controller distinguishes the `public/` development layout from the
  self-contained `htdocs/` layout instead of resolving to a parent repository.

### Added — jackpot configuration and settlement (2026-11-08)
- `SpinResult::hasJackpot()` / `jackpotTier()` — a jackpot is now distinguishable
  from an ordinary win by reading the round's feature transitions.
- `AbstractSlotGame` adds a triggered jackpot prize to the reels' own payout
  before the max-win cap, so a jackpot and a line win are never double-counted.
- `RoundService::recordJackpot()` writes every trigger to `jackpot_events` in
  `afterCommit()`, giving the administrator panel an append-only jackpot
  history. `ProbabilityRepository` is the sole writer.
- `SimulationStatistics` counts jackpot, bonus and free-spin triggers separately
  and exposes per-feature rates; the simulation report and
  `AdminController::compare()` now report the observed jackpot rate against the
  configured probability.
- `documentation/backend/ADMIN_PANEL.md` — the jackpot lifecycle, the corrected data model and the
  governor's jackpot exemption are documented.

### Fixed — jackpot probability was deflatable (2026-11-08)
- `WinFrequencyGovernor` could re-draw a jackpot round when an any-win target was
  set, silently reducing the configured jackpot probability below its value. A
  jackpot outcome is now always accepted.

### Added — frontend and administrator panel
- `public/app.html` and `public/assets/` — lobby, game catalog with search and
  filtering, and the slot, blackjack and roulette interfaces. All outcomes come
  from the API; the client computes no result.
- `/api/v1/admin/*` routes, `AdminMiddleware`, `AdminController` and
  `public/assets/js/views/admin.js` — global and per-game probability
  configuration, jackpot and bonus settings, configuration history, audit log
  and the simulation/verification tool, gated by the `admin` role.

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
