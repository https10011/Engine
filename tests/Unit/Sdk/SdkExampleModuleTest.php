<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Unit\Sdk;

use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Games\CoinFlip\CoinFlipConfiguration;
use CasinoEngine\Games\CoinFlip\CoinFlipGame;
use CasinoEngine\Games\CoinFlip\CoinFlipState;
use CasinoEngine\Tests\Support\TestCase;

/**
 * The worked example from GAME_SDK.md, exercised against the real engine.
 *
 * The SDK document presents this module as a complete example. If it were only
 * a snippet in prose it could drift from the engine; because it is a real
 * registered module driven here, the document cannot claim anything the code
 * does not do.
 */
final class SdkExampleModuleTest extends TestCase
{
    private function game(): CoinFlipGame
    {
        return new CoinFlipGame();
    }

    public function testTheExampleModuleIsDiscoveredByTheEngine(): void
    {
        self::assertContains('coinflip.heads-tails', $this->registeredGames());
    }

    public function testMetadataAndCategoryAgreeSoRegistrationSucceeds(): void
    {
        $game = $this->game();

        self::assertSame($game->category(), $game->metadata()->category());
        self::assertSame(1, preg_match('/^[a-z0-9][a-z0-9._-]{1,63}$/', $game->metadata()->id()));
    }

    public function testAnUnknownConfigurationKeyIsRejectedAtConstruction(): void
    {
        $this->expectException(ConfigurationException::class);

        CoinFlipConfiguration::fromArray(['not_a_real_key' => true]);
    }

    public function testStateRejectsAnUnknownSchemaVersionRatherThanMisreadingIt(): void
    {
        $this->expectException(ValidationException::class);

        CoinFlipState::fromArray(['schema' => 999, 'flips_played' => 5]);
    }

    public function testStateRoundTrips(): void
    {
        $state = new CoinFlipState(7);

        self::assertSame($state->toArray(), CoinFlipState::fromArray($state->toArray())->toArray());
    }

    public function testAnUnknownActionIsRejected(): void
    {
        $game = $this->game();

        $this->expectException(GameRuleException::class);

        $game->validateAction(GameAction::create('roll'), new CoinFlipState());
    }

    public function testAFlipWithoutABetIsRejected(): void
    {
        $game = $this->game();

        $this->expectException(GameRuleException::class);

        $game->validateAction(GameAction::create('flip'), new CoinFlipState());
    }

    public function testTheOutcomeUsesTheInjectedRngAndPaysEitherTwiceOrNothing(): void
    {
        $game = $this->game();
        $action = GameAction::withBet('flip', Money::of(100));

        // Ten seeds: the payout multiplier must always be exactly 0 or 2, never
        // an amount derived from a client value, and the RNG must be the one
        // the engine supplied.
        for ($seed = 0; $seed < 10; $seed++) {
            $context = $this->context('coinflip.heads-tails', $seed);
            $outcome = $game->execute($action, CoinFlipState::initial(), $context);

            $payout = $outcome->payoutMultiplier()->toFloat();
            self::assertContains($payout, [0.0, 2.0]);
            self::assertSame(1, $outcome->wagerMultiplier()->numerator());
        }
    }

    public function testTheStateCarriesTheFlipCountForward(): void
    {
        $game = $this->game();
        $context = $this->context('coinflip.heads-tails');
        $action = GameAction::withBet('flip', Money::of(100));

        $outcome = $game->execute($action, new CoinFlipState(4), $context);
        $next = $outcome->nextState();

        self::assertInstanceOf(CoinFlipState::class, $next);
        self::assertSame(5, $next->flipsPlayed());
    }
}
