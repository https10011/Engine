# Game SDK

> How to add a game to the engine without modifying the engine.
>
> Everything here maps to real interfaces in `src/Core/Contract`. The worked
> example is `src/Games/Synthetic/SyntheticSlotGame.php`, which is a real
> module, not a sketch, so you can read it beside this document.

---

## 1. The one rule

A game module **describes what happened in game terms**. It never moves money.
It returns *ratios* — how many times the bet is wagered, how many times it is
returned — and the engine's round service converts those into currency, inside
a database transaction, with a row lock on the wallet.

You cannot debit a wallet from a module even by accident: `GameContext`
(what a module receives) has no wallet service on it. This is enforced by the
type system, not by a convention in a code review.

---

## 2. What you implement

A game is any class in a discovered namespace that implements
`CasinoEngine\Core\Contract\GameInterface`:

| Method | You return | Notes |
| --- | --- | --- |
| `metadata()` | `GameMetadataInterface` | Must be deterministic and side-effect free; called at discovery |
| `category()` | `GameCategory` | Must match `metadata()->category()` or registration fails |
| `defaultConfiguration()` | `GameConfigurationInterface` | Validated at registration time |
| `validateConfiguration($configuration)` | — | Throws `ConfigurationException` on a bad config |
| `createInitialState($context)` | `GameStateInterface` | State for a brand-new session |
| `hydrateState($data)` | `GameStateInterface` | Rebuild typed state from a stored snapshot |
| `validateAction($action, $state)` | — | Throws `GameRuleException` for illegal play |
| `execute($action, $state, $context)` | `GameOutcomeInterface` | The actual mechanic |

Discovery scans the namespaces in `games.discovery_namespaces`
(default `CasinoEngine\Games`). If your class is a `GameInterface`, it is
instantiated directly. If it needs collaborators, implement
`GameFactoryInterface` instead and return the module from `create()`.

---

## 3. A minimal module, end to end

This is a complete, working module — a coin flip. It is deliberately tiny so
every part is visible.

```php
<?php

declare(strict_types=1);

namespace CasinoEngine\Games\CoinFlip;

use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\GameOutcome;
use CasinoEngine\Core\Value\GenericOutcomeDetail;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;

final class CoinFlipGame implements GameInterface
{
    public const ID = 'coinflip.heads-tails';

    private const HEADS = 0;

    public function metadata(): GameMetadataInterface
    {
        return new GameMetadata(
            self::ID,
            'Coin Flip',
            GameCategory::Card,
            '1.0.0',
            '1.0.0',
            'Engine Examples',
            Money::of(100),
            Money::of(100000),
            Money::of(100),
            Volatility::Low,
            2,
            0.5,
            true,
            ['example']
        );
    }

    public function category(): GameCategory
    {
        return GameCategory::Card;
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return CoinFlipConfiguration::fromArray([]);
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof CoinFlipConfiguration) {
            throw new ConfigurationException('CoinFlipGame expects a CoinFlipConfiguration.');
        }

        $configuration->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return CoinFlipState::initial();
    }

    /** @param array<string, mixed> $data */
    public function hydrateState(array $data): GameStateInterface
    {
        return CoinFlipState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if ($action->name() !== 'flip') {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }
        if ($action->bet() === null || $action->bet()->isZero()) {
            throw new GameRuleException('A flip requires a bet.');
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        $state = $state instanceof CoinFlipState ? $state : CoinFlipState::initial();

        $call = $context->rng()->int(0, 1);
        $won = $call === self::HEADS;

        return new GameOutcome(
            new CoinFlipState($state->flipsPlayed() + 1),
            new GenericOutcomeDetail('coin_flip', [
                'call'   => 'heads',
                'result' => $won ? 'heads' : 'tails',
            ]),
            $won ? Ratio::of(2) : Ratio::zero(),
            Ratio::one()
        );
    }
}
```

Register it simply by placing it under `src/Games/CoinFlip/`. No engine file
changes. `php bin/console games:list` will show it.

---

## 4. State

State must round-trip without loss; it is stored as JSON on the session row.

```php
<?php

declare(strict_types=1);

namespace CasinoEngine\Games\CoinFlip;

use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\ValidationException;

final class CoinFlipState implements GameStateInterface
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private readonly int $flipsPlayed = 0)
    {
    }

    public static function initial(): self
    {
        return new self();
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function flipsPlayed(): int
    {
        return $this->flipsPlayed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['schema' => self::SCHEMA_VERSION, 'flips_played' => $this->flipsPlayed];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        $schema = (int) ($data['schema'] ?? 0);
        if ($schema !== self::SCHEMA_VERSION) {
            throw new ValidationException("Unsupported coin flip state schema $schema.");
        }

        return new self((int) ($data['flips_played'] ?? 0));
    }

    public function isComplete(): bool
    {
        return false;
    }
}
```

Rules that matter:

* Bump `SCHEMA_VERSION` whenever the shape changes, and let `fromArray()`
  decide how to read an old version. Do not silently renumber.
* `isComplete()` returning `true` tells the engine no further money-moving
  action is accepted on this session.
* Never store a balance, a payout amount or a client-supplied value in state.

---

## 5. Configuration

Configuration is typed and validated. Reject unknown keys so a typo in a game
definition fails at boot rather than leaving a default in place.

```php
<?php

declare(strict_types=1);

namespace CasinoEngine\Games\CoinFlip;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;

final class CoinFlipConfiguration implements GameConfigurationInterface
{
    public function __construct(public readonly bool $doubleOnHeads = true)
    {
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): static
    {
        $known = ['double_on_heads'];
        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw new ConfigurationException(
                'Unknown configuration key(s): ' . implode(', ', $unknown) . '.'
            );
        }

        return new self((bool) ($values['double_on_heads'] ?? true));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['double_on_heads' => $this->doubleOnHeads];
    }

    public function validate(): void
    {
    }
}
```

---

## 6. Actions

An action is created by the API or CLI as a `GameAction`. You can construct one
in a module test:

```php
$action = GameAction::withBet('flip', Money::of(100));
$action = GameAction::withBet('spin', Money::of(100), ['positions' => [0, 3, 7]]);
```

`bet()` is null for an action that must not move money — a free spin, a hit in
blackjack after the deal. Getting this right is what stops a free spin from
being charged: see `SyntheticSlotGame::validateAction`, which rejects a bet on a
free spin.

---

## 7. Outcomes, and how money is applied

`GameOutcome` carries two ratios and, optionally, a stake basis:

| Field | Meaning |
| --- | --- |
| `wagerMultiplier` | Times the stake is wagered. `1` for a paid action, `0` for a free one |
| `payoutMultiplier` | Times the stake is returned. `0` lose, `2` for 2x, `Ratio::of(3, 2)` for 3:2 |
| `stakeBasis` | Usually null. Set it when a round spans several actions and a later action must settle against the stake the round opened with — blackjack is the canonical case |

The engine computes `wager = stake × wagerMultiplier`, `payout = stake ×
payoutMultiplier`, debits and credits inside one transaction, and refuses a
payout above `metadata()->maxWinMultiplier()`.

---

## 8. Testing your module

Extend `CasinoEngine\Tests\Support\TestCase`. It boots the engine with a frozen
clock and can hand you a seeded RNG, so a test is deterministic.

```php
final class CoinFlipGameTest extends \CasinoEngine\Tests\Support\TestCase
{
    public function testTheOutcomePaysEitherTwiceOrNothing(): void
    {
        $game = new CoinFlipGame();
        $action = \CasinoEngine\Core\Value\GameAction::withBet(
            'flip',
            \CasinoEngine\Core\Value\Money::of(100)
        );

        for ($seed = 0; $seed < 10; $seed++) {
            $context = $this->context('coinflip.heads-tails', $seed);
            $outcome = $game->execute($action, CoinFlipState::initial(), $context);

            self::assertContains($outcome->payoutMultiplier()->toFloat(), [0.0, 2.0]);
            self::assertSame(1, $outcome->wagerMultiplier()->numerator());
        }
    }
}
```

This is a real test, not an illustration: it lives at
`tests/Unit/Sdk/SdkExampleModuleTest.php` and runs in the normal suite, so the
example module in this document is proven to work against the engine.

To integrate with the database-backed engine, extend
`CasinoEngine\Tests\Support\DatabaseTestCase` and drive a full round through
`$this->engine->rounds()->execute($session, $action)`.

---

## 9. Exposing the game through the API

There is nothing to do. `GET /api/v1/games` lists every registered module,
`GET /api/v1/games/{id}` returns its metadata and configuration, and
`POST /api/v1/sessions` starts a session for it. Game logic stays in the
module; the controller is a thin adapter and calls the same round service the
CLI does.

---

## 10. The three frameworks

Most modules should be built from a framework rather than from scratch, so you
get the mechanics every game in that family shares.

### Slot — `CasinoEngine\Frameworks\Slot`

Compose a `SlotEngine` from independent pieces:

* `ReelConfiguration` + `ReelStrip` — grid shape and reel strips. Strips can be
  authored explicitly (`fromSymbols`) or generated from weights
  (`fromWeights`). Explicit strips make a game's mathematics a constant.
* `SymbolSet` — the symbol catalogue (`Symbol::regular/wild/scatter/bonus/blank`).
* `Paytable` — payouts in *units*, not currency.
* A `GridEvaluatorInterface`: `PaylineEvaluator`, `WaysEvaluator`,
  `ClusterEvaluator`, `ScatterEvaluator`, or `CompositeEvaluator` to stack them.
* Features: `FreeSpinsFeature`, `CascadeFeature`, `ComboMultiplierFeature`, or
  your own `FeatureInterface`.

`unitDivisor` is what stops the framework assuming every game is a payline game:
a 5-line game sets it to 5 so a line paying 100 units pays 20x the bet.

Read `SyntheticSlotGame` for a complete example with wild, scatter, free spins,
cascades and a combo multiplier enabled through configuration.

### Card — `CasinoEngine\Frameworks\Card`

`Card`, `Rank`, `Suit`, `Deck` (single or multi-deck, shuffled through the RNG
abstraction), `Hand`, and `BlackjackHandEvaluator` / `BlackjackRules` /
`BlackjackSettlement` for blackjack specifically. A future card game builds on
the same card and deck primitives with its own rules class.

### Roulette — `CasinoEngine\Frameworks\Roulette`

`RouletteVariant` defines the wheel (European, American, or custom), `Bet` and
`BetType` model inside and outside bets, `RouletteEngine::spin()` produces the
winning number, and `RouletteSettlement` evaluates bets and computes payouts.
A different variant is a different `RouletteVariant`, not a code branch.

---

## 11. Definition of done for a new game

* [ ] The class is in a discovered namespace; `games:list` shows it.
* [ ] `metadata()` is deterministic and the id matches the slug pattern.
* [ ] `category()` agrees with the metadata category.
* [ ] `defaultConfiguration()` validates; an unknown config key throws.
* [ ] `hydrateState()` rejects an unknown schema version.
* [ ] `execute()` draws randomness only from `$context->rng()`.
* [ ] `execute()` returns ratios; it never constructs a `Money` payout amount.
* [ ] A module test covers the winning and losing paths.
* [ ] If it has features, a simulation (`sim:run`) produces sane statistics.

---

## 12. What the SDK deliberately does not do

* It does not let a module touch the wallet, the database or the clock directly.
* It does not let a client influence an outcome: the server owns the RNG and the
  state, and the API trusts neither a client balance nor a client result.
* It does not require you to know how the engine stores sessions, settles
  rounds or writes history. Implement the interface, and the engine does the
  rest.
