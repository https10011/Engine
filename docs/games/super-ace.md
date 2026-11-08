# Super Ace — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.super-ace` |
| Provider (researched) | JILI Games, catalogue id 49 |
| Category | Slot |
| Grid | 5 reels × 4 rows |
| Win model | 1,024 ways |
| Volatility | Low–Medium |
| Max win (published) | 10,000× |
| RTP | 97.0% (configuration; provider-published, market-dependent) |
| Verification | **[V]** provider page + **[O]** reference databases — see `RESEARCH_NOTES.md` §3.1 |

## 1. Researched mechanics

* **1,024 ways** — a win is a run of the same symbol from reel 0 where every
  matching cell on each reel counts; units × ways.
* **Cascading reels** — winning cells are removed and the columns refill from
  the reel strip, then the grid is re-evaluated.
* **Combo multiplier trail** — x1, x2, x3, x5 in the base game, resetting at the
  end of the spin; during free spins the trail is doubled to x2, x4, x6, x10.
* **Gold Card → Joker Wild** — a Gold Card symbol becomes a Joker Wild when it
  is eliminated by a cascade.
* **Big Joker** — a wild that replicates 1–4 additional wilds onto reels 2–5.
  **Little Joker** — a wild that only occupies the position the Gold Card left.
* **Free spins** — 3 scatters award 10 free games, retriggerable (+5 each time).

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `A` | Ace (gold card) | Regular | Top premium; becomes a Joker Wild when eliminated |
| `K` | King | Regular | Premium |
| `Q` | Queen | Regular | Mid |
| `J` | Jack | Regular | Mid |
| `T` | Ten | Regular | Low |
| `N` | Nine | Regular | Low |
| `JW` | Joker Wild | Wild | Substitutes for regulars; pays its own run of 3+ |
| `BJ` | Big Joker | Wild | Substitutes; replicates 1–4 wilds on elimination |
| `S` | Scatter | Scatter | Pays anywhere; 3+ triggers free spins |

## 3. Paytable (units, per way)

Values are engine-authored units chosen to produce a mid-to-low volatility ways
game; they are not the provider's proprietary table.

| Symbol | 3 | 4 | 5 |
| --- | --- | --- | --- |
| A (gold card) | 25 | 100 | 400 |
| K | 15 | 60 | 250 |
| Q | 10 | 40 | 150 |
| J | 6 | 25 | 100 |
| T | 4 | 15 | 60 |
| N | 3 | 10 | 40 |
| JW / BJ | 40 | 150 | 600 |
| S (scatter, by count) | 3 → 40, 4 → 100, 5 → 400 | | |

`unitDivisor = 1`, so one unit is one times the total bet divided by the way
count already applied by the evaluator.

## 4. Feature composition

1. `WaysEvaluator` + `ScatterEvaluator` via `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `ComboMultiplierFeature` with ladder `[1,2,3,5]` base and `[2,4,6,10]` in
   free spins (the ladder is selected per phase).
4. `SymbolTransformationFeature` — Gold Card → Joker Wild on elimination.
5. `WildReplicationFeature` — Big Joker spawns 1–4 wilds on reels 2–5.
6. `FreeSpinsFeature` — awards `[3 => 10, 4 => 12, 5 => 15]`, retrigger +5,
   cap 100.

## 5. Configuration

`SuperAceConfiguration` extends `SlotConfiguration` with:

* `wild_replication_min` / `wild_replication_max` (default 1 / 4)
* `free_spin_multiplier_ladder` (default `[2,4,6,10]`)
* `gold_card_symbol` (default `A`)

Unknown keys are rejected.

## 6. State

Uses `SlotState` plus a `transformation` feature bucket holding the set of cells
that will become wild on the next cascade step.

## 7. Tests

* 1,024 ways evaluated on a pinned grid equals the hand-computed units.
* A cascade removes winning cells and refills from the strip (distribution
  preserved, no uniform random refill).
* Gold Card → Joker Wild transformation on elimination.
* Big Joker replication produces 1–4 wilds on reels 2–5.
* Free-spin trigger at 3 scatters; retrigger adds 5; cap respected.
* Combo ladder is `[1,2,3,5]` in base and `[2,4,6,10]` in free spins.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* Reel strips, weights and paytable are independent implementations, not the
  provider's proprietary mathematics.
* RTP is a configuration value, never a claim of equivalence.
* The provider's 10,000× max win conflicts with a reference database's 1,500×;
  the module uses the provider figure as the published maximum (see
  `RESEARCH_NOTES.md` C1).
