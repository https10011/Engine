# Fortune Gems 3 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.fortune-gems-3` |
| Provider (researched) | JILI Games, catalogue id 300 |
| Category | Slot |
| Grid | 3 reels × 3 rows |
| Win model | 5 paylines |
| Volatility | High–Medium |
| Max win (published) | 10,125× |
| RTP | 97.0% (configuration) |
| Verification | **[V]** provider page — see `RESEARCH_NOTES.md` §3.5 |

## 1. Researched mechanics

* **3×3 board, 5 connections (paylines)**.
* **Every time you win points, you can get a multiplier** — a win multiplier is
  applied to the line win.
* **Symbol split** — a winning symbol can split into multiple paying units,
  multiplying the effective run value.
* Comprehensive rewards on the 5 fixed lines.

## 2. Symbol set

| Id | Name | Type | Behaviour |
| --- | --- | --- | --- |
| `D7` | Lucky Seven | Regular | Top premium |
| `BAR` | Bar | Regular | Premium |
| `BE` | Bell | Regular | Mid |
| `WM` | Watermelon | Regular | Mid |
| `OR` | Orange | Regular | Low |
| `LM` | Lemon | Regular | Low |
| `W` | Wild | Wild | Substitutes; pays its own run |
| `S` | Scatter | Scatter | Pays anywhere |

## 3. Paytable

Engine-authored units. `unitDivisor = 5` (5 paylines), so a line paying 100
units pays 20× the total bet.

| Symbol | 3 |
| --- | --- |
| D7 | 300 |
| BAR | 150 |
| BE | 80 |
| WM | 50 |
| OR | 30 |
| LM | 20 |
| W | 500 |
| S (by count) | 3 → 50 |

## 4. Feature composition

1. `PaylineEvaluator` (5 lines) + `ScatterEvaluator` via `CompositeEvaluator`.
2. `SymbolSplitFeature` — a winning symbol may split into 2–3 paying units,
   multiplying that win.
3. `WinMultiplierFeature` — a win may carry a configured multiplier.
4. No cascade (the researched mechanic set does not include one).

## 5. Configuration

`FortuneGems3Configuration` extends `SlotConfiguration` with:

* `symbol_split_enabled` (default `true`)
* `split_probability` (default 0.15)
* `split_max_units` (default 3)
* `win_multiplier_values` (default `[2,3,5]`)
* `win_multiplier_probability` (default 0.10)

Unknown keys are rejected.

## 6. State

`SlotState` (no feature buckets are required beyond the spin counter).

## 7. Tests

* A 3-symbol line on each of the 5 paylines pays the hand-computed units.
* A split multiplies the win by the number of split units, capped.
* A win multiplier is applied once per winning line.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* Split rules (how many units, when) are not published; the module uses a
  documented, configurable approximation.
* Reel strips, paytable and RTP are independent implementations.
