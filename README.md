# Universal Casino Game Engine — AURUM

A self-contained PHP 8.3 / MySQL casino-style game platform: a modular engine,
22 registered game modules, a working player interface and an administrator
probability panel.

> **Virtual credits only.** Balances are simulated Philippine Peso credits.
> There is no deposit, withdrawal, wagering of real money, cash-out, crypto or
> payment processing anywhere in the codebase. It is a private programming and
> simulation project.

**Read the full project audit: [FINISHED_ANALYSIS_REPORT.md](FINISHED_ANALYSIS_REPORT.md).**

---

## What is included

| Part | State |
| --- | --- |
| Game engine | 22 modules across slot, card and roulette frameworks; authority is always server-side |
| Player frontend | AURUM — mobile-first lobby, search, phone registration/login, demo OTP, four game interfaces, history |
| Administrator panel | dedicated `admin.php`: users, credits, suspension, game availability, odds/friendly play, audit |
| HTTP API | bearer-token JSON API under `/api/v1` |
| CLI | migrations, users, games, simulation, maintenance purges |
| Database | MySQL/MariaDB schema, eight migrations, one importable `install.sql` |
| Tests | 294 tests / 22,836 assertions; PHPStan and PHP lint clean |
| Deployment bundle | [`htdocs/`](htdocs/) — complete app for a PHP/MySQL shared host |

The 22 modules are 17 slots, two blackjack tables, two roulette tables and one
coin-flip game. Seventeen slots share one metadata-driven cabinet; the interface
is chosen from the game's metadata, not hardcoded per title.

---

## Quick start (development)

Requirements: PHP 8.3+ (`pdo_mysql`, `json`, `mbstring`), MySQL 8.0+ or
MariaDB 10.6+, Composer 2.

```bash
composer install
cp .env.example .env                    # app/runtime settings only
cp config/database.local.example.php config/database.local.php
# edit config/database.local.php with the local database credentials
php bin/console db:migrate               # create the schema
php bin/console serve --host=0.0.0.0 --port=3000
```

Open <http://localhost:3000/>. The lobby is public; register with a phone
number, display name, age and password. Registration grants **300 credits**
once and shows a six-digit demo code in a browser notification (no SMS is
sent). The administrator console is at `/admin.php`; the seeded initial
account is `admin` / `Tyroar123` — change it immediately in a real deployment.

Useful commands:

```bash
php bin/console games:list
php bin/console db:status
composer check                   # lint + PHPStan + PHPUnit
```

The development server (`serve`) refuses to start with `APP_ENV=production`.

---

## Deploy to shared hosting

The complete production application is the **contents of `htdocs/`**. It needs
no Composer, Node.js, Python, Docker, external API, CDN or background worker on
the server.

1. Create a MySQL database and user in the hosting panel.
2. Upload the contents of `htdocs/` to the domain root (`public_html/`) or a
   subdomain root.
3. Copy `htdocs/config/database.local.example.php` to
   `htdocs/config/database.local.php` and enter the host, port, database,
   username, password and charset. This file is ignored by Git; `.env` is not
   needed for database credentials.
4. Import `htdocs/database/install.sql` with phpMyAdmin (fresh install).
5. Make `storage/` writable (`chmod -R 775 storage`), then open the site and
   sign in to the administrator console at `/admin.php`.

`htdocs/` is generated and committed. Rebuild it after source changes:

```bash
PATH="$HOME/bin:$PATH" php tools/build_htdocs.php
```

Full instructions, Nginx rules, troubleshooting and the security checklist:
[documentation/deployment/SHARED_HOSTING_DEPLOYMENT.md](documentation/deployment/SHARED_HOSTING_DEPLOYMENT.md).

---

## Repository map

```
public/                 development document root (front controller + SPA + assets)
htdocs/                 generated, self-contained production tree
src/                    engine, frameworks, games, API, CLI, persistence
config/                 application defaults + database.local.php credentials loader
database/               migrations (and htdocs/database/install.sql in the bundle)
bin/                    CLI entry points
tests/                  PHPUnit suites (unit, framework, transaction, API, integration)
tools/                  inventory, asset generation, htdocs build, browser checks
documentation/          all technical documentation, organised by area
AH77_SOURCE/            supplied reference-site mirror (not shipped; see audit)
```

`public/` and `htdocs/` use the same front controller. The source tree, tests,
tools and reference export are never part of the production upload.

---

## Documentation

| Document | Contents |
| --- | --- |
| [FINISHED_ANALYSIS_REPORT.md](FINISHED_ANALYSIS_REPORT.md) | complete project audit, deployment readiness, limitations |
| [documentation/README.md](documentation/README.md) | documentation index |
| [Backend architecture](documentation/backend/ARCHITECTURE.md) | engine layers, round flow, invariants |
| [API specification](documentation/backend/API_SPECIFICATION.md) | routes, envelope, auth, errors |
| [Game SDK](documentation/backend/GAME_SDK.md) | adding a game |
| [Frontend architecture](documentation/frontend/FRONTEND_ARCHITECTURE.md) | SPA structure and data flow |
| [Design system](documentation/frontend/DESIGN_SYSTEM.md) | tokens and components |
| [Shared hosting](documentation/deployment/SHARED_HOSTING_DEPLOYMENT.md) | deployment runbook |
| [PHP/MySQL compatibility](documentation/deployment/PHP_MYSQL_COMPATIBILITY.md) | requirements and host limits |
| [Production security](documentation/deployment/PRODUCTION_SECURITY.md) | controls and checklist |
| [Project status](documentation/project/PROJECT_STATUS.md) | phase and publication record |
| [Known issues](documentation/project/KNOWN_ISSUES.md) | open and resolved items |
| [Frontend test report](documentation/reports/FRONTEND_TEST_REPORT.md) | browser verification evidence |

---

## Verification summary

* `composer check` → **277 tests, 22,791 assertions, OK**; PHPStan and PHP lint
  clean (baseline before the frontend work: 274 tests / 22,777 assertions).
* Mobile (390×844) and desktop (1440×900) Chromium runs: lobby, login,
  registration, slot spin, blackjack deal, roulette spin, coin flip, search and
  history, with **no console errors**.
* A fresh database imported from `htdocs/database/install.sql`: 19 tables,
  five migrations, no drift.
* The production entry point served HTML/CSS/JSON correctly; private paths
  returned 404; registration and both password algorithms worked.
* The production tree copied to `/tmp` and served there worked identically,
  proving it does not depend on the repository around it.

Not tested here: Apache/LiteSpeed (only PHP's built-in server), a live shared
host, MySQL 8.0 (MariaDB 10.11 was used), HTTPS, non-Chromium browsers and
screen readers. See the root report for the complete list.

---

## License and scope

Proprietary (`composer.json`). The supplied `AH77_SOURCE` mirror is a
third-party commercial site and is **not** redistributed through the
application: no provider artwork, tenant branding, regulatory marks, tracking
code or real-money promotion is shipped. The AURUM brand and all game tiles are
original generated assets. See
[the reference analysis](documentation/frontend/REFERENCE_WEBSITE_ANALYSIS.md)
and [security audit](documentation/frontend/SECURITY_AUDIT.md).
