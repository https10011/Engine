# Super Ace Deluxe — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.super-ace-deluxe` |
| Provider (researched) | JILI Games, catalogue id 403 |
| Category | Slot |
| Grid | 5 reels × 4 rows |
| Win model | 1,024 ways |
| Volatility | Medium |
| Max win (published) | 10,000× |
| RTP | 97.0% (configuration) |
| Verification | **[V]** provider page — see `RESEARCH_NOTES.md` §3.2 |

## 1. Researched mechanics

* **5×4, 1,024 ways**.
* **Combo multiplier trail** `x1, x2, x3, x5, x10` in the base game;
  `x2, x4, x6, x10, x20` in free spins — an extended trail versus Super Ace.
* **Star Card** may activate on the 2nd–4th cascade and is **guaranteed from the
  5th**; it converts symbols on reels 2–4 into Golden Symbols.
* **Golden Symbols → Joker Wilds** when eliminated; **Big Joker** replicates
  1–4 wilds.
* **3 scatters award 10 free games**.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `A` | Ace (golden) | Regular | Top premium; becomes Joker Wild when eliminated |
| `K` | King | Regular | Premium |
| `Q` | Queen | Regular | Mid |
| `J` | Jack | Regular | Mid |
| `T` | Ten | Regular | Low |
| `N` | Nine | Regular | Low |
| `ST` | Star Card | Special | Converts reels 2–4 symbols to Golden |
| `JW` | Joker Wild | Wild | Substitutes; pays its own run |
| `BJ` | Big Joker | Wild | Replicates 1–4 wilds |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |

## 3. Paytable

Engine-authored units for a ways game. `unitDivisor = 1`.

| Symbol | 3 | 4 | 5 |
| --- | --- | --- | --- |
| A | 30 | 120 | 500 |
| K | 18 | 70 | 300 |
| Q | 12 | 45 | 180 |
| J | 7 | 28 | 110 |
| T | 5 | 18 | 70 |
| N | 3 | 12 | 45 |
| JW / BJ | 45 | 180 | 750 |
| S (by count) | 3 → 40, 4 → 120, 5 → 500 | | |

## 4. Feature composition

1. `WaysEvaluator` + `ScatterEvaluator` via `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `ComboMultiplierFeature` — `[1,2,3,5,10]` base, `[2,4,6,10,20]` free spins.
4. `StarCardFeature` — activates on cascade 2–4 with probability, guaranteed
   from cascade 5; converts reels 2–4 to Golden.
5. `SymbolTransformationFeature` — Golden → Joker Wild on elimination.
6. `WildReplicationFeature` — Big Joker spawns 1–4 wilds.
7. `FreeSpinsFeature` — `[3 => 10]`, retrigger, cap 100.

## 5. Configuration

`SuperAceDeluxeConfiguration` extends `SlotConfiguration` with:

* `star_card_min_cascade` (default 2)
* `star_card_guaranteed_cascade` (default 5)
* `star_card_probability` (default 0.25)
* `free_spin_multiplier_ladder` (default `[2,4,6,10,20]`)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `star_card` bucket and a `transformation` bucket.

## 7. Tests

* 1,024 ways on a pinned grid equals the hand-computed units.
* The Star Card is probabilistic before cascade 5 and guaranteed at cascade 5.
* Golden → Joker Wild on elimination; Big Joker replication 1–4.
* Base ladder `[1,2,3,5,10]`; free-spin ladder `[2,4,6,10,20]`.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* Exact Star Card trigger probabilities are not published; the module uses a
  documented, configurable approximation.
* Reel strips, paytable and RTP are independent implementations.
