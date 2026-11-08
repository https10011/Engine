# Fortune Garuda 1000 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.fortune-garuda-1000` |
| Provider (researched) | JILI Games — documented 1000× variant of Fortune Garuda 500 (id 696) |
| Category | Slot |
| Grid | 3 reels × 3 rows + side reels |
| Win model | 5 paylines + side multiplier reels |
| Volatility | Low–Medium |
| Max win (published) | 25,000× |
| RTP | 97.1% (configuration) |
| Verification | **[V]** provider page for Fortune Garuda 500 — see `RESEARCH_NOTES.md` §3.8 |

## 1. Researched mechanics

No title called exactly *Fortune Garuda 1000* was found at JILI, Pragmatic,
PG Soft or in the slotcatalog index. The closest verified title is **Fortune
Garuda 500** (id 696). This module is therefore a **documented 1000× variant**
of that verified mechanic set:

* **3×3 reels with 5 win lines**.
* **Garuda boosts NUDGE and multipliers, up to 500×** — a nudge mechanic shifts
  a symbol into a paying position and can re-trigger evaluation.
* **Special side reels trigger high-multiplier hits, up to 500×** (the 1000
  variant doubles the side-reel ceiling to 1,000×).
* Max win 25,000×.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `GA` | Garuda | Regular | Top premium |
| `EM` | Emerald | Regular | Premium |
| `CO` | Coin | Regular | Mid |
| `TR` | Temple | Regular | Mid |
| `FR` | Fruit | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere |

## 3. Paytable

Engine-authored units. `unitDivisor = 5` (5 paylines).

| Symbol | 3 |
| --- | --- |
| GA | 400 |
| EM | 200 |
| CO | 100 |
| TR | 60 |
| FR | 30 |
| W | 1000 |
| S (by count) | 3 → 60 |

## 4. Feature composition

1. `PaylineEvaluator` (5 lines) + `ScatterEvaluator` via `CompositeEvaluator`.
2. `AuxiliaryReelEvaluator` — a side multiplier reel (1×, 2×, 3×, 5×, 10×, 25×,
   50×, 100×, 250×, 500×, 1000×) multiplies the line win when it aligns.
3. `NudgeFeature` — a configured symbol may nudge into a paying position and
   ask for one more evaluation.

## 5. Configuration

`FortuneGaruda1000Configuration` extends `SlotConfiguration` with:

* `side_reel_multipliers` (default `[1,2,3,5,10,25,50,100,250,500,1000]`)
* `nudge_symbol` (default `W`)
* `nudge_probability` (default 0.08)
* `nudge_max_steps` (default 2)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `nudge` bucket carrying the pending nudge.

## 7. Tests

* A 3-symbol line on each of the 5 paylines pays the hand-computed units.
* A side-reel multiplier multiplies the line win; the ceiling is 1,000×.
* A nudge shifts the symbol and re-evaluates without paying a line twice.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] The naming discrepancy is stated in the module metadata and here.

## 9. Limitations

* **The title is not verifiable as named.** The implementation is a documented
  1000× variant of the verified *Fortune Garuda 500* mechanic set.
* The module metadata names the provider
  `JILI Games (Fortune Garuda 500 variant, documented approximation)`.
* The provider attribute field says 1 payline while the description says 5 win
  lines; the description is treated as gameplay (`RESEARCH_NOTES.md` C3).
* Nudge rules and side-reel distribution are not published; configurable
  approximations are used.
