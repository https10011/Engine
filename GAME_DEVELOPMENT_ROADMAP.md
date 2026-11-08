# Game Development Roadmap — Phases 15 onward

> Implementation roadmap for the 18 individual casino games, the complete
> frontend experience and the global administrator probability panel, built on
> top of the completed engine (phases 0–14).
>
> **Current phase:** see the status board at the bottom of this file.
> **Last updated:** Phase 22 (Roulette) — table games complete.

---

## 0. Where this roadmap starts

Phases 0–14 delivered the *engine*: core, services, three frameworks, SDK,
persistence, HTTP API, CLI, simulation, tests and documentation, plus three
synthetic fixtures. `PROJECT_STATUS.md` records that honestly.

This roadmap covers what the directive calls Phase 15 onward: the 18 named
games, the frontend and the administrator probability panel.

**Preserved invariants (must not regress):**

* `src/Core` stays game-agnostic (enforced by `GameModulePurityTest` and the
  layering test).
* A game module never moves money; it returns ratios.
* Randomness comes only from `$context->rng()`.
* The 168 pre-existing tests keep passing.
* Virtual credits only — no deposits, withdrawals, payments or crypto.

---

## 1. Architectural decisions taken for this phase

| # | Decision | Rationale |
| --- | --- | --- |
| ADR-11 | One `AbstractSlotGame` base supplies the SDK glue; each of the 16 slots supplies its own symbols, strips, paytable, evaluator, feature set, metadata, configuration, state extensions, tests and specification | 16 copies of the same 90 lines of interface plumbing is not 16 implementations, it is 16 opportunities to diverge. The *game* is its maths and mechanics, and those stay per-title. |
| ADR-12 | Probability configuration is a **service**, not a frontend setting | The directive requires the engine to actually change behaviour; sliders that only change a displayed number are explicitly forbidden. |
| ADR-13 | Probability targets are applied by **bounded rejection sampling** (re-spin) and a **disclosed payout scale**, never by fabricating an outcome | An accepted outcome is always a genuine spin of the game's own reels. A target that cannot be reached under a game's rules is reported, not faked. |
| ADR-14 | Jackpot is a separate, independently configured roll | The directive requires jackpot probability to be distinguishable from ordinary wins. |
| ADR-15 | Admin authentication reuses the bearer-token model with a `role` column | One auth path, one place to audit. No cookie session means no CSRF surface (recorded in `KNOWN_ISSUES.md`). |
| ADR-16 | The frontend is server-rendered PHP pages plus progressive-enhancement JavaScript that posts to the existing JSON API | The deployment target is shared hosting with no Node runtime and no WebSockets. Every outcome still comes from the API. |

---

## 2. Phase plan

### PHASE 15 — Repository audit and specifications — **COMPLETE**

* Audited the engine, frameworks, SDK, persistence, API, tests and fixtures.
* Confirmed the framework already provides: payline / ways / cluster / scatter
  evaluators, composite evaluation, cascades, combo multiplier trail, free
  spins with retrigger, ragged grids, per-reel heights.
* Identified missing capabilities (below) and the games that need them.
* Wrote `GAME_DEVELOPMENT_ROADMAP.md` (this file).
* Wrote 18 individual game specifications under `docs/games/`.
* Established per-game and global acceptance criteria (§5, §6).

**Missing capabilities identified by the audit:**

| Gap | Needed by |
| --- | --- |
| Pay-anywhere evaluation (8+ of a symbol anywhere) | Sweet Bonanza 1000, Gates of Olympus 1000 |
| Position-keyed persistent multipliers | Sugar Rush 1000 |
| Random multiplier symbols summed per tumble sequence | Gates of Olympus 1000, Wild Bounty Showdown |
| Gold-frame → wild transformation on elimination | Super Ace family, Piñata Wins, Wild Bounty Showdown, Mahjong Hit |
| Multi-stage symbol progression (silver → gold → wild) | Treasure of Aztec |
| Expanding reel heights mid-spin | Golden Empire, Treasure of Aztec |
| Symbol splitting on a win | Fortune Gems 3 |
| Auxiliary (side) multiplier reel | Fortune Gems 2, Fortune Garuda 1000 |
| Nudge mechanic | Fortune Garuda 1000 |
| Bonus wheel / pick bonus round | Fortune Gems 2 |
| Jackpot roll | configurable, per-game |
| Round-scoped accumulating multiplier | Gates of Olympus 1000 |
| Persistent feature multiplier (never resets) | Treasure of Aztec, Piñata Wins |
| Configurable trigger/retrigger thresholds | Gates of Olympus 1000, Sweet Bonanza 1000 |

### PHASE 16 — Shared infrastructure extensions

Deliverables:

1. `src/Services/Probability/` — centralised probability configuration service:
   `ProbabilityProfile` (value), `ProbabilityConfigService` (resolve, validate,
   version, audit), `ProbabilityRepository`, `WinFrequencyGovernor`,
   `JackpotService`, `PayoutScale`.
2. Migration `2026_01_02_000001_create_probability_tables.sql` —
   `probability_profiles`, `probability_overrides`, `probability_versions`,
   `probability_audit_log`, `jackpot_events`.
3. Migration `2026_01_02_000002_add_admin_role.sql` — `users.role`.
4. `GameContext` gains an optional `ProbabilityProfile`; `RoundService` resolves
   the effective profile per game and hands it to the module. Absent profile =
   engine default behaviour, so every pre-existing module is unaffected.
5. New slot evaluators: `PayAnywhereEvaluator`, `AuxiliaryReelEvaluator`.
6. New slot features: `SymbolTransformationFeature`, `PositionMultiplierFeature`,
   `SymbolMultiplierFeature`, `ExpandingReelsFeature`, `NudgeFeature`,
   `SymbolSplitFeature`, `BonusWheelFeature`, `JackpotFeature`,
   `PersistentMultiplierFeature`.
7. `AbstractSlotGame` SDK base and `SlotGameDefinition` (a typed bundle of the
   pieces a slot module supplies).
8. Admin authentication: `AdminService`, `AdminMiddleware`, admin routes.
9. Tests for every extension.

Completion criteria: every extension has unit tests; the 168 pre-existing tests
still pass; `composer check` is green; documentation updated.

### PHASE 17 — Slot games, batch 1

1. Super Ace
2. Piñata Wins
3. Wild Bounty Showdown
4. Mahjong Hit

### PHASE 18 — Slot games, batch 2

5. Golden Empire
6. Sugar Bang Bang 2
7. Fortune Gems 3
8. Swerte Fiesta

### PHASE 19 — Slot games, batch 3

9. Super Ace Deluxe
10. Fortune Garuda 1000
11. Sugar Rush 1000
12. Fortune Gems 2

### PHASE 20 — Slot games, batch 4

13. Treasure of Aztec
14. Sweet Bonanza 1000
15. Super Ace 2
16. Gates of Olympus 1000

Each slot game delivers: module class, configuration class, symbol set, reel
strips, paytable, evaluator, features, state extensions where the mechanic needs
them, metadata, registration, unit tests, integration coverage and a
specification document.

### PHASE 21 — Blackjack

Full playable blackjack on the card framework: shoe management, shuffle,
dealing, player hands, dealer hand, hand evaluation, hit / stand / double /
split / insurance / surrender, dealer behaviour, betting, payout, round
lifecycle, history, error handling. Rules are configuration.

### PHASE 22 — Roulette

Full playable roulette on the roulette framework: wheel configuration (European
default, American and French selectable), number generation, inside and outside
bets, winning-number determination, payout evaluation, round lifecycle, betting
limits, history, error handling.

### PHASE 23 — Game integration

Register all 18, verify initialisation, configuration validation, round
execution, payout correctness, session isolation and state non-leakage.
Integration tests across all 18 games.

### PHASE 24 — Frontend

* Lobby, game catalog, categories, search and filtering, game cards,
  responsive layout, navigation, loading and error states.
* 16 individually recognisable slot interfaces (grid presentation, spin, bet
  controls, balance, win display, paytable, rules, bonus and free-spin
  presentation, multipliers, animations, sound controls, responsive).
* Blackjack interface (cards, betting, hit/stand/double/split/insurance,
  results, payouts, animation).
* Roulette interface (wheel, betting table, inside/outside bets, place/remove,
  spin, winning number, payouts).

Every outcome comes from the API. No inert controls. No placeholder screens.

### PHASE 25 — Administrator panel frontend

Overview dashboard, global probability settings, per-game settings, game
configuration cards, sliders and numeric fields, toggles, save, reset-to-global,
configuration comparison, simulation interface and reports, configuration
history, audit-log viewer.

### PHASE 26 — Final verification and publication

Full test suite, PHP syntax check, coding standards, static analysis, security
review, documentation updates, commit and push, remote verification.

---

## 3. Shared infrastructure requirements (Phase 16 detail)

### 3.1 Probability configuration service

Two configuration levels:

* **Global profile** — defaults for all games.
* **Per-game override** — takes precedence for that game; can be cleared to
  fall back to global.

Separately represented and configured metrics (the directive is explicit that
these must not be conflated):

| Metric | Meaning | How it is applied |
| --- | --- | --- |
| `any_win_target` | Probability a round produces any payout | Bounded re-spin governor at the module boundary |
| `net_positive_target` | Probability payout exceeds wager | Reported; achieved through `any_win_target` plus the game's own maths; a game whose payouts are all sub-1x reports the limitation |
| `rtp_target` | Expected payout ÷ wagered over many rounds | Disclosed payout scale factor + observed verification |
| `jackpot_probability` | Probability of triggering a jackpot | Independent jackpot roll |
| `bonus_trigger_probability` | Probability of a bonus feature | Trigger-threshold governor |
| `free_spin_trigger_probability` | Probability of free spins | Trigger-threshold governor |

Every profile is validated, versioned and audited. A change never alters a round
already in progress or already completed.

### 3.2 How a target is actually applied

The governor never invents an outcome. It draws a genuine outcome from the
module, and either accepts it or draws again, up to a configured bound:

```
draw outcome
while attempts < bound and outcome-class != target-class:
    draw outcome again
accept outcome
```

* Every accepted outcome is a real spin of the game's own reels.
* The bound is finite, so a target that is unreachable under the game's real
  rules (for example a 95% any-win target on a 3×3 five-line game) is reported
  as unreachable with the observed rate, rather than faked.
* The RNG stream stays independent; the target is a *distribution* goal, not a
  "every second spin wins" rule.

`rtp_target` is applied as a disclosed payout scale applied to the outcome
multiplier, which is mathematically identical to rescaling the paytable. The
effective configuration is exposed to players through the API, satisfying the
transparency requirement.

### 3.3 Jackpot

Independent roll per configured game, with tiers, trigger probability, payout,
history and simulation. Distinct from any-win, bonus and free-spin metrics.

### 3.4 Administrator security

* `role` column on `users`; admin routes require the `admin` role server-side.
* Password hashing reuses `AuthService` (Argon2id, configurable).
* Every configuration change is written to `probability_audit_log` with the
  administrator identity, the before and after configuration, the affected
  games, the timestamp and the version.
* A regular player token is rejected by every admin route with 403, proven by
  test.

---

## 4. Expected files and components

```
src/Services/Probability/            probability configuration service
src/Services/Jackpot/                jackpot service
src/Services/Admin/                  administrator service
src/Frameworks/Slot/Evaluation/      PayAnywhereEvaluator, AuxiliaryReelEvaluator
src/Frameworks/Slot/Feature/         new features (see §2 Phase 16)
src/Frameworks/Slot/Sdk/             AbstractSlotGame, SlotGameDefinition
src/Games/Slots/<Title>/             one directory per slot game
src/Games/Blackjack/                 blackjack module
src/Games/Roulette/                  roulette module
src/Api/Controller/Admin*            admin controllers
public/                              frontend: lobby, game pages, admin pages,
                                     css/, js/, assets/
docs/games/                          one specification per game
```

---

## 5. Testing strategy

| Layer | What it proves |
| --- | --- |
| Unit — framework extensions | Each new evaluator and feature against pinned grids and known paytables |
| Unit — probability service | Resolution order, override precedence, validation, versioning, audit |
| Unit — each game module | Metadata, configuration validation, initial state, action validation, winning and losing paths, special mechanics, payout rules, state round-trip |
| Unit — blackjack | Hand evaluation, soft/hard, naturals, splits, doubles, insurance, dealer rules, settlement multipliers |
| Unit — roulette | Every bet type, payout ladder, la partage / en prison, variant geometry |
| Integration — all 18 | Registration, session creation, full round through `RoundService`, wallet movement, session isolation, state non-leakage |
| Integration — admin | Authorisation, override precedence, config application, existing-round integrity |
| Simulation | Observed RTP and hit frequency per game, labelled as observed with sample size and seed |
| Regression | The 168 pre-existing tests |

Statistical tests are diagnostics, never substitutes for verifying the rules.

---

## 6. Acceptance criteria

### Per game

- [ ] Module class in a discovered namespace; `games:list` shows it.
- [ ] Own metadata (id, name, provider, volatility, max win, RTP where known).
- [ ] Own typed configuration with validation and unknown-key rejection.
- [ ] Own symbol set, reel strips and paytable.
- [ ] Own evaluator and feature composition reflecting its researched mechanics.
- [ ] Own state extensions where the mechanic needs persistence.
- [ ] Own unit tests covering the winning path, the losing path and each special
      mechanic, plus state round-trip and configuration rejection.
- [ ] Own specification document under `docs/games/`.
- [ ] Integration coverage through `RoundService`.

### Global

- [ ] All 16 slots individually implemented.
- [ ] Blackjack fully implemented.
- [ ] Roulette fully implemented.
- [ ] All 18 registered; every game starts a round and settles a payout.
- [ ] Session isolation and state non-leakage verified by test.
- [ ] Frontend lobby, catalog and 18 game interfaces, backend-authoritative.
- [ ] Administrator panel with global and per-game probability configuration.
- [ ] Any-win, net-positive, RTP, jackpot, bonus and free-spin metrics separate.
- [ ] Configuration versioning, history and audit logging.
- [ ] Simulation and statistical verification dashboard.
- [ ] No inert controls; no placeholder screens presented as completed games.
- [ ] Existing engine tests, PHP syntax, coding standards and static analysis pass.
- [ ] Documentation updated; changes committed and pushed; remote verified.

---

## 7. Research integrity

Game mechanics come from `RESEARCH_NOTES.md`, which tags every claim **[V]**
verified, **[O]** observed, **[E]** estimated or **[U]** unverified.

* Three titles (`Mahjong Hit`, `Sugar Bang Bang 2`, `Swerte Fiesta`) have no
  verified source. They are implemented as **clearly labelled configurable
  approximations** built from the closest verified related mechanics, and their
  specifications state exactly what was assumed.
* `Fortune Garuda 1000` does not match a verifiable product; the closest is
  `Fortune Garuda 500`. It is implemented as a documented 1000x variant of that
  mechanic set.
* Reel strips, weights and paytables are independent implementations, not
  replicas. No proprietary source, API or asset is used. Artwork is original
  CSS/SVG created for this project.

---

## 8. Status board

| Phase | Title | Status |
| --- | --- | --- |
| 15 | Repository audit and specifications | **Complete** |
| 16 | Shared infrastructure extensions | **Complete** |
| 17 | Slot games batch 1 | **Complete** |
| 18 | Slot games batch 2 | **Complete** |
| 19 | Slot games batch 3 | **Complete** |
| 20 | Slot games batch 4 | **Complete** |
| 21 | Blackjack | **Complete** |
| 22 | Roulette | **Complete** |
| 23 | Game integration | Not started |
| 24 | Frontend | Not started |
| 25 | Administrator panel frontend | Not started |
| 26 | Final verification and publication | Not started |
