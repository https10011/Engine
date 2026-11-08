# Super Ace 2 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.super-ace-2` |
| Provider (researched) | JILI Games, catalogue id 542 |
| Category | Slot |
| Grid | 5 reels × 4 rows |
| Win model | 1,024 ways |
| Volatility | Medium |
| Max win (published) | 10,000× |
| RTP | 97.0% (configuration) |
| Verification | **[V]** provider page — see `RESEARCH_NOTES.md` §3.3 |

## 1. Researched mechanics

* **5×4, 1,024 ways to win**.
* **Stacked Golden Joker** — enables consecutive wins: a stacked multi-cell wild
  unit that persists through the cascade chain.
* **Free game multipliers double**.
* Cascading reels with a combo trail, as in the Super Ace family.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `A` | Ace | Regular | Top premium; becomes Joker Wild when eliminated |
| `K` | King | Regular | Premium |
| `Q` | Queen | Regular | Mid |
| `J` | Jack | Regular | Mid |
| `T` | Ten | Regular | Low |
| `N` | Nine | Regular | Low |
| `JW` | Joker Wild | Wild | Substitutes; pays its own run |
| `SJ` | Stacked Golden Joker | Wild | Multi-cell wild unit |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |

## 3. Paytable

Engine-authored units for a ways game. `unitDivisor = 1`.

| Symbol | 3 | 4 | 5 |
| --- | --- | --- | --- |
| A | 28 | 110 | 450 |
| K | 16 | 65 | 260 |
| Q | 11 | 40 | 160 |
| J | 7 | 26 | 100 |
| T | 4 | 16 | 60 |
| N | 3 | 11 | 42 |
| JW / SJ | 42 | 170 | 700 |
| S (by count) | 3 → 40, 4 → 120, 5 → 500 | | |

## 4. Feature composition

1. `WaysEvaluator` + `ScatterEvaluator` via `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `ComboMultiplierFeature` — `[1,2,3,5]` base, doubled to `[2,4,6,10]` in free
   spins.
4. `StackedWildFeature` — a Stacked Golden Joker occupies a vertical run of
   cells and behaves as a wild unit across cascade steps.
5. `SymbolTransformationFeature` — Ace → Joker Wild on elimination.
6. `FreeSpinsFeature` — `[3 => 10]`, retrigger, cap 100.

## 5. Configuration

`SuperAce2Configuration` extends `SlotConfiguration` with:

* `stacked_wild_symbol` (default `SJ`)
* `stacked_wild_min_height` (default 2)
* `stacked_wild_max_height` (default 3)
* `free_spin_multiplier_ladder` (default `[2,4,6,10]`)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `stacked_wild` bucket carrying the active stack and a
`transformation` bucket.

## 7. Tests

* 1,024 ways on a pinned grid equals the hand-computed units.
* A Stacked Golden Joker occupies its run of cells and substitutes on each.
* The stack enables a consecutive win across a cascade step.
* The free-spin ladder is doubled.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* The detailed stacked-joker mechanics are not published; the module uses a
  documented, configurable approximation.
* A reviewer attributes a different "Super Ace 2" to another studio; only
  JILI's catalogue entry is treated as verified (`RESEARCH_NOTES.md` C6).
