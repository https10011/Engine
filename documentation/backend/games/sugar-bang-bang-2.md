# Sugar Bang Bang 2 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.sugar-bang-bang-2` |
| Provider (researched) | Unverified title — documented sweet-cluster approximation |
| Category | Slot |
| Grid | 6 reels × 5 rows |
| Win model | Cluster pays (8+ adjacent) |
| Volatility | High |
| Max win (published) | 10,000× (configuration) |
| RTP | 96.50% (configuration, typical for the family) |
| Verification | **[U]** unverified title; modelled on the verified sugar-cluster family (Sweet Bonanza / Sugar Rush mechanics) — see `RESEARCH_NOTES.md` §3.12 |

## 1. Researched mechanics

No reliable source describes a game titled exactly *Sugar Bang Bang 2*. The
module is a **clearly labelled configurable approximation** of the
sweet-cluster cascade family:

* **Cluster pays**: 8 or more orthogonally adjacent identical symbols.
* **Tumble** cascades: winning clusters explode and are replaced from the strip.
* **Candy bomb multipliers** land with random values up to 1,000×; multipliers
  remain on screen during tumbles, are **added together**, and applied to the
  total win of the sequence.
* **4 lollipops → 10 free spins**; 3 or more lollipops during the bonus add 5.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `CH` | Cherry | Regular | Top premium |
| `GR` | Grape | Regular | Premium |
| `AP` | Apple | Regular | Mid |
| `BN` | Banana | Regular | Mid |
| `PL` | Plum | Regular | Low |
| `WM` | Watermelon | Regular | Low |
| `CB` | Candy Bomb | Multiplier | Random multiplier up to 1,000× |
| `LP` | Lollipop | Scatter | Triggers free spins |

## 3. Paytable

Engine-authored units for a cluster game. `unitDivisor = 1`.

| Symbol | 8–9 | 10–11 | 12–14 | 15+ |
| --- | --- | --- | --- | --- |
| CH | 50 | 150 | 500 | 2000 |
| GR | 30 | 100 | 300 | 1000 |
| AP | 20 | 60 | 200 | 600 |
| BN | 12 | 40 | 120 | 400 |
| PL | 8 | 25 | 80 | 250 |
| WM | 5 | 15 | 50 | 150 |
| LP (by count) | 4 → 10 spins, 5 → 12, 6 → 15 | | | |

## 4. Feature composition

1. `ClusterEvaluator(8)` + `CompositeEvaluator`.
2. `CascadeFeature` (max 20).
3. `SymbolMultiplierFeature` — candy bombs land with a random value and are
   summed at the end of the tumble sequence.
4. `FreeSpinsFeature` — `[4 => 10, 5 => 12, 6 => 15]`, +5 on a 3+ lollipop
   retrigger, cap 100.

## 5. Configuration

`SugarBangBang2Configuration` extends `SlotConfiguration` with:

* `cluster_minimum` (default 8)
* `multiplier_symbol` (default `CB`)
* `multiplier_values` (default `[2,3,5,10,25,50,100,250,500,1000]`)
* `retrigger_threshold` (default 3)
* `retrigger_spins` (default 5)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `symbol_multiplier` bucket carrying the sequence's summed
multiplier.

## 7. Tests

* A cluster of 7 does not pay; a cluster of 8 does.
* Candy bomb values are summed and applied once to the sequence total.
* Tumble cascade refills from the strip.
* Free-spin trigger at 4 lollipops; retrigger at 3 during the bonus adds 5.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] The unverified status of the title is stated in the module metadata and here.

## 9. Limitations

* **This title is unverified.** The implementation is an explicit, documented
  approximation of the sweet-cluster family, not a recreation.
* The module metadata marks the provider as
  `Engine Approximation (unverified title)`.
* Multiplier frequencies and values are configuration, not the provider's.
