# Global Administrator Panel — Specification

> Control and configuration of game probabilities, winning frequency, payout
> behaviour, jackpot probability and game-specific probability settings.
>
> **Scope:** simulated credits only. This panel configures a simulated casino's
> game mathematics. It contains no deposit, withdrawal, payment or
> cryptocurrency functionality, and none will be added.

---

## 1. Objective

A dedicated, secure administrator area where an authorised administrator can
change a game's probability policy centrally, without editing source files, and
have the engine actually apply it.

The directive is explicit on one point: a slider that only changes a displayed
number is not an implementation. Every control here changes real engine
behaviour.

---

## 2. Architecture

```
Admin frontend (public/admin/*)
      │  JSON over the existing API envelope
      ▼
Admin controllers (src/Api/Controller/Admin*)
      │  require the `admin` role server-side
      ▼
ProbabilityConfigService ──── ProbabilityRepository ──── DB
      │                              │
      │                              └── probability_profiles / overrides
      │                                  probability_versions / audit log
      ▼
RoundService::buildContext()
      │  resolves the effective profile for the game
      ▼
GameContext::probabilityProfile()
      │
      ▼
Slot governor (WinFrequencyGovernor / JackpotService / trigger governors)
      │
      ▼
The module's own evaluator — the outcome is always a genuine spin
```

---

## 3. Data model

| Table | Purpose |
| --- | --- |
| `probability_profiles` | One row per named profile; `is_global` marks the active global profile |
| `probability_overrides` | Per-game override rows; a row's absence means "use global" |
| `probability_versions` | Immutable snapshot of a configuration version |
| `probability_audit_log` | Every change: administrator, before, after, games, time, version |
| `jackpot_events` | Recorded jackpot triggers for history and verification |

### Fields on a profile / override

| Field | Type | Meaning |
| --- | --- | --- |
| `any_win_target` | decimal(5,4) null | Probability a round produces any payout |
| `net_positive_target` | decimal(5,4) null | Probability payout exceeds wager |
| `rtp_target` | decimal(6,4) null | Expected payout ÷ wagered |
| `jackpot_probability` | decimal(8,6) null | Probability of a jackpot trigger |
| `bonus_trigger_probability` | decimal(5,4) null | Probability of a bonus feature |
| `free_spin_trigger_probability` | decimal(5,4) null | Probability of free spins |
| `payout_scale` | decimal(6,4) null | Disclosed payout scale applied to the multiplier |
| `jackpot_tiers` | JSON null | Tier name → {probability, payout_multiplier} |
| `enabled` | tinyint | Whether the policy is applied at all |
| `max_resample_attempts` | smallint | Bound on the governor's re-draw loop |

`null` means "do not constrain this metric".

---

## 4. The metrics are separate

The directive requires these to be distinct, and they are distinct in the
schema, in the service and in the dashboard:

| Metric | Definition | Effect |
| --- | --- | --- |
| Any-win | A round produced a payout > 0 | Governor re-draws losing spins up to the bound |
| Net-positive | Payout > wager | Achieved through the game's own maths; a game whose payouts are all sub-1× reports the limitation |
| RTP | Σ payout ÷ Σ wagered over many rounds | Disclosed payout scale |
| Jackpot | A jackpot tier triggered | Independent roll, outside the normal win |
| Bonus trigger | A bonus round started | Trigger-threshold governor |
| Free-spin trigger | Free spins awarded | Trigger-threshold governor |

A 50% any-win target is **not** 50% RTP, and it is **not** "every second spin
wins". The RNG stays independent; the target is a distribution goal verified
statistically over a simulation.

---

## 5. How a target is applied without faking an outcome

```
draw a genuine outcome from the module
attempts = 1
while attempts < max_resample_attempts
      and classification(outcome) != target_class:
    draw another genuine outcome
    attempts++
accept the last outcome drawn
```

* Every accepted outcome is a real spin of the game's own reels.
* The loop is bounded, so an unreachable target cannot hang a request.
* When the bound is exhausted, the observed rate is recorded and reported; the
  dashboard shows "target 95%, observed 47%, unreachable under this game's
  rules" rather than a false compliance claim.

`rtp_target` is applied as `payout_scale`, a disclosed factor on the outcome
multiplier — mathematically identical to rescaling the paytable, and visible to
players through the API.

---

## 6. Resolution order

1. Per-game override row, when present and enabled.
2. Global profile row.
3. Engine default (no constraint).

An override takes precedence over global for that game. The dashboard shows
which games use global settings and which have overrides, and offers
reset-to-global.

---

## 7. Security

| Control | Implementation |
| --- | --- |
| Administrator-only routes | `AdminMiddleware` checks the `admin` role from the authenticated token |
| Server-side authorisation | The role comes from the database, never from the request |
| Secure password handling | Reuses `AuthService` (Argon2id) |
| Session protection | Bearer tokens, hashed at rest, TTL-bounded, revocable |
| CSRF | Not applicable to bearer-token auth (recorded in `KNOWN_ISSUES.md`) |
| Input validation | Every field range-checked server-side before it is stored |
| Change auditing | `probability_audit_log` row per change |
| Unauthorised modification | A player token receives 403 on every admin route, proven by test |

---

## 8. Configuration application

On save:

1. Validate every submitted value.
2. Resolve global and per-game overrides.
3. Create a new configuration version (immutable snapshot).
4. Write the audit-log row with before and after state.
5. Apply to newly initialised rounds only.
6. Leave rounds already in progress untouched.
7. Expose the effective configuration to the game execution services and to the
   player-facing API (transparency).

A change never retroactively alters a completed round.

---

## 9. Simulation and verification

The administrator selects a game, a configuration and a round count; the engine
runs the simulation **without touching any live balance or game history** and
returns:

* total rounds, winning rounds, any-win frequency, net-positive frequency
* total wagered, total paid, observed RTP
* jackpot, bonus and free-spin trigger counts
* expected vs observed probability, standard error and a 95% confidence interval

The report always distinguishes the theoretical configured target from the
observed result.

---

## 10. Frontend

`public/admin/` provides: overview dashboard, global probability settings,
per-game settings with cards and sliders, numeric inputs, enable/disable
toggles, save, reset-to-global, configuration comparison, simulation interface,
simulation reports, configuration history and an audit-log viewer.

The interface explains the difference between winning probability, RTP, jackpot
probability and bonus probability in plain language, and is responsive.

---

## 11. Testing

* Global configuration save and resolve.
* Per-game override precedence over global.
* Reset-to-global.
* Validation rejects out-of-range values and unknown keys.
* Version creation and audit logging on every change.
* Administrator authorisation and unauthorised rejection (403 for a player).
* Configuration application to new rounds; existing rounds unchanged.
* Governor: a target is approached statistically and never by fabrication.
* An unreachable target is reported, not silently claimed.
* All 18 games resolve their effective configuration.
* No regression in the existing engine tests.

---

## 12. Transparency and scope

* Configurable odds belong to the simulated-credit environment.
* The configuration is an explicit game configuration, not a hidden mechanism
  for targeting individual players.
* No player-specific outcome manipulation, no undisclosed selective losing, no
  hidden override that contradicts the published configuration.
* Where a player can see game odds or rules, the displayed information matches
  the active configuration.
