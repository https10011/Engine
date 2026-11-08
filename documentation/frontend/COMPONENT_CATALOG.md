# Component Catalogue

Every reusable interface element in AURUM, its markup, states and source. Class names are
the contract; CSS lives in the file named in the last column.

## 1. Shell

| Component | Markup / classes | States and behaviour | Source |
| --- | --- | --- | --- |
| Header | `.app-header` → `.app-header__inner`, `.brand`, `.app-nav`, `.app-header__account` | sticky; signed-out shows Log in/Register; signed-in shows `.balance-chip`; nav hidden <900px | `app.html`, `app.css` |
| Brand | `.brand`, `.brand__logo` | links to `/`; SVG wordmark with tagline | `app.html`, `brand/aurum-logo.svg` |
| Primary nav | `.app-nav a[data-nav]` | `aria-current="page"` on the active route; `My play`/`Admin` hidden until authorised | `app.js` |
| Balance chip | `.balance-chip`, `.balance__value` | hidden signed-out; opens account dialog; flashes `.is-up`/`.is-down` after a round | `shell.js` |
| Menu button | `.icon-btn[data-action="open-menu"]` | `aria-expanded`, opens `dialog[data-modal="menu"]` | `app.js` |
| Tabbar | `.tabbar` → `.tabbar__inner`, `.tab` | fixed <900px; 5 items; centre `.tab--fab` search; gold active label; safe-area padding | `app.css` |
| Footer | `.site-footer`, `.site-footer__fine` | virtual-credits statement, original mark, no regulatory badges | `app.html` |
| Skip link | `.skip-link` | visually hidden until focused | `app.css` |

## 2. Lobby

| Component | Markup / classes | States and behaviour | Source |
| --- | --- | --- | --- |
| Banner carousel | `.banner`, `.banner__track`, `.banner__slide.is-active`, `.banner__dots`, `.banner__arrow` | 3 slides; autoplay 6.5s; pause on hover/focus; swipe; dots `aria-current`; reduced-motion stops autoplay | `lobby.js` |
| Search bar (inline) | `.lobby-toolbar`, `.searchbar` | filters name/provider/tags/category live; clear button in empty state | `lobby.js` |
| Category rail | `.cat-rail`, `.cat` | All/Slots/Tables/Roulette with counts; `aria-pressed`; locked on category routes | `lobby.js` |
| Shelf | `.section.shelf`, `.shelf__head`, `.shelf__rail` | titled horizontal rail; optional "See all"; hidden when empty | `lobby.js` |
| Game tile | `.tile`, `.tile__art`, `.tile__label`, `.tile__sub`, `.tile__hot`, `.tile__rtp` | hover lift + gold ring; opens login if signed out; routes to game if signed in | `lobby.js` |
| Results grid | `.tiles` | 3/4/6 columns; search results show `results-meta` | `lobby.js`, `lobby.css` |
| Trust strip | `.trust`, `.trust__item` | three statements with icons; counts derived from catalogue | `lobby.js` |
| Empty / error state | `.state`, `.state__actions` | "No games match" with clear search; API error with retry | `lib/ui.js` |
| Skeleton | `.skeleton--hero`, `.skeleton--card` | shown while a view loads | `app.js` |

## 3. Dialogs

All dialogs are native `<dialog class="modal">`; `showModal()` supplies the focus trap and
Escape handling. Backdrop click closes. `data-action="close-modal"` is the explicit close.

| Dialog | Selector | Contents | Validation |
| --- | --- | --- | --- |
| Log in | `dialog[data-modal="login"]` | brand head, username, password with show/hide, "Keep me signed in", forgot link, register link | username ≥3, password ≥10, server error in `.form__error` |
| Register | `dialog[data-modal="register"]` | username, display name, password + confirmation, strength meter, terms checkbox | username regex `^[A-Za-z0-9._-]{3,64}$`, password ≥10 and match, terms required |
| Forgot password | `dialog[data-modal="forgot"]` | explanation that no email rail exists; operator action | — |
| Account | `dialog[data-modal="account"]` | display name, username, role, balance, links to My play/Admin, sound toggle, sign out | — |
| Search sheet | `dialog[data-modal="search"]` | sticky search input, results list with art/name/provider/RTP, empty state | filters catalogue |
| Menu drawer | `dialog[data-modal="menu"]` | primary navigation, sound, auth actions, disclaimer | — |

Field components: `.field`, `.field__label`, `.field__control`, `.field__action` (eye),
`.field__hint`, `.form__error[role="alert"]`, `.strength` bar, `.checkbox`.

## 4. Game views

### Shared

| Component | Classes | Notes |
| --- | --- | --- |
| Game header | `.slot__head`, `.slot__art`, `.slot__titles`, `.slot__name`, `.slot__provider`, `.slot__badges`, `.tag` | provider shortened; badges wrap to a full-width row; optional Lobby button |
| Side panel | `.slot__side`, `.tabs`, `.panel`, `.paytable`, `.rules`, `.history-list` | Paytable / Rules / Session tabs on slots |
| Readouts | `.readout`, `.readout__label`, `.readout__value` | bet, win, payout; monospace |
| Stepper | `.stepper` | −/value/+, bounds from the game's bet ladder |
| Primary spin | `.spin`, `.is-spinning` | disabled while busy or no bet; pulse while settling |
| Sound toggle | `.sound-toggle`, `.sound-toggle__icon` | `aria-pressed`; inline `sound`/`sound-off` icon |
| Overlay | `.overlay--win`, `.overlay--bonus`, `.overlay__title` | transient result banner |

### Slot cabinet

`.cabinet`, `.reels`, `.reel` (`--reel-rows`), `.cell` with modifiers
`--wild`, `--scatter`, `--bonus`, `--blank`, `--win`, `--dim`, plus `.cell__label`.
Reel count and per-reel rows come from `layout`; symbols, labels and paytable come from
`presentation.slot`. Idle state shows a deterministic face of the game's own symbols.

### Blackjack

`.table--blackjack`, `.table__felt`, `.hand`, `.hand--dealer`, `.hand__head`,
`.hand__cards`, `.card`, `.card--hidden`, `.card__rank`, `.card__suit`, `.hand__total`,
`.table__actions` (`Deal`, `Hit`, `Stand`, `Double`, `Split`, `Surrender`, `Insurance`,
`Repeat bet`), result tags `--win`/`--lose`/`--info`.

### Roulette

`.wheel-wrap`, `.wheel`, `.wheel__pocket--red/black/green` (angle set with a real CSS
custom property), `.wheel__hub`, `.wheel__result`; `.felt`, `.felt__grid`,
`.felt__number--red/black/green`, outside bets, dozens/columns, `.chip-btn` (58px,
`aria-pressed`), `.history-strip__dot`, `.spin`.

### Coin flip

`.coin-page`, `.game-head`, `.coin-stage`, `.coin`, `.coin__face--heads/--tails`,
`.coin__mark`, `.coin__word`, `.coin__status`, `.coin__outcome`, `.coin__result--win/lose`,
`.coin__count`, `.history-list`. The coin lands on the face the engine returned; heads and
tails faces are distinct and correctly oriented.

## 5. Data views

| Component | Classes | Source |
| --- | --- | --- |
| Page hero | `.hero` | `history.js` |
| Panel | `.panel` | history, admin, game side panel |
| Data table | `.table-wrap`, `.table`, `.num` | history and admin |
| Signed amount | `.net--up`, `.net--down` | history ledger and rounds |
| Loading state | `.state` + spinner | `lib/ui.js` |
| Toast | `.toasts`, `.toast--win/--error` | `lib/ui.js` |
| Tag | `.tag`, `.tag--info`, `.tag--win`, `.tag--lose` | game metadata, admin table |

## 6. JavaScript helpers

| Helper | Contract |
| --- | --- |
| `el(tag, attrs, children)` | creates an element; `text` sets textContent, `html` only for authored SVG/em, `style` supports CSS custom properties, `on*` attaches listeners |
| `mount(node, children)` | clears and appends a fragment |
| `money(amount, scale, currency)` | formats minor units with locale grouping |
| `ratio({numerator,denominator})` | short multiplier text |
| `percent(value)` | two-decimal percentage or `—` |
| `providerLabel(provider)` | drops `(mechanics)`/`/ Engine Original (configuration)` noise for tiles |
| `icon(name)` | 24×24 inline SVG |
| `toast(message, kind)` | stacked feedback |
| `applyTheme(node, theme)` | writes `--panel`, `--accent`, etc. |
| `api.*` | typed endpoint calls; throws `ApiError` |
| `store.*` | token/user/balance persistence, admin check |
| `syncBalance/flashBalance` | header balance updates |

## 7. State matrix (shared)

| State | Visual |
| --- | --- |
| Default | surface/card, line border |
| Hover | lifted 1–2px, gold or green border, stronger shadow |
| Focus-visible | 3px gold outline with 2px offset |
| Pressed/selected | gold fill or `aria-pressed="true"` |
| Disabled/busy | 0.45–0.7 opacity, `cursor: not-allowed`, label change for async actions |
| Error | red text/border, `role="alert"` |
| Empty | `.state` with title, explanation and one action |
| Loading | skeleton or spinner, `aria-busy="true"` |
