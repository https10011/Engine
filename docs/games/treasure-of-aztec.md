# Treasure of Aztec — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.treasure-of-aztec` |
| Provider (researched) | PG Soft (verified title: *Treasures of Aztec*) |
| Category | Slot |
| Grid | 6 reels with expanding heights |
| Win model | Up to 32,400 ways |
| Volatility | Medium–High |
| Max win (published) | 9,071× |
| RTP | 96.71% (configuration) |
| Verification | **[O]** Bigwinboard review — see `RESEARCH_NOTES.md` §3.17 |

## 1. Researched mechanics

* **6×5 grid with expanding reel heights**, up to **32,400 ways**.
* **Cascading wins**; the multiplier **starts at x1** in the base game and
  increases per cascade.
* **Wilds on the Way** — symbols land with **silver frames**; if they win they
  upgrade to **gold frames**; if those win again they become **wilds** (a
  three-stage per-cell progression).
* **4 scatters → 10 free spins**, extra scatters add more; in free spins the
  multiplier **starts at x2 and increases by x2 per cascade** and **never
  resets** on a dead spin.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `MZ` | Mask | Regular | Top premium |
| `SN` | Sun Stone | Regular | Premium |
| `JG` | Jaguar | Regular | Mid |
| `TP` | Temple | Regular | Mid |
| `SC` | Scroll | Regular | Low |
| `PT` | Pot | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere; 4+ triggers free spins |

## 3. Paytable

Engine-authored units for a ways game. `unitDivisor = 1`.

| Symbol | 3 | 4 | 5 | 6 |
| --- | --- | --- | --- | --- |
| MZ | 40 | 130 | 400 | 1000 |
| SN | 25 | 80 | 250 | 600 |
| JG | 15 | 50 | 150 | 350 |
| TP | 10 | 30 | 90 | 200 |
| SC | 6 | 20 | 60 | 130 |
| PT | 4 | 12 | 40 | 90 |
| W | 60 | 200 | 600 | 1500 |
| S (by count) | 4 → 10 spins, 5 → 15, 6 → 20 | | | |

## 4. Feature composition

1. `WaysEvaluator` + `ScatterEvaluator` via `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `SymbolProgressionFeature` — silver → gold → wild, per cell, surviving
   between cascade steps.
4. `PersistentMultiplierFeature` — base starts at x1 and rises per cascade;
   free spins start at x2 and rise by x2 per cascade and never reset on a dead
   spin.
5. `ExpandingReelsFeature` — reel heights grow during the spin.
6. `FreeSpinsFeature` — `[4 => 10, 5 => 15, 6 => 20]`, retrigger, cap 100.

## 5. Configuration

`TreasureOfAztecConfiguration` extends `SlotConfiguration` with:

* `base_heights` (default `[3,3,3,3,3,3]`)
* `max_height` (default `5`)
* `base_multiplier_start` (default 1)
* `free_spin_multiplier_start` (default 2)
* `free_spin_multiplier_step` (default 2)
* `free_spin_multiplier_persists` (default `true`)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `symbol_progression` bucket holding each cell's frame stage
and a `persistent_multiplier` bucket.

## 7. Tests

* Ways are recomputed when a reel height changes.
* A silver frame that wins becomes gold; a gold frame that wins becomes wild.
* The base multiplier starts at x1; the free-spin multiplier starts at x2 and
  rises by x2 per cascade.
* The free-spin multiplier does not reset on a dead spin.
* Free-spin trigger at 4 scatters.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* The directive names "Treasure of Aztec"; the verified title is
  *Treasures of Aztec* (PG Soft). The module uses the directive's name and the
  verified mechanic set.
* Expansion rules, ways derivation and reel weights are not public;
  configurable approximations are used.
