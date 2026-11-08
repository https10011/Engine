# Fortune Gems 2 — Game Specification

| Field | Value |
| --- | --- |
| Game id | `slots.fortune-gems-2` |
| Provider (researched) | JILI Games, catalogue id 223 |
| Category | Slot |
| Grid | 3 reels × 3 rows + auxiliary multiplier reel |
| Win model | 5 paylines + side multiplier reel |
| Volatility | Medium |
| Max win (published) | 10,000× |
| RTP | 97.0% (configuration) |
| Verification | **[V]** provider page + **[O]** slotcatalog — see `RESEARCH_NOTES.md` §3.4 |

## 1. Researched mechanics

* **3×3 board, 5 lines**.
* **Multiplier reel on the right side** carrying multipliers **1×, 2×, 3×, 5×,
  10×, 15×** and wheel symbols, in conjunction with the payline wins.
* **Scatter triggers the Lucky Wheel** — a bonus round with cash prizes
  1×, 3×, 5×, 8×, 10×, 15×, 20×, 30×, 50×, 100×, 200×.
* A bonus-bet mode upgrades the special reel.

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
| `S` | Scatter | Scatter | Triggers the Lucky Wheel bonus |

## 3. Paytable

Engine-authored units. `unitDivisor = 5` (5 paylines).

| Symbol | 3 |
| --- | --- |
| D7 | 300 |
| BAR | 150 |
| BE | 80 |
| WM | 50 |
| OR | 30 |
| LM | 20 |
| W | 500 |
| S (by count) | 3 → 30 |

## 4. Feature composition

1. `PaylineEvaluator` (5 lines) + `ScatterEvaluator` via `CompositeEvaluator`.
2. `AuxiliaryReelFeature` — a side multiplier reel (1×, 2×, 3×, 5×, 10×, 15×)
   that multiplies the payline win when it aligns.
3. `BonusWheelFeature` — a scatter or wheel symbol starts the Lucky Wheel; the
   wheel picks a cash prize from the configured ladder.

## 5. Configuration

`FortuneGems2Configuration` extends `SlotConfiguration` with:

* `side_reel_multipliers` (default `[1,2,3,5,10,15]`)
* `wheel_prizes` (default `[1,3,5,8,10,15,20,30,50,100,200]`)
* `wheel_trigger_scatters` (default 3)
* `bonus_bet_upgrade` (default `false`)

Unknown keys are rejected.

## 6. State

`SlotState` plus a `bonus_wheel` bucket carrying the pending wheel round.

## 7. Tests

* A 3-symbol line on each of the 5 paylines pays the hand-computed units.
* The side multiplier multiplies the payline win.
* The Lucky Wheel is triggered by 3 scatters and pays a value from the ladder.
* The bonus-bet upgrade changes the side reel as configured.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] Round executes through `RoundService` and moves the wallet correctly.
- [ ] Every mechanic in §1 is implemented and tested.
- [ ] Specification limitations recorded.

## 9. Limitations

* The exact side-reel distribution and wheel prize weighting are not public;
  the module uses documented, configurable values.
* slotcatalog attributes this title to a different studio (TaDa Gaming); JILI's
  own catalogue is treated as authoritative (`RESEARCH_NOTES.md` C2).
* The wheel prize ladder is truncated in the source; the module uses a
  documented, configurable ladder.
