# Game Interface Implementation

How every registered module gets a working interface, and why there are four interfaces
rather than 22.

## 1. Dispatch model

`/tables/{id}` (and `/slots/{id}` when the presentation says the game is not a slot)
runs `views/table.js`. It reads `GET /api/v1/games/{id}/presentation` once and routes by
the module, not by a hard-coded list:

| Condition | Interface |
| --- | --- |
| `game.id === 'coinflip.heads-tails'` or tags include `example` | `views/coinflip.js` |
| `category === 'card'` | `views/blackjack.js` |
| `category === 'roulette'` | `views/roulette.js` |
| `category === 'slot'` | `views/slot.js` (via `/slots/{id}`) |
| anything else | explicit "no interface yet" error, never a fake screen |

Coin Flip is checked before the category because it is registered as a `card` game but
is not blackjack: it has one action, a fixed "heads" call and a 2× payout.

## 2. Slot interface (17 games)

`views/slot.js` renders one metadata-driven cabinet:

* **Layout** — reel count and per-reel row counts from `presentation.layout`, so
  3-4-5-4-3 ways games and 5×3 payline games both render correctly.
* **Symbols** — from `presentation.slot.symbols`; a glyph map covers common ids, unknown
  ids fall back to their initials. Wild/scatter/bonus/blank classes come from module data.
* **Bet ladder** — starts at `game.min_bet.amount`, steps by `game.bet_step.amount`, stops
  at `max_bet.amount`; the stepper cannot offer a value the engine would reject.
* **Spin** — creates a session once, then posts
  `POST /sessions/{id}/actions { action: "spin", bet }` with a UUID idempotency key.
  The returned `round.result.data.grid` is drawn; winning positions get `.cell--win`,
  other cells dim. `round.payout`, `round.net` and `round.balance_after` drive the
  readout and header balance.
* **Free spins / cascades / features** — read from the round (`free_spins_remaining`,
  `free_spin`) and the module's feature list in the side panel. The UI does not simulate
  a feature the engine did not return.
* **Side panel** — Paytable (symbol pays from the module), Rules (volatility, max win,
  RTP, feature list), Session (the rounds settled in this tab).
* **Idle state** — a deterministic face built from the game's own symbols, labelled
  "Press spin to play"; it is not a stored or predicted result.
* **Sound** — synthesised WebAudio spin/stop/win cues, toggle persisted.

All 17 slot ids (`jili.*`, `pgsoft.*`, `pragmatic.*`, `slots.*`, `synthetic.slot3x3`)
use this view. Their differences are data: theme colours, symbols, reels, rows,
paytable, features and bet limits.

## 3. Blackjack (2 modules)

`views/blackjack.js`:

* Deal posts `{ action: "deal", bet }`; the response state contains dealer/player cards,
  phase, stakes and results.
* Action buttons are built from the phase: Deal/Repeat bet when idle, Hit/Stand/Double/
  Split/Surrender while playing, Take insurance/Decline on insurance, Next hand when a
  hand is complete.
* Cards render as `.card` with rank/suit; the dealer's hole card is `.card--hidden`
  until the engine exposes it. Hand totals are displayed from the server state.
* Payout, result text and balance come from the round; no client-side scoring decides an
  outcome.
* Both `table.blackjack` and `synthetic.blackjack` share the view.

## 4. Roulette (2 modules)

`views/roulette.js`:

* The wheel pocket order is a framework constant; the view builds one `.wheel__pocket`
  per number with `--angle` written through `style.setProperty` (a real CSS custom
  property), so the ring is correct for European single-zero.
* Betting: straight numbers, red/black, odd/even, low/high, dozens and columns. Chips
  are 1/5/10/50; left-click adds, right-click or shift-click removes. Stakes are capped
  by the module's straight/outside/round maxima.
* Spin posts `place_bets` with the slip, then `spin` with the total; both use idempotency
  keys. The winning number is the server's, animated by rotating the wheel 4 turns plus
  the pocket offset (2.4s), then shown in the hub badge.
* Winning cells get `.is-winning`; the history strip records the last 12 numbers; the
  payout and balance come from the round.
* `table.roulette` and `synthetic.roulette` share the view.

## 5. Coin Flip (1 module)

`views/coinflip.js`:

* The module always calls heads and offers one action: `POST .../actions { action:
  "flip", bet }`.
* The coin has distinct heads (crown) and tails (spark) faces. The CSS 3D flip lands on
  the returned side; the outcome text says which side landed and whether the call won.
* Payout (2× stake on heads), session flip count, history list and balance are rendered
  from server data.
* This view exists because the module's action model is not blackjack; treating it as a
  blackjack table would have produced a broken interface.

## 6. Shared rules

* **The server settles every round.** Views post an action and render the returned
  `round`; no symbol, card, pocket or payout is chosen in the browser.
* **Idempotency.** Every action sends `Idempotency-Key: crypto.randomUUID()`; the client
  never retries a POST automatically.
* **Sessions.** Slot/coinflip/roulette sessions are created lazily and closed on view
  teardown (`api.closeSession`, best effort). Blackjack sessions are created on deal.
* **Errors.** A failed action restores the controls, shows the API message in a toast
  and keeps the previous state; there is no optimistic balance change.
* **Sign-in.** Opening a game while signed out opens the login dialog; the lobby remains
  public. The game route itself is not blocked by the router, so a deep link shows the
  game's metadata and prompts for sign-in on the first action.

## 7. Coverage matrix

| Interface | Modules | Actions exercised in verification |
| --- | ---: | --- |
| Slot cabinet | 17 | one real spin (`jili.super-ace`), result + balance checked |
| Blackjack | 2 | one real deal; Hit/Stand/Double rendered from state |
| Roulette | 2 | one straight-up bet + spin; 36.00 payout checked |
| Coin flip | 1 | one real flip; tails face and loss text checked |
| **Total** | **22** | 4 modules settled end-to-end; all 22 open their interface |

The remaining 18 modules were opened and rendered (lobby → tile → view) in the catalogue
and share the exercised code paths. No module has a bespoke fake state.

## 8. Extending

* A new slot needs no frontend change; its presentation drives the cabinet.
* A new blackjack/roulette variant uses the existing view.
* A new *kind* of game needs a view plus one branch in `table.js`; the error branch makes
  the omission visible instead of silently mis-rendering.
* Regenerate tiles with `tools/generate_assets.php` (see
  [ASSET_MAPPING.md](ASSET_MAPPING.md)).
