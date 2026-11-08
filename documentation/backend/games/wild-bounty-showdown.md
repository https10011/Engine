# Wild Bounty Showdown — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.wild-bounty-showdown` |
| Provider (researched) | PG Soft |
| Category | Slot |
| Grid | 6 reels, heights 3-4-5-5-4-3 (ragged) |
| Win model | 3,000 ways ('bet ways' product) |
| Volatility | High |
| Max win (published) | 5,000× |
| RTP | 96.75% (configuration; provider-published) |
| Verification | **[O]** slotcatalog — see `RESEARCH_NOTES.md` §3.10 |

## 1. Researched mechanics

* **Bet Ways**: the number of matching symbols on each adjacent reel is
  multiplied together, then multiplied by the paytable value.
* **Golden Frame** symbols appear on **reels 3 and 4**; a gold-framed symbol
  involved in a win turns **wild on the next cascade** (a deferred
  transformation).
* Cascading wins with a **doubling multiplier per cascade**, up to **x1,024**.
* **3 scatters → 10 free spins**, +2 per extra scatter; the win multiplier
  **starts at x8** per free spin and doubles per cascade up to x1,024;
  retriggerable.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `SH` | Sheriff | Regular | Top premium |
| `OU` | Outlaw | Regular | Premium |
| `HR` | Horse | Regular | Mid |
| `BT` | Boot | Regular | Mid |
| `RE` | Revolver | Regular | Low |
| `WH` | Whiskey | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |

## 3. Paytable

Engine-authored units for a ways game. `unitDivisor = 1`; the evaluator
applies the way count.

| Symbol | 3 | 4 | 5 | 6 |
| --- | --- | --- | --- | --- |
| SH | 50 | 150 | 500 | 1500 |
| OU | 30 | 100 | 300 | 800 |
| HR | 20 | 60 | 180 | 450 |
| BT | 12 | 40 | 120 | 300 |
| RE | 8 | 25 | 75 | 180 |
| WH | 5 | 15 | 50 | 120 |
| W | 75 | 250 | 800 | 2500 |
| S (by count) | 3 → 60, 4 → 200, 5 → 800, 6 → 3000 | | | |

## 4. Feature composition

1. `WaysEvaluator` (ragged grid supported natively) + `ScatterEvaluator`.
2. `CascadeFeature` (max 20).
3. `SymbolTransformationFeature` — gold-framed winner → Wild on the *next*
   cascade step (deferred, so the frame pays on the cascade after the win).
4. `ComboMultiplierFeature` — doubling ladder `[1,2,4,8,16,32,64,128,256,512,1024]`.
5. `FreeSpinsFeature` — `[3 => 10]`, +2 per extra scatter, cap 100, with a
   per-phase starting multiplier of 8.

## 5. Configuration

`WildBountyConfiguration` extends `SlotConfiguration` with:

* `frame_reels` (default `[2,3]` — reels 3 and 4, zero-indexed)
* `cascade_multiplier_cap` (default 1024)
* `free_spin_start_multiplier` (default 8)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `golden_frame` bucket carrying the cells that become wild on
the next cascade step.

## 7. Tests

* The bet-ways product on a pinned ragged grid equals the hand-computed ways.
* A gold-framed winner becomes wild on the *following* cascade, not the same one.
* The cascade multiplier doubles and saturates at 1,024.
* Free spins start at x8 and retrigger.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* Exact paytable bands, frame frequency and reel weights are not public and
  are independent implementations.
* The 3,000-ways figure is derived from the ragged 3-4-5-5-4-3 layout as
  documented; the provider's exact derivation is not published.
