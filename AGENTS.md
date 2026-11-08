# AGENTS.md — working notes for this repository

PHP 8.3+ modular casino game engine (virtual credits only). No real money,
payments, withdrawals or crypto anywhere in this project.

## Commands

```
composer check          # lint + phpstan + phpunit — run before every commit
composer lint           # php -l over src, tests, bin, public
composer cs             # phpcs --standard=phpcs.xml.dist
composer stan           # phpstan analyse (config: phpstan.neon.dist)
composer test           # phpunit

php bin/console games:list                    # every registered game
php bin/console games:show --game=<id>        # metadata + config for one game
php bin/console user:create --username=<n> --password=<p> --balance=100000
php bin/console user:admin --username=<n>     # grant/revoke admin role
php bin/console serve                         # dev HTTP server (development only)
php bin/engine-simulate sim:run --game=<id> --rounds=N --seed=S
```

## Non-negotiable invariants

* `src/Core` stays game-agnostic — no target title, symbol or paytable name
  may appear under it. Enforced by `GameModulePurityTest`.
* A game module never moves money; it returns payout ratios. Only
  `RoundService` settles a round.
* Randomness comes only from `$context->rng()`. Never `rand()`/`mt_rand()`.
* Repositories are the only classes that write SQL.
* The 168 pre-existing engine tests must keep passing; the suite currently
  holds 274 tests.

## Where things live

| Path | What |
| --- | --- |
| `src/Core/` | bootstrap, registry, discovery, runtime, lifecycle, contracts |
| `src/Framework/Slot/`, `Card/`, `Roulette/` | the three game frameworks |
| `src/Games/` | the 18 target games (16 slots + blackjack + roulette) |
| `src/Probability/` | the global/per-game odds governor and profiles |
| `src/Simulation/` | streaming statistics and the simulation driver |
| `src/Api/` | HTTP controllers and routing |
| `public/` | the player frontend (`app.html`, `assets/`) and `/admin` panel |
| `docs/games/` | one specification per game (18 files) |
| `tests/Integration/AllGamesIntegrationTest.php` | asserts all 18 games run end to end |

## Documentation to keep current

`GAME_DEVELOPMENT_ROADMAP.md` (phase board), `DEVELOPMENT_LOG.md` (chronology),
`PROJECT_STATUS.md` (single-page truth + publication record), `KNOWN_ISSUES.md`
(open items under `## Open`, fixed ones under `## Resolved`).

## Gotchas

* The round response envelope is `data.round`; money fields are objects
  (`{amount, currency, scale, formatted}`), not bare integers.
* The admin API is versioned under `/api/v1/admin/*`; check the route table
  before guessing a path.
* `storage/` is fully git-ignored, so dev databases and simulation output never
  reach the repository.
* Statistical reporting must stay honest: `observed_rtp` is a ratio of sums and
  its confidence interval is centred on that same ratio (see KI-14).
