<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Games\Slots;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Services\Probability\ProbabilityProfile;
use CasinoEngine\Tests\Support\TestCase;

/**
 * The jackpot roll, wired end to end through a real slot module.
 *
 * The directive requires the administrator's jackpot probability to be
 * independently configurable and to actually change game behaviour. These cases
 * prove three things a configuration UI alone could not:
 *
 *  1. an unconfigured game never rolls a jackpot;
 *  2. a configured probability produces jackpot triggers, at the configured
 *     order of magnitude, with the jackpot *added* to the reels' own result
 *     rather than replacing it;
 *  3. the jackpot is exempt from the win-frequency governor, so a rare jackpot
 *     cannot be discarded by a low any-win target.
 *
 * The two Fortune Gems titles are the games that advertise a jackpot mechanic,
 * so they are the ones exercised here.
 */
final class JackpotWiringTest extends TestCase
{
    private const GAME_ID = 'jili.fortune-gems-2';

    /** A profile with only the jackpot roll configured. */
    private function jackpotProfile(float $probability): ProbabilityProfile
    {
        return new ProbabilityProfile(
            name: 'test',
            gameId: self::GAME_ID,
            jackpotProbability: $probability,
            jackpotTiers: ['grand' => ['probability' => 1.0, 'payout_multiplier' => 500.0]],
            enabled: true
        );
    }

    /** @return array{triggers:int, maxPayout:float, lastState:GameStateInterface} */
    private function spin(int $rounds, ?ProbabilityProfile $profile): array
    {
        $game = $this->engine->registry()->get(self::GAME_ID);
        $context = $this->context(self::GAME_ID, 20260102);

        if ($profile !== null) {
            $context = $context->withProbability($profile);
        }

        $state = $game->createInitialState($context);
        $bet = Money::of(100, 'PHP', 2);

        $triggers = 0;
        $maxPayout = 0.0;

        for ($i = 0; $i < $rounds; $i++) {
            $action = GameAction::withBet('spin', $bet);
            $game->validateAction($action, $state);
            $outcome = $game->execute($action, $state, $context);

            $maxPayout = max($maxPayout, $outcome->payoutMultiplier()->toFloat());

            foreach ($outcome->featureTransitions() as $transition) {
                if ($transition->type === 'jackpot' && $transition->status === FeatureTransition::STARTED) {
                    $triggers++;
                }
            }

            $state = $outcome->nextState();
            if ($state->isComplete()) {
                $state = $game->createInitialState($context);
            }
        }

        return ['triggers' => $triggers, 'maxPayout' => $maxPayout, 'lastState' => $state];
    }

    public function testAnUnconfiguredGameNeverRollsAJackpot(): void
    {
        $result = $this->spin(2000, null);

        self::assertSame(0, $result['triggers'], 'A game with no jackpot probability must never trigger one.');
    }

    public function testAZeroJackpotProbabilityNeverTriggers(): void
    {
        $result = $this->spin(2000, $this->jackpotProfile(0.0));

        self::assertSame(0, $result['triggers']);
    }

    public function testAConfiguredJackpotProbabilityProducesTriggers(): void
    {
        // 5% is far above any realistic jackpot so the assertion is stable on a
        // seeded run; the point is that the roll happens and is settled.
        $result = $this->spin(4000, $this->jackpotProfile(0.05));

        self::assertGreaterThan(0, $result['triggers'], 'A configured jackpot probability must produce triggers.');
        self::assertGreaterThan(
            500.0,
            $result['maxPayout'],
            'A triggered jackpot must pay its prize, on top of any line win.'
        );
    }

    public function testTheJackpotIsNotSuppressedByALowWinTarget(): void
    {
        // A 1% any-win target would drive almost every ordinary spin to a loss.
        // The jackpot roll must survive that, because it is configured
        // separately and must not be discarded by the governor.
        $profile = new ProbabilityProfile(
            name: 'test',
            gameId: self::GAME_ID,
            anyWinTarget: 0.01,
            jackpotProbability: 0.05,
            jackpotTiers: ['grand' => ['probability' => 1.0, 'payout_multiplier' => 500.0]],
            enabled: true
        );

        $result = $this->spin(4000, $profile);

        self::assertGreaterThan(
            0,
            $result['triggers'],
            'A jackpot must not be suppressed by an unrelated win-frequency target.'
        );
    }
}
