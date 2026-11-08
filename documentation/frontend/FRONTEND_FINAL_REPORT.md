# Frontend Final Report — AURUM

## 1. Result

The repository now has a complete, working player interface for all 22 registered game
modules, rebuilt as **AURUM** — an original brand and design that follows the reference
lobby's structure without redistributing its identity, artwork or real-money marketing.

* Mobile-first lobby with live catalogue, carousel, filters, search, shelves and trust
  strip.
* Working authentication: login, self-service registration, keep-me-signed-in, logout,
  forgot-password explanation, account sheet.
* Four real game interfaces covering 22 modules: slot cabinet (17), blackjack (2),
  roulette (2), coin flip (1).
* Rounds, wallet ledger and balance are real API data; the client never decides an
  outcome.
* Desktop widens the same design (6-column grid, header nav, two-column game stage) with
  no horizontal overflow.
* Server suite green: **277 tests / 22,791 assertions**, PHPStan and lint clean.

## 2. What was delivered

| Area | Files |
| --- | --- |
| Shell | `public/app.html` — header/nav, `#main`, footer, tabbar, 6 dialogs |
| Design system | `public/assets/css/app.css`, `lobby.css`, `games.css`, `views.css` |
| App | `public/assets/js/app.js` — router, auth, search, dialogs, shell wiring |
| Library | `lib/api.js`, `store.js`, `shell.js`, `dom.js`, `icons.js`, `ui.js`, `sound.js` |
| Views | `views/lobby.js`, `slot.js`, `table.js`, `blackjack.js`, `roulette.js`, `coinflip.js`, `history.js`, `admin.js` |
| Artwork | 22 game SVGs, 4 brand SVGs, 3 banner SVGs under `public/assets/` |
| Backend | `POST /api/v1/auth/register` in `AuthController` + kernel/config + 3 tests |
| Tooling | inventory/doc generators, asset generator, mobile/desktop Playwright scripts |
| Documentation | this directory (13 documents, including the complete source inventory) |

## 3. Design and originality

* Deep-green header (`#083326`), gold actions (`#f6c744`), light canvas (`#f2f5f1`),
  rounded surfaces and a raised central tabbar action mirror the reference's visual
  hierarchy.
* The AURUM name, monogram, logo, banners, icons and game tiles are original. No tenant
  logo, provider artwork, provider wordmark or PAGCOR/BMM mark is used.
* The reference's 18 deposit/bonus banners were replaced by three factual slides about
  virtual credits, registration and server settlement.
* The reference's central "Deposit" action has no counterpart because the engine has no
  payment rail; the central action is search.

## 4. Backend change

One additive endpoint was necessary for a working registration dialog:

```
POST /api/v1/auth/register
→ 201 { user, token, expires_at, balance }
```

It reuses `UserRepository`, `WalletRepository`, `AuthService::hashPassword/issueToken`
inside a transaction, validates username/password/display name, and grants only virtual
credits (`REGISTRATION_STARTING_BALANCE_MINOR`, default 100000 minor units). It can be
turned off with `ALLOW_SELF_REGISTRATION=false`. No existing endpoint, service, game
registration or test changed; three tests were added.

## 5. Verification

| Check | Result |
| --- | --- |
| PHP syntax lint | no errors |
| PHPStan (260 files) | no errors |
| PHPUnit | OK — 277 tests, 22,791 assertions (baseline 274 / 22,777) |
| Mobile Chromium 390×844 | lobby, login, signed-in, slot + real spin, blackjack + real deal, roulette + real spin, coin flip + real flip, search; **0 console errors** |
| Desktop Chromium 1440×900 | 6 columns, 178.5px each, no overflow, header nav visible, tabbar hidden; all views and history correct; **0 console errors** |
| Real settlements | slot 0.20 → 0.14; blackjack deal; roulette 1.00 → 36.00 (winning run); coin flip tails; balances/ledger agreed |

The full defect list (13 items, including two functional blockers) and exact reproduction
commands are in [FRONTEND_TEST_REPORT.md](FRONTEND_TEST_REPORT.md).

## 6. Run it

```bash
composer install
cp .env.example .env          # database credentials
php bin/console migrate
php bin/console serve --host=0.0.0.0 --port=3000
# http://localhost:3000/
```

Create an account from the Register dialog, or use an operator-created account. The
database must be reachable; the interface has no other service dependency.

## 7. Commits

| Commit | Contents |
| --- | --- |
| `7e87359` | self-service registration endpoint, config, tests, API specification |
| `edcdeee` | AURUM shell, design system, lobby, game views, generated artwork |
| `86cd5c3` | inventory, asset generation and browser verification tooling |
| `5800d01` | the documents in this directory and the README frontend section (plus a follow-up wording correction) |

Branch: `freebuff/6ad6f9aa37015975ddeaebcc` (pushed to the connected repository's `main`,
as requested).

## 8. Honest limitations

* Browser verification used Chromium only; Safari/iOS and Firefox were not available.
  No screen-reader or automated WCAG audit was run.
* Only four modules were settled with live actions (one per interface); the other 18
  slots share the exercised cabinet but were not individually spun. No game has a fake
  state.
* The 22 tiles are generated placeholders labelled `ENGINE APPROXIMATION`; licensed
  provider artwork can replace them at the documented paths
  ([MISSING_GAME_ASSETS.md](MISSING_GAME_ASSETS.md)).
* The administrator panel was not re-exercised in this browser run.
* Self-service registration has no email verification or CAPTCHA. Suitable for a
  virtual-credit simulation; a public launch should add verification or an invite code.
* The reference site's provider game clients, real-money flows, 49-game catalogue,
  localization and member-centre features are not reproduced. They are documented in
  [RESOURCE_GAP_ANALYSIS.md](RESOURCE_GAP_ANALYSIS.md).

## 9. Documentation index

| Document | Contents |
| --- | --- |
| [AH77_SOURCE_INVENTORY.md](AH77_SOURCE_INVENTORY.md) | every supplied file, hash, size, type |
| [REFERENCE_WEBSITE_ANALYSIS.md](REFERENCE_WEBSITE_ANALYSIS.md) | what the reference is, what was reused and rejected |
| [ASSET_MAPPING.md](ASSET_MAPPING.md) | reference asset groups → AURUM assets, generation |
| [SECURITY_AUDIT.md](SECURITY_AUDIT.md) | reference findings, AURUM controls, registration risks |
| [RESOURCE_GAP_ANALYSIS.md](RESOURCE_GAP_ANALYSIS.md) | 22 registered games and reference gaps |
| [FRONTEND_ARCHITECTURE.md](FRONTEND_ARCHITECTURE.md) | modules, router, data flow, backend delta |
| [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) | tokens, type, components, motion, accessibility |
| [COMPONENT_CATALOG.md](COMPONENT_CATALOG.md) | every component and state |
| [GAME_INTERFACE_IMPLEMENTATION.md](GAME_INTERFACE_IMPLEMENTATION.md) | the four interfaces and all 22 modules |
| [MISSING_GAME_ASSETS.md](MISSING_GAME_ASSETS.md) | placeholders and replacement paths |
| [FRONTEND_TEST_REPORT.md](FRONTEND_TEST_REPORT.md) | commands, results, defects, limitations |
| [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) | phases and decisions |
