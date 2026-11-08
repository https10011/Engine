# Frontend Architecture

The player interface is a small, dependency-free ES-module SPA served from
`public/app.html`. It replaces the previous placeholder pages without changing the
backend, the API contract or the existing views' responsibilities.

## 1. Runtime shape

```
public/index.php                  single HTTP entry point
  ├─ /api/*                       ApiKernel → JSON API (unchanged, one new route)
  └─ everything else              app.html + assets (SPA shell)

public/app.html                   header, #main, footer, tabbar, dialogs
public/assets/css/
  app.css                          tokens, shell, header, buttons, forms, modals, tabbar
  lobby.css                        banner, categories, shelves, tiles, search rows, trust
  games.css                        slot cabinet, controls, coin flip, game-stage tokens
  views.css                        blackjack, roulette wheel/felt, history tables
public/assets/js/
  app.js                           router, auth dialogs, search sheet, shell wiring
  lib/api.js                       fetch wrapper and endpoint map
  lib/store.js                     session/token persistence
  lib/shell.js                     header/balance sync
  lib/dom.js                       element builder and formatters
  lib/icons.js                     inline SVG icon set
  lib/ui.js                        toast, state, theme helpers
  lib/sound.js                     WebAudio cues (no audio files)
  views/lobby.js                   lobby + category routes + search + carousel
  views/slot.js                    all slot games
  views/table.js                   dispatcher for table/card/coin-flip modules
  views/blackjack.js               blackjack table
  views/roulette.js                roulette wheel and felt
  views/coinflip.js                coin flip
  views/history.js                 rounds + wallet ledger
  views/admin.js                   admin probability panel (existing endpoints)
```

There is no build step, no bundler and no browser dependency. The shell loads ES
modules with `type="module"`; the CSP allows only same-origin scripts.

## 2. Routing

`app.js` parses `location.pathname` and dispatches to a view:

| Path | View | Notes |
| --- | --- | --- |
| `/` | `renderLobby` | full catalogue, carousel, shelves, search |
| `/slots` | `renderLobby({lockedCategory:'slot'})` | category route |
| `/tables` | `renderLobby({lockedCategory:'card'})` | category route |
| `/roulette` | `renderLobby({lockedCategory:'roulette'})` | category route |
| `/slots/:id` | `renderSlot` | slot cabinet |
| `/tables/:id` | `renderTable` → slot / blackjack / roulette / coin flip | dispatch by id/tag then category |
| `/history` | `renderHistory` | requires a token; shows the sign-in prompt otherwise |
| `/admin` | `renderAdmin` | requires an authenticated administrator |
| anything else | not-found state | |

Navigation uses `history.pushState`; `popstate` re-renders. Every render increments a
token, so a slow response from a previous route is discarded instead of overwriting the
new one (`context.isStale()`). Views may return `{ node, destroy() }`; `destroy` runs
before the next view mounts and clears timers/sessions (the carousel and slot session
use it).

Route changes also:

* set `document.title` and `aria-current` on header/tabbar links;
* set `body[data-view]`, which switches game pages to the dark stage theme;
* render a skeleton while the view loads;
* guard `/history` and `/admin` in the UI while the API still enforces authorisation.

## 3. Data flow

```
view → api.js → /api/v1/... → engine → JSON envelope {ok,data,error,meta}
                              ↓
                 view renders textContent / DOM nodes
```

* `api.js` attaches the bearer token, sends `credentials: 'same-origin'`, adds an
  `Idempotency-Key` for actions, and throws `ApiError` for a failed envelope. It clears
  the session on a 401.
* The lobby is public (`auth: false`), so the floor renders before login.
* Presentation payloads (`GET /games/{id}/presentation`) drive reels, rows, symbols,
  paytable, rules and theme colours. A view never invents a symbol set or payout.
* Actions (`POST /sessions/{id}/actions`) return a settled round; the UI animates the
  server's grid/cards/number and shows `round.payout` and `round.balance_after`.
* If `balance_after` is present the shell updates from it; otherwise it calls
  `syncBalance()` (`GET /auth/me`).

### Money

The API uses `{amount, currency, scale, formatted}` for wallets and rounds.
`money(amount, scale, currency)` in `lib/dom.js` formats minor units; no floats are used
for arithmetic. The history endpoints instead return flat minor-unit integers
(`bet`, `payout`, `net`, `amount_minor`, `balance_after_minor`); `views/history.js`
normalises both shapes.

## 4. Session and shell

`lib/store.js` persists:

* token, user and balance in `sessionStorage` by default;
* the same in `localStorage` when "Keep me signed in" is checked;
* the recent-games list (`aurum.recent`) for the lobby shelf;
* logout clears both stores and calls `POST /auth/logout` (server-side revocation).

`lib/shell.js` keeps the header balance chip and auth buttons in sync with the store and
exposes `syncBalance()` / `flashBalance()` for game views.

## 5. Dialogs and global UI

`app.html` contains native `<dialog>` elements for login, registration, forgot-password,
account, search and the menu drawer. `app.js`:

* opens/closes them from `data-action` buttons;
* validates login/registration and calls the API;
* renders the search sheet from the cached catalogue (`ensureCatalogue()`);
* closes on backdrop click and Escape (native dialog behaviour), and returns focus to the
  opener.

The search sheet also appears on mobile from the central tabbar FAB. On desktop, the
lobby has an inline search field; the sheet remains available from the drawer.

## 6. Backend change

One additive endpoint was required for a working registration dialog:

```
POST /api/v1/auth/register
{ "username": "...", "password": "...", "display_name": "..." }
→ 201 { user, token, expires_at, balance }
```

It reuses `UserRepository`, `WalletRepository`, `AuthService::hashPassword()` and
`issueToken()` in a transaction, and is gated by `security.allow_self_registration`.
No existing route, controller, service, game registration or test was removed or
changed. See `API_SPECIFICATION.md` §2 and `tests/Api/ApiTest.php`.

## 7. Serving

* `php bin/console serve --host=0.0.0.0 --port=3000` (PHP built-in server) serves
  `public/index.php`; assets are handed back to the server by the `cli-server` guard.
* Apache/shared hosting uses `public/.htaccess` to route non-files to `index.php`; the
  shell response carries CSP, `nosniff`, `Referrer-Policy` and `X-Frame-Options: DENY`.
* No Node process or build artifact is required in production.

## 8. Design boundaries

* Backend and game math are untouched (except registration).
* Views render API data; they do not calculate outcomes, payouts or RTP.
* The frontend keeps one implementation per *interface type*, not one per game: 17 slots
  share the cabinet, 18 slot + blackjack + roulette modules reuse their table views, and
  only Coin Flip needed a separate view because its action model (`flip`, fixed call)
  differs from blackjack.
