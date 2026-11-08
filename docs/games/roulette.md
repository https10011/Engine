# Roulette — Game Specification

| Field | Value |
| --- | --- |
| Game id | `table.roulette` |
| Provider (researched) | Standard public rules (Wikipedia *Roulette*) |
| Category | Roulette |
| Grid | N/A — wheel pockets |
| Win model | Bet-vs-winning-pocket settlement |
| Volatility | Low |
| Max win (published) | 36× (a winning straight-up bet) |
| RTP | 97.30% (European), 94.74% (American) — geometry, not configuration |
| Verification | **[V]** Wikipedia *Roulette* — see `RESEARCH_NOTES.md` §5.2 |

## 1. Researched mechanics

* **European** wheel: 37 pockets (0–36), single zero. **American**: 38
  pockets (0, 00, 1–36). **French**: single zero with **La Partage** or
  **En Prison** on even-money bets.
* Colours follow the standard layout (red/black assignment derived from the
  published table; 0 and 00 green).
* Payouts (to 1, plus stake back on a win): straight **35:1**, split **17:1**,
  street **11:1**, corner **8:1**, six-line **5:1**, column/dozen **2:1**,
  even-money **1:1**. American five-number bet pays **6:1**.
* House edge: single-zero 2.70%, double-zero 5.26% for standard bets.
* **La Partage**: on a zero, even-money bets lose only half. **En Prison**: the
  bet is imprisoned; if the next spin would win, the wager is returned.

## 2. Symbol set

Pockets `0` (and `00` on American), `1`–`36`. Bet types: straight, split,
street, corner, six-line, trio, basket, column, dozen, red, black, odd, even,
low, high.

## 3. Paytable

Multipliers returned *including* the stake:

| Bet | Multiplier |
| --- | --- |
| Straight | 36 |
| Split | 18 |
| Street / Trio | 12 |
| Corner | 9 |
| Six line / Basket | 6 / 7 |
| Column / Dozen | 3 |
| Even money | 2 |

## 4. Feature composition

Built on the roulette framework:

1. `RouletteVariant` — European (default), American, French, or custom.
2. `BetFactory` — builds and structurally validates every bet from the wheel.
3. `RouletteEngine::spin()` — uniform pocket draw from the injected RNG.
4. `RouletteSettlement` — per-bet win/lose/push, la partage and en prison.

## 5. Configuration

`RouletteConfiguration` exposes:

* `variant` (default `european`; `american`, `french` selectable)
* `min_bet_minor` / `max_bet_minor` / `max_total_bet_minor`
* `max_bets_per_round` (default 20)
* `allow_inside` / `allow_outside` (default `true`)

Unknown keys are rejected.

## 6. State

`RouletteState` carries the spin counter and the last winning pocket so a
reloaded session renders the same table. Schema-versioned.

## 7. Tests

* Every bet type is built and structurally validated from the wheel; an
  invalid geometry (a non-consecutive street, a non-2×2 corner) is rejected.
* The payout ladder is exact for every bet type.
* European and American wheels produce the correct pocket sets and colours.
* La partage returns half on a zero for even-money bets; en prison returns the
  stake on the French variant.
* The winning pocket is drawn uniformly from the wheel.
* Betting limits are enforced.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] A full round (place bets → spin → settle) executes through
  `RoundService` and moves the wallet correctly.
- [ ] Every bet type and variant is tested.
- [ ] Game history records the round.

## 9. Limitations

* Table limits and chip denominations are configuration.
* The engine implements the standard rules; it does not claim a casino's
  specific table layout or limits.
