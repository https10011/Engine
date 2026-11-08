# Golden Empire — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.golden-empire` |
| Provider (researched) | JILI Games, catalogue id 103 |
| Category | Slot |
| Grid | 6 reels, expanding heights |
| Win model | 32,400 megaways |
| Volatility | Low–Medium |
| Max win (published) | 2,000× |
| RTP | 97.0% (configuration) |
| Verification | **[V]** provider page — see `RESEARCH_NOTES.md` §3.6 |

## 1. Researched mechanics

* **32,400 megaways** — the ways count is recomputed from the grid's actual
  shape each evaluation, so an expanded reel increases the ways.
* The reel surface **turns into Wilds** for a seamless cascade of wins.
* **Eliminating a single symbol expands the reel**; the larger the reel, the
  more times a symbol can be eliminated, up to **4 eliminations** without
  vanishing (symbol durability).
* Free games accumulate an **unlimited multiplier** (no cap).

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `EM` | Emperor | Regular | Top premium |
| `GD` | Gold Bar | Regular | Premium |
| `CT` | Coin | Regular | Mid |
| `LT` | Lantern | Regular | Mid |
| `PT` | Pot | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |

## 3. Paytable

Engine-authored units for a megaways game. `unitDivisor = 1`; the evaluator
applies the per-reel match product.

| Symbol | 3 | 4 | 5 | 6 |
| --- | --- | --- | --- | --- |
| EM | 40 | 120 | 400 | 1000 |
| GD | 25 | 80 | 250 | 600 |
| CT | 15 | 50 | 150 | 350 |
| LT | 10 | 30 | 90 | 200 |
| PT | 6 | 20 | 60 | 130 |
| W | 60 | 200 | 600 | 1500 |
| S (by count) | 3 → 50, 4 → 200, 5 → 1000, 6 → 5000 | | | |

## 4. Feature composition

1. `WaysEvaluator` (recomputes ways from the grid shape).
2. `CascadeFeature` (max 20).
3. `ExpandingReelsFeature` — a symbol eliminated from a reel expands that reel's
   height for the remainder of the spin.
4. `SymbolDurabilityFeature` — a premium symbol survives up to 4 eliminations.
5. `PersistentMultiplierFeature` — during free games the multiplier accumulates
   with no cap.
6. `FreeSpinsFeature` — `[3 => 10]`, retrigger, cap 100.

## 5. Configuration

`GoldenEmpireConfiguration` extends `SlotConfiguration` with:

* `base_heights` (default `[3,3,3,3,3,3]`)
* `max_height` (default `7`)
* `max_eliminations_per_symbol` (default `4`)
* `unlimited_free_spin_multiplier` (default `true`)

Unknown keys are rejected.

## 6. State

`SlotState` plus an `expanding_reels` bucket holding per-reel heights and a
`durability` bucket holding per-cell remaining eliminations.

## 7. Tests

* The ways product is recomputed when a reel expands.
* An elimination expands the reel it came from, up to the configured maximum.
* A durable symbol survives the configured number of eliminations.
* The free-spin multiplier accumulates without a cap.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* The provider's exact 32,400-ways derivation is not published; the module
  computes ways from the grid shape, which is the documented mechanic.
* slotcatalog's `Golden-Empire` slug is a different game (Iconic Gaming) — a
  name collision recorded in `RESEARCH_NOTES.md` C5. No slotcatalog data was used.
* Reel weights, expansion rules and paytable are independent implementations.
