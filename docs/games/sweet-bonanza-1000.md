# Sweet Bonanza 1000 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.sweet-bonanza-1000` |
| Provider (researched) | Pragmatic Play |
| Category | Slot |
| Grid | 6 reels × 5 rows |
| Win model | Cluster pays (8+ anywhere on the grid) |
| Volatility | High |
| Max win (published) | 25,000× |
| RTP | 96.53% (configuration; provider page) |
| Verification | **[V]** provider page — see `RESEARCH_NOTES.md` §3.15 |

## 1. Researched mechanics

* **6×5 grid**; prizes when **eight or more** of the same symbol land on the
  grid (pay-anywhere, not adjacency-limited in the published rule; the engine
  implements the cluster form for the sweet family and documents the choice).
* **Tumbles**.
* **At least four lollipops → bonus game with 10 free spins**.
* **Candy bomb multipliers** award random values **up to 1,000×**; multipliers
  **remain on screen during tumbles**, are **added together**, and applied to
  the total win.
* **Five further free spins** when three or more lollipops land during the bonus.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `AP` | Apple | Regular | Top premium |
| `BN` | Banana | Regular | Premium |
| `GR` | Grape | Regular | Mid |
| `WM` | Watermelon | Regular | Mid |
| `PL` | Plum | Regular | Low |
| `CH` | Cherry | Regular | Low |
| `CB` | Candy Bomb | Multiplier | Random value up to 1,000× |
| `LP` | Lollipop | Scatter | Triggers free spins |

## 3. Paytable

Engine-authored units for a cluster game. `unitDivisor = 1`.

| Symbol | 8–9 | 10–11 | 12–14 | 15+ |
| --- | --- | --- | --- | --- |
| AP | 25 | 100 | 400 | 1500 |
| BN | 15 | 60 | 250 | 900 |
| GR | 10 | 40 | 150 | 550 |
| WM | 6 | 25 | 90 | 350 |
| PL | 4 | 15 | 60 | 220 |
| CH | 3 | 10 | 40 | 150 |
| LP (by count) | 4 → 10 spins, 5 → 12, 6 → 15 | | | |

## 4. Feature composition

1. `ClusterEvaluator(8)` + `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `SymbolMultiplierFeature` — candy bomb multipliers accumulate within a tumble
   sequence and are summed and applied to the sequence's total.
4. `FreeSpinsFeature` — `[4 => 10, 5 => 12, 6 => 15]`, retrigger at 3+ for +5,
   cap 100.

## 5. Configuration

`SweetBonanza1000Configuration` extends `SlotConfiguration` with:

* `cluster_minimum` (default 8)
* `multiplier_symbol` (default `CB`)
* `multiplier_values` (default `[2,3,5,10,25,50,100,250,500,1000]`)
* `retrigger_threshold` (default 3)
* `retrigger_spins` (default 5)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `symbol_multiplier` bucket carrying the sequence total.

## 7. Tests

* A cluster of 7 does not pay; a cluster of 8 does.
* Candy bomb multipliers are summed and applied once.
* Free-spin trigger at 4 lollipops; retrigger at 3 adds 5.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* The published rule is "eight or more symbols anywhere on the grid"; the
  engine implements the 8+ cluster form and records the choice explicitly.
* Candy bomb frequency and value weighting are not public; configurable
  approximations are used.
