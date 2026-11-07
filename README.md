# Universal Casino Game Engine

A reusable, modular, PHP 8.3+ game engine for building multiple independent
casino-style games (slots, card games, roulette) on top of a single
game-agnostic core.

> **Virtual credits only.** This project uses simulated Philippine Peso
> (PHP) credits exclusively. There is no real-money deposit, withdrawal,
> wagering, cash-out, cryptocurrency, or payment processing anywhere in the
> codebase. It is a private programming and simulation project.

---

## What this is

The engine is the *platform*: a universal core, a set of shared services
(RNG, wallet, betting, payout, session, statistics, history), three game
frameworks (slot, card, roulette), a documented Game SDK, a MySQL persistence
layer, a thin HTTP/JSON API, a CLI, and a simulation harness.

The 18 named target games are **future modules**, not part of this delivery.
This repository ships the engine plus three *synthetic* fixtures that prove
each framework executes end-to-end through the real engine.

## Documentation map

| Document | Contents |
| --- | --- |
| [AUTONOMOUS_EXECUTION_PLAN.md](AUTONOMOUS_EXECUTION_PLAN.md) | Phases, objectives, deliverables, acceptance criteria |
| [PROJECT_STATUS.md](PROJECT_STATUS.md) | Live status, commit/branch record |
| [DEVELOPMENT_LOG.md](DEVELOPMENT_LOG.md) | Per-phase engineering log with evidence |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Layering, boundaries, lifecycle, decisions |
| [ENGINE_SPECIFICATION.md](ENGINE_SPECIFICATION.md) | Core contracts and runtime semantics |
| [GAME_SDK.md](GAME_SDK.md) | How to build a new game module |
| [FRAMEWORK_SLOT.md](FRAMEWORK_SLOT.md) | Slot framework reference |
| [FRAMEWORK_CARD.md](FRAMEWORK_CARD.md) | Card framework reference |
| [FRAMEWORK_ROULETTE.md](FRAMEWORK_ROULETTE.md) | Roulette framework reference |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) | Tables, keys, indexes, migrations |
| [API_SPECIFICATION.md](API_SPECIFICATION.md) | Endpoints, payloads, errors |
| [TESTING_STRATEGY.md](TESTING_STRATEGY.md) | Test layers and how to run them |
| [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | Shared-hosting deployment |
| [SECURITY_AUDIT.md](SECURITY_AUDIT.md) | Audit findings and fixes |
| [RESEARCH_NOTES.md](RESEARCH_NOTES.md) | Game mechanics research |
| [KNOWN_ISSUES.md](KNOWN_ISSUES.md) | Honest limitation list |
| [CHANGELOG.md](CHANGELOG.md) | Change history |

## Quick start

```bash
composer install
cp .env.example .env         # then edit credentials
php bin/console migrate       # create schema
php bin/console game:list     # list registered games
php bin/console sim:run synthetic-slot --spins=100000 --seed=42
php bin/console serve         # dev HTTP server for the API
vendor/bin/phpunit
```

See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for production setup.

## Directory structure

```
bin/            CLI entry points (console, dev server)
config/         Environment-driven configuration
database/       Migrations and seed data
public/         Webroot (the only publicly served directory)
src/
  Core/         Universal engine core (game-agnostic)
  Services/     Shared engine services
  Frameworks/   Slot, Card, Roulette frameworks
  Games/        Game modules (SDK consumers)
  Persistence/  Repositories, migrations runner
  Api/          HTTP/JSON API (thin controllers)
  Cli/          Console commands
tests/          Unit, integration, framework, transaction, simulation tests
docs/           Diagrams and developer guides
```

## Requirements

* PHP 8.3+ with `pdo_mysql`, `mbstring`, `json`, `openssl`
* MySQL 8.0+ / MariaDB 10.6+
* Composer 2.x
* No Docker, no Node server, no background workers required

## License

Proprietary / private. All game mechanics are independently implemented from
publicly observable behaviour; no proprietary provider code or assets are used.
