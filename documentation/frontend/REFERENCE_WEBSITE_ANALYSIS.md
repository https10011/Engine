# Reference Website Analysis — ah33c5.org/m

This document describes what the supplied `AH77_SOURCE/` mirror actually is and which
parts of its design language the AURUM player interface reproduces. It is an analysis,
not a copy: the mirror is a third-party commercial site and its branding, artwork,
copy and tracking code are not redistributed.

## 1. What was supplied

`AH77_SOURCE/` is a saved mobile web application, not a source repository. The full
file-level inventory is in [AH77_SOURCE_INVENTORY.md](AH77_SOURCE_INVENTORY.md);
the relevant facts are:

| Property | Value |
| --- | --- |
| Files | 164 (15.74 MB), one export duplicated byte-for-byte |
| Application | Vue 2 + Vant mobile SPA, webpack-bundled and minified |
| Capture points | `/m` mobile lobby, `/mobile/mc` member centre, `/lotto` bundles, WPS manifest |
| Game thumbnails | 57 provider icons from 15 providers (170×170 / 244×244) |
| Banners | 18 lobby slides at 750×308, plus portrait promos, one GIF |
| Game clients | **Absent** — provider iframes were remote and are not in the export |
| API responses | **Absent** — no catalogue, wallet or session payloads were captured |
| Source maps / package.json | **Absent** — no build pipeline can be reconstructed |

The captured `index.html` is a rendered DOM snapshot with the webpack runtime inlined.
It shows the lobby with 18 banner slides, 49 game cards and a winners ticker, but it
cannot be run as an application from the files alone.

## 2. Layout and visual language observed

The reference is a mobile-first lobby:

* **Header** — deep green (`rgb(34, 103, 75)` sampled from the snapshot), tenant logo at
  the left, a gold pill and a small icon at the right, fixed to the top.
* **Rounded content sheet** — the page body begins below the header with a light grey
  canvas and cards with roughly 14–18px corner radii.
* **Banner carousel** — 18 slides, aspect ratio close to 750:308, dots underneath,
  autoplay, one call-to-action per slide.
* **Game grid** — three columns of square thumbnails on a 390px viewport. Each tile has
  the provider artwork, then a dark-green name bar with white text, and often a small
  badge in a corner.
* **Category row** — Hot, Slot, Fishing, Live Casino, Poker, Lottery, Sport, E-sport,
  with a small icon above the label. The first item is highlighted.
* **Bottom navigation** — five items with a raised circular gold "Deposit" button in the
  centre. The other four are Home, Games, Wallet and Account-style destinations.
* **Login screen** — a dark green textured card with gold coin decorations, the tenant
  mark, two fields, a gold submit button and links to register/forgot password.
* **Trust footer** — PAGCOR and BMM-style regulatory marks, an 18+ notice, provider
  wordmarks, and a long legal paragraph.

Motion is restrained: carousel slides, tile press states, a spinning-wheel animation in
the roulette client and a modal sheet for login. Typography is a system sans stack;
prices and bet amounts use tabular/monospace figures in places.

## 3. What AURUM reproduces

AURUM is an original interface for the engine in this repository. The reference guides
the *layout language* only:

| Reference pattern | AURUM implementation |
| --- | --- |
| Deep-green fixed header with brand at left, actions at right | `.app-header` in `public/app.html` / `app.css` |
| Gold primary and outline-pill actions | `.btn--gold`, `.btn--outline-gold`, `--gold` tokens |
| Light canvas, rounded cards, green text | `--bg`, `--surface`, `--radius`, `--green-*` tokens |
| Banner carousel with dots and a CTA | `buildBanner()` in `views/lobby.js`, three original slides |
| Three-column mobile game grid with green name bars | `.tile`, `.tile__label` in `lobby.css` |
| Category rail with counts and icons | `.cat-rail`, `.cat` |
| Bottom tab bar with a raised central action | `.tabbar`, `.tab--fab` with a search FAB |
| Dark, textured login card with gold accents | `dialog[data-modal="login"]` and `.modal__head` |
| Trust strip near the footer | `.trust` in the lobby and the site footer disclaimer |
| Desktop widening instead of a separate design | six-column tile grid and header nav at ≥900px |

## 4. What AURUM deliberately does not reproduce

* **Brand names and marks** — no AH33, AH77 or tenant logo, wordmark, mascot or PAGCOR /
  BMM badge is used. The AURUM name, monogram and "virtual casino" tagline are original.
* **Provider artwork** — the 57 cached thumbnails and 13 provider wordmarks are
  third-party copyrighted assets. AURUM generates its own SVG tiles from engine theme
  metadata (see [ASSET_MAPPING.md](ASSET_MAPPING.md)).
* **Promotional banners** — the captured slides advertise deposits, bonuses and
  real-money prizes that do not exist in this engine. AURUM's three slides describe
  virtual credits, self-service registration and server-settled rounds.
* **Deposit / withdrawal flows** — the reference's central "Deposit" action has no
  counterpart because the engine has no payment rail. The central action is search.
* **Third-party scripts** — Google Tag Manager, Facebook pixel, Telegram login, Sentry
  and the obfuscated browser bundle are not included (see [SECURITY_AUDIT.md](SECURITY_AUDIT.md)).
* **Provider game clients** — the engine has 22 registered modules, not the reference's
  49 provider games. Each registered game gets a real interface; no fake tiles or
  unplayable placeholders are shown.

## 5. Responsive observations

The reference is designed for a 375–430px viewport and relies on horizontal shelves.
AURUM keeps that behaviour on phones and, above 720px, widens the grid rather than
switching to a separate desktop product:

| Width | Grid | Navigation | Header |
| --- | --- | --- | --- |
| < 720px | 3 columns | bottom tabbar, centred search FAB | brand + login/register + menu |
| 720–899px | 4 columns | bottom tabbar | same |
| ≥ 900px | 6 columns (lobby content max 1180px) | header nav; tabbar hidden | brand + nav + balance + menu |

The desktop game views use a two-column stage (cabinet + side panel) at ≥940px and a
single column below that. All interactive targets are at least 44px tall on touch
viewports.

## 6. Evidence

Screenshots of the reference captured during the analysis are in `artifacts/reference/`
(`ref-top.png`, `ref-games.png`, `ref-footer.png`, `ref-login.png`). They are kept out of
version control because they contain third-party branding.
