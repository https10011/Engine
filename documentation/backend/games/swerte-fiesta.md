# Swerte Fiesta — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.swerte-fiesta` |
| Provider (researched) | Unverified title — documented Philippine-fiesta approximation |
| Category | Slot |
| Grid | 5 reels × 3 rows |
| Win model | 243 ways |
| Volatility | Medium |
| Max win (published) | 5,000× (configuration) |
| RTP | 96.5% (configuration) |
| Verification | **[U]** unverified title — see `RESEARCH_NOTES.md` §3.13 |

## 1. Researched mechanics

No reliable source describes a game titled exactly *Swerte Fiesta*.
("Swerte" is Tagalog for "luck", so the title is plausibly a Philippine-market
slot.) The module is a **clearly labelled configurable approximation** using the
most common 5×3 ways structure of that market segment:

* **243 ways** on a 5×3 grid.
* **Wild** substitutes; **scatter** pays anywhere and triggers free spins.
* **Free spins** with a doubling multiplier, retriggerable.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `CR` | Crown | Regular | Top premium |
| `DI` | Diamond | Regular | Premium |
| `FL` | Flower | Regular | Mid |
| `GR` | Guitar | Regular | Mid |
| `MA` | Mango | Regular | Low |
| `CO` | Coconut | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |

## 3. Paytable

Engine-authored units for a 243-ways game. `unitDivisor = 1`.

| Symbol | 3 | 4 | 5 |
| --- | --- | --- | --- |
| CR | 30 | 120 | 500 |
| DI | 20 | 80 | 300 |
| FL | 12 | 50 | 180 |
| GR | 8 | 30 | 120 |
| MA | 5 | 20 | 80 |
| CO | 3 | 12 | 50 |
| W | 50 | 200 | 800 |
| S (by count) | 3 → 50, 4 → 200, 5 → 800 | | |

## 4. Feature composition

1. `WaysEvaluator` + `ScatterEvaluator` via `CompositeEvaluator`.
2. `ComboMultiplierFeature` — `[1,1,2,2,3]` base ladder, `[2,2,4,4,6]` in
   free spins.
3. `FreeSpinsFeature` — `[3 => 10, 4 => 15, 5 => 20]`, retrigger, cap 100.

## 5. Configuration

`SwerteFiestaConfiguration` extends `SlotConfiguration` with:

* `free_spin_multiplier_ladder` (default `[2,2,4,4,6]`)

Unknown keys are rejected.

## 6. State

`SlotState`.

## 7. Tests

* 243 ways on a pinned grid equals the hand-computed units.
* Free-spin trigger tiers and retrigger.
* The free-spin multiplier ladder is applied.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] The unverified status of the title is stated in the module metadata and here.

## 9. Limitations

* **This title is unverified.** The implementation is an explicit, documented
  approximation, not a recreation.
* The module metadata marks the provider as
  `Engine Approximation (unverified title)`.
