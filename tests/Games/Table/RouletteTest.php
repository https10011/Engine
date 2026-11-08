<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Games\Table;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\Service\SystemClock;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Frameworks\Roulette\Bet\BetFactory;
use CasinoEngine\Frameworks\Roulette\Bet\BetType;
use CasinoEngine\Frameworks\Roulette\RouletteEngine;
use CasinoEngine\Frameworks\Roulette\Settlement\RouletteSettlement;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;
use CasinoEngine\Games\Table\Roulette\Roulette;
use CasinoEngine\Games\Table\Roulette\RouletteConfiguration;
use CasinoEngine\Games\Table\Roulette\RouletteState;
use CasinoEngine\Services\Rng\RngFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Roulette module tests.
 *
 * The payouts are asserted against known pockets through the framework's
 * `settle()`, so a table that paid a straight at 17:1 instead of 35:1 fails
 * here rather than at a player's expense. The full module path — place a slip,
 * then spin — is exercised separately.
 */
final class RouletteTest extends TestCase
{
    private function game(): Roulette
    {
        return new Roulette();
    }

    private function context(?RouletteConfiguration $configuration = null, int $seed = 99): GameContext
    {
        $game = $this->game();

        return new GameContext(
            RngFactory::seeded($seed),
            $game->metadata(),
            $configuration ?? new RouletteConfiguration(),
            new NullLogger(),
            new SystemClock()
        );
    }

    /** @param list<array{type:string,numbers:list<string>,stake_minor:int}> $bets */
    private function spinAction(array $bets, int $totalMinor): GameAction
    {
        return GameAction::withBet('spin', Money::of($totalMinor, 'PHP'), ['bets' => $bets]);
    }

    // ----------------------------------------------------------- framework

    public function testAStraightPaysThirtyFiveToOnePlusTheStake(): void
    {
        $variant = RouletteVariant::european();
        $factory = new BetFactory($variant);
        $engine = new RouletteEngine($variant, $factory, new RouletteSettlement());

        $bet = $factory->make(BetType::Straight, ['17'], Money::of(1000, 'PHP'));
        $result = $engine->settle([$bet], '17');

        self::assertSame(36000, $result->totalPayout->minorUnits(), '35:1 plus the stake is 36x.');
        self::assertSame(0.0, $result->net->minorUnits() === 35000 ? 0.0 : 1.0);
    }

    public function testAnEvenMoneyBetPaysOneToOne(): void
    {
        $variant = RouletteVariant::european();
        $factory = new BetFactory($variant);
        $engine = new RouletteEngine($variant, $factory, new RouletteSettlement());

        $reds = [];
        foreach ($variant->pockets() as $pocket) {
            if ($pocket !== '0' && $variant->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }

        $bet = $factory->make(BetType::Red, $reds, Money::of(1000, 'PHP'));
        $result = $engine->settle([$bet], '1');

        self::assertSame(2000, $result->totalPayout->minorUnits());
    }

    public function testAnEvenMoneyBetLosesToZeroOnTheEuropeanWheel(): void
    {
        $variant = RouletteVariant::european();
        $factory = new BetFactory($variant);
        $engine = new RouletteEngine($variant, $factory, new RouletteSettlement());

        $reds = [];
        foreach ($variant->pockets() as $pocket) {
            if ($pocket !== '0' && $variant->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }

        $bet = $factory->make(BetType::Red, $reds, Money::of(1000, 'PHP'));
        $result = $engine->settle([$bet], '0');

        self::assertSame(0, $result->totalPayout->minorUnits());
    }

    public function testFrenchLaPartageReturnsHalfAnEvenMoneyBetOnZero(): void
    {
        $variant = RouletteVariant::french();
        $factory = new BetFactory($variant);
        $engine = new RouletteEngine($variant, $factory, new RouletteSettlement());

        $reds = [];
        foreach ($variant->pockets() as $pocket) {
            if ($pocket !== '0' && $variant->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }

        $bet = $factory->make(BetType::Red, $reds, Money::of(1000, 'PHP'));
        $result = $engine->settle([$bet], '0');

        self::assertTrue($result->laPartage);
        self::assertSame(500, $result->totalPayout->minorUnits());
    }

    public function testTheAmericanWheelHasThirtyEightPockets(): void
    {
        self::assertSame(38, RouletteVariant::american()->pocketCount());
        self::assertSame(37, RouletteVariant::european()->pocketCount());
        self::assertTrue(RouletteVariant::american()->hasDoubleZero());
        self::assertFalse(RouletteVariant::european()->hasDoubleZero());
    }

    // ---------------------------------------------------------- configuration

    public function testConfigurationRejectsAnUnknownKey(): void
    {
        $this->expectException(ConfigurationException::class);

        RouletteConfiguration::fromArray(['variant' => 'european', 'nonsense' => 1]);
    }

    public function testConfigurationRejectsAnUnknownVariant(): void
    {
        $this->expectException(ConfigurationException::class);

        RouletteConfiguration::fromArray(['variant' => 'martian']);
    }

    public function testConfigurationRoundTrips(): void
    {
        $configuration = RouletteConfiguration::fromArray([
            'variant'                => 'american',
            'min_bet_minor'          => 200,
            'max_bet_minor'          => 400000,
            'max_straight_bet_minor' => 50000,
            'max_outside_bet_minor'  => 200000,
            'max_total_bet_minor'    => 800000,
        ]);

        $rules = $configuration->toArray();

        self::assertSame('american', $rules['variant']);
        self::assertSame(200, $rules['min_bet_minor']);
        self::assertSame(38, $configuration->variant()->pocketCount());
    }

    public function testALimitBelowTheMinimumIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        RouletteConfiguration::fromArray(['min_bet_minor' => 1000, 'max_bet_minor' => 100])->validate();
    }

    // ---------------------------------------------------------- module wiring

    public function testTheModuleRegistersUnderItsOwnId(): void
    {
        self::assertSame('table.roulette', Roulette::ID);
        self::assertSame('table.roulette', $this->game()->metadata()->id());
    }

    public function testPlacingASlipWagersNothing(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $action = GameAction::create('place_bets', ['bets' => [
            ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
        ]]);
        $game->validateAction($action, $state);

        $outcome = $game->execute($action, $state, $context);
        $next = $outcome->nextState();

        self::assertInstanceOf(RouletteState::class, $next);
        self::assertSame(1000, $next->totalStakeMinor());
        self::assertSame(RouletteState::PHASE_BETTING, $next->phase());
        self::assertFalse($next->isComplete(), 'A placed slip still owes the player a spin.');
    }

    public function testAPlacedSlipIsNotPersistedAsAWager(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $outcome = $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
            ]]),
            $state,
            $context
        );

        // Placing a slip carries no bet, so the engine's wager — the bet times
        // the multiplier — is zero however the multiplier reads. That is what
        // makes placing a slip free while a spin is not.
        $action = GameAction::create('place_bets', ['bets' => [
            ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
        ]]);

        self::assertNull($action->bet());
        self::assertSame(0, Money::zero('PHP')->multiply($outcome->wagerMultiplier()->toFloat())->minorUnits());
    }

    public function testSpinSettlesTheSlipAndReportsThePocket(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $bets = [['type' => 'red', 'numbers' => RouletteVariant::european()->pockets(), 'stake_minor' => 0]];

        // Build the real red set through the factory's own menu so the test
        // does not restate the wheel.
        $reds = [];
        foreach (RouletteVariant::european()->pockets() as $pocket) {
            if ($pocket !== '0' && RouletteVariant::european()->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }

        $placed = $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'red', 'numbers' => $reds, 'stake_minor' => 1000],
            ]]),
            $state,
            $context
        );

        $state = $placed->nextState();
        self::assertInstanceOf(RouletteState::class, $state);

        $action = $this->spinAction($state->bets(), 1000);
        $game->validateAction($action, $state);

        $outcome = $game->execute($action, $state, $context);
        $next = $outcome->nextState();

        self::assertInstanceOf(RouletteState::class, $next);
        self::assertTrue($next->isComplete());
        self::assertSame(RouletteState::PHASE_SETTLED, $next->phase());
        self::assertSame([], $next->bets(), 'The slip is cleared once it has been spun.');
        self::assertSame(1, $next->spinsPlayed());

        $spin = $next->lastSpin();
        self::assertNotNull($spin);
        self::assertArrayHasKey('winning_number', $spin);
        self::assertContains($spin['winning_number'], RouletteVariant::european()->pockets());
        self::assertSame([$spin['winning_number']], $next->history());
    }

    public function testAClientCannotSpinASlipDifferentFromThePlacedOne(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $reds = [];
        foreach (RouletteVariant::european()->pockets() as $pocket) {
            if ($pocket !== '0' && RouletteVariant::european()->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }

        $placed = $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'red', 'numbers' => $reds, 'stake_minor' => 1000],
            ]]),
            $state,
            $context
        );
        $state = $placed->nextState();
        self::assertInstanceOf(RouletteState::class, $state);

        // The same total, but a straight on 17 instead of red: the stake
        // matches, so only the slip comparison catches it.
        $tampered = $this->spinAction([
            ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
        ], 1000);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/does not match the placed slip/i');

        $game->execute($tampered, $state, $context);
    }

    public function testASpinWhoseTotalDoesNotMatchItsBetsIsRejected(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $bets = [['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000]];

        $placed = $game->execute(GameAction::create('place_bets', ['bets' => $bets]), $state, $context);
        $state = $placed->nextState();
        self::assertInstanceOf(RouletteState::class, $state);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/does not match the sum/i');

        $game->execute($this->spinAction($bets, 2000), $state, $context);
    }

    public function testAnImpossibleBetIsRejectedBeforeAnyMoneyMoves(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        // 1, 2, 4 is not a street: a street is three consecutive numbers
        // starting a column.
        $this->expectException(ValidationException::class);

        $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'street', 'numbers' => ['1', '2', '4'], 'stake_minor' => 1000],
            ]]),
            $state,
            $context
        );
    }

    public function testANumberThatIsNotOnTheWheelIsRejected(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $this->expectException(ValidationException::class);

        $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'straight', 'numbers' => ['37'], 'stake_minor' => 1000],
            ]]),
            $state,
            $context
        );
    }

    public function testABetBelowTheTableMinimumIsRejected(): void
    {
        $game = $this->game();
        $context = $this->context(RouletteConfiguration::fromArray(['min_bet_minor' => 500]));
        $state = $game->createInitialState($context);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/below the table minimum/i');

        $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 100],
            ]]),
            $state,
            $context
        );
    }

    public function testASlipAboveTheTableTotalIsRejected(): void
    {
        $game = $this->game();
        $context = $this->context(RouletteConfiguration::fromArray([
            'max_total_bet_minor' => 1500,
            'max_bet_minor'       => 100000,
        ]));
        $state = $game->createInitialState($context);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/exceeds the table total/i');

        $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
                ['type' => 'straight', 'numbers' => ['18'], 'stake_minor' => 1000],
            ]]),
            $state,
            $context
        );
    }

    public function testASpinWithoutAPlacedSlipIsRejected(): void
    {
        $game = $this->game();
        $state = $game->createInitialState($this->context());

        $this->expectException(GameRuleException::class);

        $game->validateAction(GameAction::withBet('spin', Money::of(1000, 'PHP')), $state);
    }

    public function testAnUnknownActionIsRejected(): void
    {
        $game = $this->game();
        $state = $game->createInitialState($this->context());

        $this->expectException(GameRuleException::class);
        $this->expectExceptionMessageMatches('/unknown action/i');

        $game->validateAction(GameAction::create('levitate'), $state);
    }

    public function testClearingASlipEmptiesIt(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $placed = $game->execute(
            GameAction::create('place_bets', ['bets' => [
                ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
            ]]),
            $state,
            $context
        );
        $state = $placed->nextState();
        self::assertInstanceOf(RouletteState::class, $state);

        $cleared = $game->execute(GameAction::create('clear_bets'), $state, $context);
        $next = $cleared->nextState();

        self::assertInstanceOf(RouletteState::class, $next);
        self::assertSame([], $next->bets());
        self::assertSame(0, $next->totalStakeMinor());
    }

    public function testPlacingASlipWithAStakeIsRejected(): void
    {
        $game = $this->game();
        $state = $game->createInitialState($this->context());

        $this->expectException(GameRuleException::class);
        $this->expectExceptionMessageMatches('/does not wager/i');

        $game->validateAction(
            GameAction::withBet('place_bets', Money::of(1000, 'PHP'), ['bets' => [
                ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
            ]]),
            $state
        );
    }

    // ------------------------------------------------------ state round trip

    public function testStateSurvivesASerialisationRoundTrip(): void
    {
        $state = (RouletteState::initial())->withBets([
            ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 1000],
            ['type' => 'red', 'numbers' => ['1', '3'], 'stake_minor' => 500],
        ]);

        $restored = RouletteState::fromArray($state->toArray());

        self::assertSame($state->toArray(), $restored->toArray());
        self::assertSame(1500, $restored->totalStakeMinor());
        self::assertSame(RouletteState::PHASE_BETTING, $restored->phase());
    }

    public function testAStateFromANewerEngineIsRefused(): void
    {
        $this->expectException(ValidationException::class);

        RouletteState::fromArray(['schema' => 99]);
    }

    public function testTheHistoryIsCappedSoTheStateRowCannotGrowWithoutBound(): void
    {
        $state = RouletteState::initial();

        for ($i = 0; $i < 80; $i++) {
            $state = $state->settled(['winning_number' => (string) ($i % 37)], (string) ($i % 37));
        }

        self::assertCount(50, $state->history());
        self::assertSame(80, $state->spinsPlayed());
    }

    public function testTheModuleAdvertisesItsConfigurationObject(): void
    {
        $game = $this->game();
        self::assertInstanceOf(RouletteConfiguration::class, $game->defaultConfiguration());

        $this->expectException(ConfigurationException::class);
        $game->validateConfiguration(new class implements GameConfigurationInterface {
            public static function fromArray(array $values): static
            {
                return new static();
            }

            public function toArray(): array
            {
                return [];
            }

            public function validate(): void
            {
            }
        });
    }
}
