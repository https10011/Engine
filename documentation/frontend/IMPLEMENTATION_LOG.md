# Frontend Implementation Log

Chronological record of the frontend rebuild, the decisions behind it and the evidence
collected. Commit hashes refer to this branch.

## Phase 0 — inventory and constraints

* Enumerated `AH77_SOURCE/` with `tools/inventory_source.php`: 164 files, 15.74 MB,
  57 provider thumbnails, 18 promotional slides, 13 provider wordmarks, 2 captured
  HTML documents, 5 CDN libraries and one obfuscated bundle.
* Confirmed the collection is a saved Vue 2/Vant application, not a source tree; game
  clients and API responses are absent.
* Confirmed `AH77_SOURCE/website-source-export/` duplicates the top-level export
  byte-for-byte.
* Decision: reproduce the **layout language** with original branding and assets; do not
  ship third-party marks, provider art, deposit advertising or tracking code.
* Captured reference screenshots into `artifacts/reference/` for comparison.

## Phase 1 — backend prerequisite

* Existing suite baseline: **274 tests / 22,777 assertions**, OK with MariaDB.
* Found no self-service registration endpoint; the login dialog had nothing to call.
* Added `POST /api/v1/auth/register` (additive), three tests, config knobs
  (`ALLOW_SELF_REGISTRATION`, `REGISTRATION_STARTING_BALANCE_MINOR`), and API docs.
* Commit `7e87359` — *Add self-service player registration endpoint*.

## Phase 2 — design system and shell

* Replaced the placeholder `public/app.html` with the AURUM shell: sticky deep-green
  header, desktop nav, bottom tabbar with a raised search action, footer disclaimer.
* Wrote `app.css` tokens and components (buttons, fields, dialogs, tags, states,
  skeletons, tabbar, toasts) and the inline SVG icon set in `lib/icons.js`.
* Native `<dialog>` implementations for login, registration, forgot password, account,
  search and the menu drawer.
* Router rewritten with `pushState`, route titles, active-nav state, staleness tokens and
  view teardown.
* Session storage reworked: `sessionStorage` by default, `localStorage` only with
  "Keep me signed in"; header/account sheet kept in sync by `lib/shell.js`.

## Phase 3 — lobby

* Lobby is entirely catalogue-driven: category counts, shelves, max-win ordering, search,
  recent games and the all-games grid come from `GET /api/v1/games/lobby`.
* Three-slide carousel with autoplay, dots, arrows, swipe and reduced-motion support.
* Category routes (`/slots`, `/tables`, `/roulette`) lock the filter while sharing one
  implementation.
* Generated 22 game tiles, 4 brand files and 3 banners with
  `tools/generate_assets.php`, using each module's `ThemeCatalogue` values.
* Commit `edcdeee` — *Rebuild the player interface as AURUM* (shell, lobby, assets and
  all game views).

## Phase 4 — game interfaces

* Slot view: metadata-driven reels/rows, symbol map, bet ladder from the game's own
  min/max/step, paytable/rules/session side panel, free-spin indicators, real spin
  action and balance handling.
* Blackjack: server-state-driven hands, hidden dealer card, phase-aware actions.
* Roulette: pocket ring from the framework order, felt/outside bets, chips, place-bets
  then spin, winning-number animation, result history.
* Coin flip: separate view because its action model (fixed heads call) is not blackjack;
  dispatched by id/tag before the category switch.
* All 22 modules open a real interface; the four interface types were exercised with
  live rounds during verification.

## Phase 5 — verification and fixes

Playwright + Chromium were installed outside the repository. The container had no
system fonts, which crashed Chromium's renderer on the lobby; installing DejaVu/Liberation
and a local `fontconfig` fixed the harness (not an application bug).

Real defects found and fixed during this phase (details in
[FRONTEND_TEST_REPORT.md](FRONTEND_TEST_REPORT.md)):

* delegated submit handler used `currentTarget` instead of the form;
* HTML `pattern` with an unescaped hyphen rejected by the `v` flag;
* CSS custom properties set through `Object.assign` never applied, leaving the roulette
  wheel without pockets;
* slot/roulette header flex wrapping;
* emoji tofu for sound/card glyphs;
* long provider strings on tiles;
* history view expecting money objects instead of flat minor units;
* roulette history strip not repainted;
* coin backface visible because a filter flattened `preserve-3d`;
* chip text overflow and duplicate search close button.

Verification results: **277 tests / 22,791 assertions OK**, PHPStan clean, no console
errors at 390×844 or 1440×900, no horizontal overflow at desktop, real spins/deal/flip
settled. Screenshots in `artifacts/verification/`.

## Phase 6 — documentation

* Inventory, asset mapping, reference analysis, security audit, gap analysis,
  architecture, design system, component catalogue, game interfaces, missing assets,
  test report, this log and the final report under `documentation/frontend/`.
* `README.md` gained a player-frontend section; `API_SPECIFICATION.md` documents
  registration.

## Commit record

| Commit | Contents |
| --- | --- |
| `7e87359` | registration endpoint, config, tests, API spec |
| `edcdeee` | AURUM shell, design system, lobby, game views, generated assets |
| `86cd5c3` | inventory/generator/browser verification tooling |
| final commit | frontend documentation and README |

## Open items

* No non-Chromium browser or real-device test.
* Administrator panel not re-exercised in the browser run.
* Licensed provider artwork can replace the generated tiles at the documented paths.
* Public deployments should consider email verification or an invite code for
  registration; the switch and the rate limiter are already there.
