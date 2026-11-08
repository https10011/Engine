# Gates Of Olympus 1000 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.gates-of-olympus-1000` |
| Provider (researched) | Pragmatic Play |
| Category | Slot |
| Grid | 6 reels × 5 rows |
| Win model | Pay-anywhere scatter pays (8+ symbols) |
| Volatility | High |
| Max win (published) | 15,000× |
| RTP | 96.50% (configuration; provider page) |
| Verification | **[V]** provider page — see `RESEARCH_NOTES.md` §3.16 |

## 1. Researched mechanics

* **6×5 grid**; match at least **eight symbols** — **symbols pay anywhere on
  the screen**.
* **Tumble** feature.
* **Multiplier symbols** on all reels with random values **2× to 1,000×**;
  values are **combined at the end of a tumble sequence** and applied to the win.
* **4+ scatters → free spins, starting with 15 free games**.
* During the bonus, a multiplier symbol's value is **added to the round's total
  multiplier**, applied to any win whenever a new symbol hits.
* **3+ scatters during the bonus → +5 free games**.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `ZE` | Zeus | Regular | Top premium |
| `HR` | Hera | Regular | Premium |
| `GO` | Goblet | Regular | Mid |
| `RG` | Ring | Regular | Mid |
| `CN` | Crown | Regular | Low |
| `SN` | Sandal | Regular | Low |
| `MU` | Multiplier | Multiplier | Random value 2×–1,000× |
| `S` | Scatter | Scatter | Pays anywhere; 4+ triggers free spins |

## 3. Paytable

Engine-authored units for a pay-anywhere game. `unitDivisor = 1`.

| Symbol | 8–9 | 10–11 | 12–14 | 15+ |
| --- | --- | --- | --- | --- |
| ZE | 20 | 80 | 300 | 1200 |
| HR | 12 | 50 | 200 | 750 |
| GO | 8 | 30 | 120 | 450 |
| RG | 5 | 20 | 80 | 300 |
| CN | 3 | 12 | 50 | 180 |
| SN | 2 | 8 | 30 | 120 |
| S (by count) | 4 → 15 spins, 5 → 20, 6 → 25 | | | |

## 4. Feature composition

1. `PayAnywhereEvaluator(8)` + `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `SymbolMultiplierFeature` — values combined at the end of a tumble sequence;
   during the bonus, added to the round's accumulating multiplier.
4. `FreeSpinsFeature` — `[4 => 15, 5 => 20, 6 => 25]`, retrigger at 3+ for +5,
   cap 100.

## 5. Configuration

`GatesOfOlympus1000Configuration` extends `SlotConfiguration` with:

* `minimum_match` (default 8)
* `multiplier_symbol` (default `MU`)
* `multiplier_values` (default `[2,3,5,10,25,50,100,250,500,1000]`)
* `free_spin_start` (default 15)
* `retrigger_threshold` (default 3)
* `retrigger_spins` (default 5)
* `round_scoped_multiplier` (default `true`)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `symbol_multiplier` bucket carrying the round-scoped total
during the bonus.

## 7. Tests

* 7 symbols do not pay; 8 symbols do, regardless of position.
* Multiplier values are combined at the end of a tumble sequence.
* During the bonus the multiplier accumulates on the round, not per sequence.
* Free-spin trigger at 4 scatters; retrigger at 3 adds 5.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* Multiplier symbol frequency and weighting are not public; configurable
  approximations are used.
* Bonus buy is deliberately not implemented (virtual credits only, and the
  directive forbids anything resembling a purchase).
