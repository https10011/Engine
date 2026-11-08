# Piñata Wins — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.pinata-wins` |
| Provider (researched) | PG Soft |
| Category | Slot |
| Grid | 5 reels × 3 rows |
| Win model | 20 fixed paylines, left-to-right |
| Volatility | Medium |
| Max win (published) | 5,000× |
| RTP | 96.75% (configuration; provider-published, market-dependent) |
| Verification | **[O]** Bigwinboard review — see `RESEARCH_NOTES.md` §3.9 |

## 1. Researched mechanics

* **20 fixed paylines**, wins left-to-right on consecutive reels.
* **Cascading reels** — winning symbols are removed and refilled from the strip.
* **Golden frames** on the three middle reels; a framed symbol becomes a **Wild**
  when it participates in a win.
* Some framed symbols carry multipliers **x2–x100**; collected multipliers apply
  to the accumulated win of the current cascade sequence.
* **3 scatters → 15 free spins**, +2 per extra scatter; during free spins the
  collected multipliers feed a **persistent global multiplier meter** that
  survives between spins of the feature.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `SK` | Skull | Regular | Top premium |
| `SO` | Sombrero | Regular | Premium |
| `MA` | Maracas | Regular | Mid |
| `TA` | Taco | Regular | Mid |
| `CH` | Chilli | Regular | Low |
| `PP` | Pepper | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |

## 3. Paytable

Engine-authored units. `unitDivisor = 20` (20 paylines), so a line paying
100 units pays 5× the total bet.

| Symbol | 3 | 4 | 5 |
| --- | --- | --- | --- |
| SK | 60 | 250 | 1000 |
| SO | 40 | 150 | 500 |
| MA | 25 | 80 | 250 |
| TA | 15 | 50 | 150 |
| CH | 10 | 30 | 100 |
| PP | 6 | 20 | 60 |
| W | 100 | 400 | 1500 |
| S (by count) | 3 → 50, 4 → 150, 5 → 500 | | |

## 4. Feature composition

1. `PaylineEvaluator` (20 lines) + `ScatterEvaluator` via `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `SymbolTransformationFeature` — framed symbol → Wild on participation in a win.
4. `FramedMultiplierFeature` — collects x2–x100 from framed winning symbols into
   the cascade sequence accumulator.
5. `PersistentMultiplierFeature` — during free spins the accumulator survives
   between spins.
6. `FreeSpinsFeature` — `[3 => 15]`, +2 per extra scatter, cap 100.

## 5. Configuration

`PinataWinsConfiguration` extends `SlotConfiguration` with:

* `frame_reels` (default `[1,2,3]` — the three middle reels)
* `frame_chance` (default 0.30 — probability a landed cell carries a frame)
* `frame_multipliers` (default `[2,3,5,10,25,50,100]`)
* `persistent_multiplier_in_free_spins` (default `true`)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `framed_multiplier` bucket holding the collected multiplier
and, during free spins, the persistent meter value.

## 7. Tests

* 20 paylines evaluated on a pinned grid equals the hand-computed units.
* A framed symbol that wins becomes a Wild on the next cascade.
* Collected frame multipliers apply to the sequence's accumulated win.
* The persistent meter survives between free spins and resets after the feature.
* Free-spin trigger at 3 scatters; extra scatters add 2 spins each.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* Frame frequency, multiplier weighting, reel strips and paytable are
  independent implementations, not the provider's mathematics.
* RTP is configuration, never a claim of equivalence.
