# Mahjong Hit — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.mahjong-hit` |
| Provider (researched) | Unverified title — implemented as a documented Mahjong-style approximation |
| Category | Slot |
| Grid | 5 reels × 5 rows |
| Win model | 2,000 ways |
| Volatility | Medium–High |
| Max win (published) | 100,000× (configuration) |
| RTP | 96.95% (configuration, from the closest verified related title) |
| Verification | **[U]** unverified title; mechanics modelled on verified **Mahjong Ways** (PG Soft) and **Mahjong Wins Super Scatter** (Pragmatic Play) — see `RESEARCH_NOTES.md` §3.11 |

## 1. Researched mechanics

No reliable source describes a game titled exactly *Mahjong Hit*. The module
is therefore a **clearly labelled configurable approximation** built from the
verified Mahjong-family mechanics:

* **2,000 ways** on a 5×5 grid.
* **Gold-plated tiles → Wild** when eliminated by a cascade.
* **Progressive win multiplier** x1, x2, x3, x5 in the base game; x2, x4, x6, x10
  during free spins.
* **3 scatters → 10 free spins**, retriggerable.
* A **Super Scatter prize ladder** is available as an optional configuration
  (50× / 500× / 5,000× / 100,000×) reflecting the related Pragmatic title.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `T1` | Dragon Tile | Regular | Top premium |
| `T2` | Bamboo Tile | Regular | Premium |
| `T3` | Circle Tile | Regular | Mid |
| `T4` | Character Tile | Regular | Mid |
| `T5` | Flower Tile | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |
| `SS` | Super Scatter | Scatter | Optional prize ladder (disabled by default) |

## 3. Paytable

Engine-authored units for a ways game. `unitDivisor = 1`.

| Symbol | 3 | 4 | 5 |
| --- | --- | --- | --- |
| T1 | 40 | 160 | 600 |
| T2 | 25 | 100 | 400 |
| T3 | 15 | 60 | 220 |
| T4 | 10 | 40 | 150 |
| T5 | 6 | 25 | 90 |
| W | 60 | 250 | 1000 |
| S (by count) | 3 → 50, 4 → 150, 5 → 500 | | |
| SS (by count) | 3 → 5000, 4 → 10000, 5 → 100000 | | |

## 4. Feature composition

1. `WaysEvaluator` + `CompositeEvaluator(ScatterEvaluator)`.
2. `CascadeFeature` (max 20).
3. `SymbolTransformationFeature` — gold tile → Wild on elimination.
4. `ComboMultiplierFeature` — `[1,2,3,5]` base, `[2,4,6,10]` free spins.
5. `FreeSpinsFeature` — `[3 => 10]`, retrigger, cap 100.
6. Optional `ScatterPrizeLadderFeature` for the Super Scatter configuration.

## 5. Configuration

`MahjongHitConfiguration` extends `SlotConfiguration` with:

* `gold_tile_symbol` (default `T1`)
* `free_spin_multiplier_ladder` (default `[2,4,6,10]`)
* `super_scatter_enabled` (default `false`)
* `super_scatter_ladder` (default `[3 => 5000, 4 => 10000, 5 => 100000]`)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `transformation` bucket.

## 7. Tests

* 2,000 ways on a pinned grid equals the hand-computed units.
* Gold tile → Wild on elimination.
* Multiplier ladders differ between base and free spins.
* Free-spin trigger and retrigger.
* Super Scatter ladder pays only when enabled; disabled by default.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] The unverified status of the title is stated in the module metadata and here.

## 9. Limitations

* **This title is unverified.** No provider, catalogue or review database
  describes a game with this exact name. The implementation is an explicit,
  documented approximation of the Mahjong-family mechanics, not a recreation.
* The module metadata marks the provider as
  `Engine Approximation (unverified title)` so the status is visible in the API.
