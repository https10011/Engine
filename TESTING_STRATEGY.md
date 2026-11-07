# Testing Strategy

> How this project is verified, and — just as importantly — what each test does
> and does not prove.

The tests are the evidence that the engine works. A green suite does not mean
the code is correct; it means the behaviours under test are correct. This
document says which behaviours those are, and where the gaps remain.

---

## 1. Running the suite

```bash
composer test               # everything PHPUnit has (166 tests, ~21.8k assertions)
composer test:unit          # unit + framework + simulation, no database needed
composer test:integration   # needs MySQL; skips cleanly without it
composer check              # php -l on every file, then PHPStan, then the suite
```

Individual suites:

```bash
php vendor/bin/phpunit --testsuite framework
php vendor/bin/phpunit --testsuite transaction
php vendor/bin/phpunit --testsuite api
```

Static analysis and style:

```bash
composer lint    # php -l across src, tests, bin, public
composer stan    # PHPStan, level configured in phpstan.neon.dist
composer cs      # PHP_CodeSniffer
```

`phpunit.xml` sets `failOnWarning`, `failOnRisky` and
`beStrictAboutTestsThatDoNotTestAnything`, so a test that asserts nothing fails
rather than passes quietly.

---

## 2. Test types and what each one establishes

### Unit tests — `tests/Unit`

Pure logic, no database, no clock. These are the fastest and the most precise.

| File | Establishes |
| --- | --- |
| `Value/MoneyAndRatioTest.php` | Integer-minor-unit arithmetic; exact ratio maths; rounding |
| `Service/RngTest.php` | The seeded RNG is reproducible; the production RNG is not seedable |
| `Service/BettingAndPayoutTest.php` | Bet-limit enforcement; payout computation; the max-win ceiling |
| `Sdk/SdkExampleModuleTest.php` | A real SDK module registers, initialises and executes |
| `Sdk/GameModulePurityTest.php` | A game module contains no forbidden global randomness |

The **purity test** is worth calling out. It scans every `GameInterface`
implementation under `src/Games` for calls to `random_int`, `rand`, `mt_rand`,
`shuffle` or `Math.random`-style sources. A module that reaches for a global RNG
instead of the injected one would make outcomes unauditable and unseedable;
this test fails the build if one appears. It was verified by injecting a
`random_int()` call into a module and confirming the test fails, then reverting.

### Framework tests — `tests/Framework`

The mathematics, with pinned inputs so there is no randomness at all.

| File | Establishes |
| --- | --- |
| `Slot/EvaluationModelsTest.php` | Payline, ways, cluster, scatter and composite evaluation on known grids |
| `Slot/FeatureRegressionTest.php` | Free-spin counting, cascade termination, multiplier growth |
| `Card/CardAndBlackjackTest.php` | Deck integrity, hand totals, every settlement branch, soft-17 |
| `Roulette/RouletteTest.php` | Each bet type against a winning and a losing pocket; French zero rules |

These use `spinAt()` / `settle()` style entry points that take a fixed outcome,
so a failure is a logic failure, not a bad-luck failure.

### Integration tests — `tests/Integration`

The engine and its services against a real database.

| File | Establishes |
| --- | --- |
| `RoundServiceIntegrationTest.php` | A full round: wager debited, payout credited, round and ledger rows written, state persisted; a multi-action blackjack round settles against the deal stake |
| `RepositoryReportingTest.php` | History and statistics queries return the right rows |

`DatabaseTestCase` **skips** rather than fails when no database is reachable,
and the skip message names the missing configuration. That keeps the unit and
framework suites runnable on a laptop without MySQL while being honest that
the integration layer was not exercised.

### Transaction tests — `tests/Transaction`

The money-safety net. `WalletIntegrityTest.php` drives the failure modes the
directive calls out:

* a failure **before** the wager — the balance is untouched;
* a failure **after** the wager but **before** the result is recorded — the
  transaction rolls back, so the wager is not lost;
* a failure during payout — same rollback;
* a **duplicate request** with the same idempotency key — settles once;
* two requests racing the **same wallet** — one wins the row lock, the other
  serialises behind it and the final balance is arithmetically correct;
* a client **retrying after a timeout** — returns the stored result, moves no
  money.

Concurrency is tested with separate PDO connections rather than threads, which
is what an actual shared-host deployment looks like.

### API tests — `tests/Api`

`ApiTest.php` drives the kernel with real request objects: login, an
unauthenticated 401, a 403 on another user's session, a 422 on a bad bet, an
idempotent replay, and the health route. It asserts status codes and the
response envelope, not just that a route exists.

### Simulation tests — `tests/Simulation`

`SimulationStatisticsTest.php` checks the statistics the harness computes
(frequency, mean, standard error, distribution buckets) — the arithmetic, not
the game outcome.

---

## 3. The simulation harness

`bin/engine-simulate` plays a registered game many times with no browser and
reports observed statistics.

```bash
php bin/engine-simulate sim:run --game=synthetic.slot3x3 \
    --rounds=100000 --seed=42 --bet=1.00 --json --csv=/tmp/run.csv

php bin/engine-simulate sim:compare --game=synthetic.slot3x3 \
    --seed-a=1 --seed-b=2 --rounds=50000
```

It reports hit frequency, win/loss/push counts, the payout distribution, the
largest and smallest observed results, feature-trigger rates, and an RTP
**estimate** with a standard error and a 95% confidence interval.

### What a simulation does and does not prove

* A simulation is a **sample**. Every figure it prints is labelled an
  observation of that sample, not a theoretical value.
* It does **not** prove a theoretical RTP. A 100k-round run of a game whose
  true RTP is 0.95 will usually land near 0.95, but the confidence interval is
  what makes that statement meaningful, and the harness prints it.
* Two runs under different seeds landing close together is evidence the result
  is not seed-specific. A single run cannot show that, which is why
  `sim:compare` exists.
* The harness **never** writes to a wallet. The driver applies the outcome's
  multipliers itself and touches no wallet, session row or transaction, so a
  large run cannot affect a real balance. Because that makes it a second
  implementation of the settlement arithmetic, an integration test
  (`testSimulationDriverAgreesWithTheRoundService`) asserts the driver and the
  round service pay and wager the same amounts on an identical seeded outcome.

The directive requires honesty about this, and the reports enforce it in their
wording.

---

## 4. What is deliberately not tested

Named plainly, because a known gap is safer than a guessed-at pass:

* **No browser or DOM tests.** The frontend is deferred, so there is nothing
  to test.
* **No long-running soak or fuzz suite.** There is no equivalent of an
  hours-long random-input campaign against the API.
* **No independent third-party security review.** See
  [SECURITY_AUDIT.md](SECURITY_AUDIT.md) — the audit is self-conducted.
* **No load test.** Query counts and index coverage were reviewed by reading
  the code and the schema; no throughput measurement was taken on a
  shared-hosting-class machine.
* **No true multi-process concurrency test.** Concurrency is proven with
  independent connections and row locks, not with forked processes.

---

## 5. Writing a test for a new module

Follow `tests/Unit/Sdk/SdkExampleModuleTest.php`. In short:

1. Build the module and assert its metadata (id, category, limits) is
   self-consistent.
2. Unit-test the framework pieces it uses with pinned inputs — evaluation,
   settlement, feature transitions.
3. Register it in a container and drive a full action through the runtime,
   asserting the wallet moved by exactly the expected amount.
4. Run a small seeded simulation and assert only the sample statistics, never a
   theoretical RTP.

Rules the suite enforces on new code:

* a module must not call a global RNG (purity test);
* a test must assert something (PHPUnit strict mode);
* a flag, warning or risky test fails the run.
