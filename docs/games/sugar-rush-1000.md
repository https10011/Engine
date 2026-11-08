# Sugar Rush 1000 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.sugar-rush-1000` |
| Provider (researched) | Pragmatic Play |
| Category | Slot |
| Grid | 7 reels × 7 rows |
| Win model | Cluster pays (5+ adjacent) |
| Volatility | High |
| Max win (published) | 25,000× |
| RTP | 96.00% (configuration; provider page; a 97.5% variant exists) |
| Verification | **[V]** provider page — see `RESEARCH_NOTES.md` §3.14 |

## 1. Researched mechanics

* **7×7 grid**, wins for **five or more adjacent symbols** (cluster pays).
* **Tumble** — whenever a winning symbol explodes its spot is **marked**; a
  second explosion on the same spot adds a multiplier **starting at x2 and
  doubling up to x1,024**. The multiplier applies to all winning combinations
  formed on top of it.
* **3+ scatters → at least 10 free spins** (10/12/15/20/30 for 3/4/5/6/7);
  marked spots and multipliers **remain in place until the round ends** and can
  grow. Additional scatters during the bonus award extra free spins.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `HB` | Heart | Regular | Top premium |
| `ST` | Star | Regular | Premium |
| `SQ` | Square | Regular | Mid |
| `TR` | Triangle | Regular | Mid |
| `DI` | Diamond | Regular | Low |
| `CI` | Circle | Regular | Low |
| `LP` | Lollipop | Scatter | Triggers free spins |

## 3. Paytable

Engine-authored units for a cluster game. `unitDivisor = 1`.

| Symbol | 5–7 | 8–10 | 11–14 | 15+ |
| --- | --- | --- | --- | --- |
| HB | 10 | 40 | 150 | 600 |
| ST | 8 | 30 | 100 | 400 |
| SQ | 6 | 20 | 70 | 250 |
| TR | 5 | 15 | 50 | 180 |
| DI | 4 | 12 | 35 | 120 |
| CI | 3 | 8 | 25 | 80 |
| LP (by count) | 3 → 10, 4 → 12, 5 → 15, 6 → 20, 7 → 30 | | | |

## 4. Feature composition

1. `ClusterEvaluator(5)` + `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `PositionMultiplierFeature` — a position-keyed multiplier grid that doubles
   on each re-explosion, capped at x1,024, and persists across free spins.
4. `FreeSpinsFeature` — `[3 => 10, 4 => 12, 5 => 15, 6 => 20, 7 => 30]`,
   retrigger, cap 100.

## 5. Configuration

`SugarRush1000Configuration` extends `SlotConfiguration` with:

* `cluster_minimum` (default 5)
* `position_multiplier_start` (default 2)
* `position_multiplier_cap` (default 1024)
* `pre_seeded_grid` (default `[]`; used by the optional bonus-buy mode)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `position_multiplier` bucket holding the position-keyed
multiplier grid, which survives between free spins.

## 7. Tests

* A cluster of 4 does not pay; a cluster of 5 does.
* A second explosion on a marked position doubles that position's multiplier.
* The position multiplier is capped at x1,024.
* Position multipliers survive between free spins and reset at round end.
* Free-spin tiers 10/12/15/20/30 for 3/4/5/6/7 scatters.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* Exact cluster paytable and reel weights are not public; the module uses an
  independent, documented table.
* RTP is a configuration value; a market variant (97.5%) exists.
* The optional pre-seeded bonus-buy grid is configuration and is off by default.
