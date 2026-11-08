# Design System — AURUM

The visual language borrows the reference lobby's *structure* (deep green, gold, light
canvas, rounded cards) but uses an original palette, type scale and icon set. All tokens
live in `public/assets/css/app.css` under `:root`.

## 1. Colour

| Token | Value | Use |
| --- | --- | --- |
| `--bg` | `#f2f5f1` | page canvas |
| `--bg-2` | `#e8eee8` | subtle canvas wash |
| `--surface` | `#ffffff` | cards, dialogs, tables |
| `--surface-2` | `#f7faf7` | inset rows, hover |
| `--surface-3` | `#eef3ee` | chips, code, skeleton |
| `--ink` | `#10241b` | primary text |
| `--muted` | `#5d7468` | secondary text |
| `--faint` | `#85988d` | hints, timestamps |
| `--green-950` | `#062a1e` | footer, darkest stage |
| `--green-900` | `#083326` | header gradient end |
| `--green-800` | `#0b4231` | header, tabbar |
| `--green-700` | `#10553f` | hover/active greens |
| `--green-600` | `#14684c` | links, icons |
| `--green-500` | `#1b8560` | focus ring, positive accents |
| `--green-soft` | `#e3f1e9` | info chips |
| `--gold-50` | `#fff8dd` | soft highlight |
| `--gold-100` | `#ffefb4` | borders on dark |
| `--gold` | `#f6c744` | primary action, FAB, active nav |
| `--gold-2` | `#e8ae1f` | gradient end, borders |
| `--gold-3` | `#b98409` | coin edge, pressed |
| `--gold-soft` | `#fdf0c4` | badge washes |
| `--win` | `#1a8a57` | wins, positive net |
| `--lose` | `#c53f45` | losses, errors |
| `--warn` | `#b37208` | warnings |
| `--info` | `#2563a8` | informational |

Game pages override a small set on the stage (`--panel`, `--panel-2`, `--line`,
`--ink-dim`, `--ink-faint`, `--bg-elevated`) so the same components work on dark
surfaces. Each game also passes its own `theme.base/panel/accent/accent2/ink` through
`applyTheme()`, which writes CSS custom properties inline (via `style.setProperty`).

Contrast: body ink on canvas and white on `--green-900` exceed 4.5:1; gold text is used
for large/bold labels and on dark only, not for small body copy on white.

## 2. Typography

```
--font: "SF Pro Text", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
        "Helvetica Neue", Arial, "Noto Sans", sans-serif
--font-display: same, display cut
--mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace
```

| Role | Size / weight |
| --- | --- |
| Page title `h1` | `clamp(1.55rem, 3.4vw, 2.3rem)`, 800 |
| Section `h2` | `clamp(1.18rem, 2.4vw, 1.55rem)`, 800 |
| Card title `h3` | 1.05rem, 750 |
| Body | 0.9375rem (15px), 400, line-height 1.5 |
| Small / meta | 0.8rem, 0.75rem |
| Eyebrow / tag | 0.66–0.72rem, 750, uppercase, letter-spacing .05–.2em |
| Numerals | `--mono` for money, RTP, counts and IDs |

No web font is requested; the stack uses the player's system UI font.

## 3. Shape and elevation

| Token | Value |
| --- | --- |
| `--radius-sm` | 12px |
| `--radius` | 18px |
| `--radius-lg` | 24px |
| `--radius-pill` | 999px |
| `--shadow-sm` | `0 6px 16px rgba(8,51,38,.10)` |
| `--shadow` | `0 16px 34px rgba(8,51,38,.12)` |
| `--shadow-gold` | `0 10px 24px rgba(232,174,31,.30)` |

Cards use `--radius`, dialogs `--radius-lg`, buttons/chips pills. The tabbar, header and
game stages are darker surfaces with their own inset highlights.

## 4. Spacing and layout

* Base spacing follows a 4px grid (`0.25rem` steps); common gaps 6, 8, 12, 16, 24px.
* The app content is centred with `clamp(12px, 3vw, 24px)` side padding and widens to
  `max-width: 1180px` on desktop.
* Lobby: banner → search → category rail → shelves → all-games grid → trust strip.
* Game pages: stage (header, cabinet, controls) + optional 320px side panel.
* Breakpoints: 720px (4-column lobby), 900px (header nav, tabbar hidden, 6 columns),
  940px (two-column game stage).

## 5. Components

### Buttons

| Class | Appearance | Use |
| --- | --- | --- |
| `.btn--gold` | gold gradient, dark text, `--shadow-gold` | primary (Log in, Create account, Flip) |
| `.btn--outline-gold` | transparent, gold border | secondary on dark header |
| `.btn--ghost` | surface with line border | neutral actions (Lobby, Max bet, Stand) |
| `.btn--primary` | green solid | Deal/Hit, confirmations |
| `.btn--danger` | red text/border | destructive admin actions |
| `.icon-btn` | 40px target with icon | menu, dialogs |
| `.btn--sm` / `.btn--lg` | size modifiers | header / hero |

Disabled buttons keep their label, lower opacity and change the cursor; busy buttons
store their label in `data-label` while showing "Signing in…".

### Forms

Inputs use a 1px `--line` border, 12px radius, 12px vertical padding; labels are small
caps. Focus is a 3px gold ring (`:focus-visible`). Errors use `.form__error` with
`role="alert"`; help text uses `.form__hint`. Password fields have a show/hide icon
button with `aria-pressed`.

### Dialogs

Native `<dialog>` with `.modal`:

* `.modal__head` — dark green panel with brand mark, title, sub and close button;
* `.modal__body` — white form area;
* `.modal__foot` — secondary action / legal note;
* backdrop dims and blurs the page; Escape and backdrop click close;
* mobile: near-full-height sheet; desktop: centred card (≤760px for search, ≤470px for
  auth).

### Tiles and shelves

* `.tile` — 1:1 art area + green label bar + provider line; hover lifts 2px and reveals
  a gold border; focus-ring keyboard accessible.
* `.tile__art` — generated SVG, lazy-loaded, with `HOT` and RTP badges in the corners.
* `.tile--table` — same tile for non-slot categories.
* `.shelf__rail` — horizontal scroll with snap and hidden scrollbar; 2.2–3.2 cards
  visible on phones.
* `.cat-rail` / `.cat` — category buttons with icon, label and count; active is gold.

### Banner

`.banner` is a 750:308-ish panel (150px tall at 390px) with layered art, copy, CTA, two
arrows and dots. Slides cross-fade/translate; autoplay is 6.5s and pauses on hover/focus;
swipe threshold 45px; respects `prefers-reduced-motion`.

### Game surfaces

* `.slot__head` — art, name, short provider, badges (volatility, max win, RTP) wrapping
  to their own row, optional Lobby button.
* `.cabinet` — reels grid with per-reel row counts from presentation; `.cell` for
  symbols with `--wild`, `--scatter`, `--bonus`, `--blank`, `--win`, `--dim` states.
* `.controls` — bet readout, stepper, max bet, win readout, SPIN, sound toggle, Lobby.
* `.table__felt`, `.hand`, `.card` — blackjack felt, hands and playing cards.
* `.wheel`, `.wheel__pocket`, `.wheel__hub` — roulette number ring and hub; `.felt` and
  `.chip-btn` for the betting layout.
* `.coin-stage`, `.coin`, `.coin__face` — 3D coin with heads/tails faces and land
  animations.

### Feedback

* `.toast` — bottom-centre stacked messages, green/gold or red accent, 4s lifetime.
* `.state` — empty/error loader with title, body, action.
* `.skeleton` — lobby/game placeholders while data loads.
* `.overlay` — transient win/bonus banner over the cabinet.
* `.balance-chip` — header credits; flashes green/red on change.

### Data display

`.table` (history/admin) uses small caps headers, monospace numerals, right-aligned
numeric columns, zebra hover and `--up/--down` net colours. `.tag`/`.tag--info` are
uppercase pills; game pages override them to translucent dark surfaces.

## 6. Motion

| Element | Timing |
| --- | --- |
| Hover/press | 120–180ms ease |
| Dialog | 180ms fade + 12px rise |
| Banner slide | 480ms cubic-bezier |
| Toast | 170ms fade-in, 4s hold |
| Reel result | per-cell fade/dim, 900ms before readout |
| Coin flip | 900ms flip (two turns) + 520ms landing |
| Roulette | 2.4s wheel spin, result badge pops |

All non-essential motion is disabled under `@media (prefers-reduced-motion: reduce)`.
Sound is on by default, remembered in `localStorage` (`casino.sound`) and toggleable
from the game controls; the WebAudio context is created lazily on the first gesture
because browsers block audio before one. There are no audio files.

## 7. Accessibility

* Skip link to `#main`; landmarks for header, nav, main, footer.
* All icon-only controls have `aria-label`; decorative images use `alt=""`.
* Segmented controls (`aria-pressed`, `aria-selected`), tabbars (`aria-current="page"`),
  dialogs (native focus trap), live regions for balance/toasts/game status.
* Visible `:focus-visible` ring on every interactive element.
* 44px minimum touch targets on phones; colour is never the only signal (net values are
  signed, errors have text).
* Game state is announced in text (e.g. "Tails — the engine takes it"), not only by the
  animation.
