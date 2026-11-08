# Roulette Framework

> Reference for `CasinoEngine\Frameworks\Roulette`. Read this when building a
> roulette module. The worked example is
> `src/Games/Synthetic/SyntheticRouletteGame.php`.

The framework keeps the three things that vary between roulette variants — the
wheel, the bet set, and the house rules on a zero — as data or as small objects,
so a new variant is a configuration rather than a code branch.

---

## 1. The wheel

```php
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;

$variant = RouletteVariant::european();   // single zero, 37 pockets
$variant = RouletteVariant::american();   // 0 and 00, 38 pockets
$variant = RouletteVariant::french();     // single zero with la partage/en prison
$variant = RouletteVariant::custom('My Wheel', $order, $colours);
```

| Method | Answers |
| --- | --- |
| `name()` | Display name |
| `pockets()` | The ordered pocket list |
| `pocketCount()` | 37, 38, or however many |
| `colourOf($pocket)` | `red`/`black`/`green` |
| `hasDoubleZero()` / `zeroCount()` | Whether 00 exists, how many zeros |
| `laPartage()` / `enPrison()` | French rules that soften a zero |
| `houseEdge()` | The theoretical house edge for the variant |

`custom()` takes the pocket order and a colour map for each pocket. A variant
that is not on the standard wheel, or a novelty layout, is expressed here
without touching settlement.

---

## 2. Bets

`BetType` is the enumeration of every standard bet, split into inside and
outside by `isInside()`:

| Inside | Outside |
| --- | --- |
| `Straight`, `Split`, `Street`, `Corner`, `SixLine`, `Trio`, `Basket` | `Column`, `Dozen`, `Red`, `Black`, `Odd`, `Even`, `Low`, `High` |

`payoutRatio()` returns the exact standard odds under the engine's "returned
including stake" convention — a straight returns 36:1 + stake = 36, a split 18,
a street 12, a corner 9, a six-line 6, a column/dozen 3, and every even-money
bet 2. Because these are `Ratio` values, an odd stake at 2:1 pays exactly.

`isEvenMoney()` identifies the six red/black/odd/even/low/high bets, which is
the set that la partage and en prison apply to.

### Building a bet

```php
use CasinoEngine\Frameworks\Roulette\Bet\Bet;
use CasinoEngine\Frameworks\Roulette\Bet\BetFactory;
use CasinoEngine\Core\Value\Money;

$factory = new BetFactory(RouletteVariant::european());
$factory->numbers();            // the numbers this variant has

$bet = Bet::straight('17', Money::of(100));                 // a one-number bet
$bet = $factory->make(BetType::Split, ['1', '2'], Money::of(100));
$factory->outsideBets();        // the list of outside bets this variant allows

$bet->covers('17');             // true if the pocket is covered
```

`BetFactory::make()` validates that the numbers a bet names exist on this
variant before it constructs the bet, so an American-only bet cannot be placed
on a European wheel.

---

## 3. The engine

```php
use CasinoEngine\Frameworks\Roulette\RouletteEngine;

$engine = new RouletteEngine(RouletteVariant::european());

$pocket = $engine->spin($rng);              // one pocket, from the injected RNG
$result = $engine->play($bets, $rng);       // spin and settle at once
$result = $engine->settle($bets, '17');     // settle a known pocket
```

* `spin($rng)` — the winning pocket. Randomness comes only from the injected
  RNG, so a seeded run reproduces exactly.
* `play($bets, $rng)` — spins and settles, returning a `RouletteSpinResult`.
* `settle($bets, $pocket)` — settles against a known pocket. This is what tests
  use: pin the pocket, assert the arithmetic, with no randomness involved.
* `variant()` / `betFactory()` — the configured wheel and its bet factory.

---

## 4. Settlement

`RouletteSettlement::settle(array $bets, string $winningPocket,
RouletteVariant $variant, bool $enPrisonSecondChance = false)` returns:

```php
[
    'payout'       => Money,            // total returned, including staked amounts
    'wins'         => list<array{...}>, // one entry per bet, win or lose
    'total_staked' => Money,
    'net'          => Money,            // payout - staked
    'la_partage'   => bool,             // whether la partage softened the zero
    'en_prison'    => bool,             // whether en prison was applied
]
```

Every bet produces exactly one entry in `wins`, tagged `win` or `loss`, so a
caller never has to infer the losing bets from the staked total.

The zero handling is where the variants differ, and it lives here rather than in
the module:

* **English/American** — a zero loses every outside bet.
* **La partage** (French) — an even-money bet that meets a zero returns half the
  stake; `la_partage` is set true in the result.
* **En prison** (French) — an even-money bet that meets a zero is held rather
  than lost. If the caller can offer the second chance, pass
  `enPrisonSecondChance: true` and a subsequent win releases the held stake.

---

## 5. A roulette module

The module is thin, because the framework does the arithmetic. It declares its
variant and its bet limits in metadata, builds a `RouletteEngine`, validates the
submitted bets against the variant, plays, and returns a `GameOutcome` whose
`payoutMultiplier` is the settled payout divided by the stake. The wizard steps
a frontend would animate are not the module's concern: the module returns the
winning pocket and the winning bets, and the frontend visualises them.

The one rule to preserve: the module returns a payout **ratio**, not a Money
amount. `RouletteSettlement` computes Money internally for reporting, but the
outcome the engine settles is the ratio, and the engine credits the wallet.

---

## 6. Testing

Roulette is the easiest framework to test exhaustively because settlement is
deterministic given a pocket. Pin the pocket, place a bet, assert the payout:

```php
$result = $engine->settle([Bet::straight('17', Money::of(100))], '17');
self::assertSame(3600, $result->totalPayout->minorUnits());   // 36:1 including stake
self::assertSame('17', $result->pocket);
```

`RouletteTest` covers each bet type against a winning and a losing pocket, both
variants, and the French zero rules. Add a simulation for the observed
distribution:

```bash
php bin/engine-simulate sim:run --game=synthetic.roulette --rounds=100000 --seed=11 --bet=1.00
```

A roulette simulation is a good sanity check on the pocket distribution: the
observed frequency of each pocket should be near `1 / pocketCount`, and a
departure beyond the reported confidence interval would indicate the wheel
generation is biased.
