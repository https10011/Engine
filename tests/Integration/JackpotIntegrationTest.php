<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Integration;

use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\RoundResult;
use CasinoEngine\Persistence\Repository\ProbabilityRepository;
use CasinoEngine\Services\Probability\ProbabilityConfigServiceInterface;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * The administrator's jackpot probability, through the real round pipeline.
 *
 * This is the seam a unit test cannot see: that an override saved through the
 * probability service reaches a live round, that a triggered jackpot is paid
 * *and* recorded in the jackpot history the dashboard reads, and that clearing
 * the override stops the roll on the very next round.
 */
final class JackpotIntegrationTest extends DatabaseTestCase
{
    private const GAME_ID = 'jili.fortune-gems-2';

    private function probabilityService(): ProbabilityConfigServiceInterface
    {
        return $this->engine->container()->get(ProbabilityConfigServiceInterface::class);
    }

    private function openSession(int $userId): string
    {
        $game = $this->engine->registry()->get(self::GAME_ID);

        return $this->engine->sessions()
            ->create($userId, self::GAME_ID, $game->createInitialState($this->engine->contextFor($game)))
            ->id();
    }

    private function freeSpinsRemaining(RoundResult $result): bool
    {
        return (int) ($result->state->toArray()['features']['free_spins']['remaining'] ?? 0) > 0;
    }

    /** @return list<RoundResult> Every paid spin's settled result. */
    private function spins(int $userId, string $sessionId, int $rounds): array
    {
        $results = [];

        for ($i = 0; $i < $rounds; $i++) {
            $result = $this->engine->rounds()->execute(
                $this->engine->sessions()->load($sessionId),
                GameAction::withBet('spin', Money::of(100))
            );
            $results[] = $result;

            // A round that awards free spins advances the session; drain them
            // so every iteration is a fresh paid spin.
            while ($this->freeSpinsRemaining($result)) {
                $result = $this->engine->rounds()->execute(
                    $this->engine->sessions()->load($sessionId),
                    GameAction::create('spin')
                );
            }
        }

        return $results;
    }

    /**
     * The first round carrying a started jackpot transition, or null.
     *
     * @param list<RoundResult> $results
     */
    private function jackpotRound(array $results): ?RoundResult
    {
        foreach ($results as $result) {
            foreach ($result->featureTransitions as $transition) {
                if ($transition->type === 'jackpot') {
                    return $result;
                }
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function jackpotRows(): array
    {
        return $this->engine->container()
            ->get(ProbabilityRepository::class)
            ->jackpotHistory(self::GAME_ID, 100);
    }

    /**
     * A jackpot that triggers must be paid into the wallet, not merely
     * reported. The test pins the seed and drives enough rounds that a 10% roll
     * is certain to fire, then asserts the triggered round itself reports a
     * jackpot, pays far above a line win, and is recorded.
     */
    public function testAConfiguredJackpotIsPaidAndRecorded(): void
    {
        $userId = $this->createUserWithBalance(100000000);

        $this->probabilityService()->saveOverride(
            self::GAME_ID,
            [
                'enabled'             => true,
                'jackpot_probability' => 0.10,
                'jackpot_tiers'       => ['grand' => ['probability' => 1.0, 'payout_multiplier' => 500.0]],
            ],
            null,
            'admin'
        );

        $results = $this->spins($userId, $this->openSession($userId), 300);

        $round = $this->jackpotRound($results);
        self::assertNotNull($round, 'A 10% jackpot roll over 300 seeded rounds must have triggered.');

        $types = array_map(static fn ($t): string => $t->type, $round->featureTransitions);
        self::assertContains('jackpot', $types, 'The triggering round must report a jackpot transition.');

        // The prize is a 500x bet multiple added to the reels' own payout, so
        // the round pays far above the largest ordinary line win.
        self::assertGreaterThan(10000, $round->payout->minorUnits(), 'A jackpot must be paid as a large multiple of the bet.');

        self::assertNotEmpty($this->jackpotRows(), 'A triggered jackpot must be written to the jackpot history.');
    }

    /**
     * Clearing the override must stop the roll immediately. The probability
     * service caches the effective profile, so this also proves the cache is
     * invalidated by a configuration change.
     */
    public function testClearingTheOverrideStopsTheJackpotRoll(): void
    {
        $userId = $this->createUserWithBalance(100000000);

        $this->probabilityService()->saveOverride(
            self::GAME_ID,
            ['enabled' => true, 'jackpot_probability' => 0.10, 'jackpot_tiers' => []],
            null,
            'admin'
        );

        $this->spins($userId, $this->openSession($userId), 200);
        self::assertNotEmpty($this->jackpotRows(), 'The configured jackpot must have triggered.');

        $this->probabilityService()->resetToGlobal(self::GAME_ID, null, 'admin');

        // Clear the history so only post-reset rounds are counted.
        $this->engine->container()
            ->get(\CasinoEngine\Core\Database\ConnectionInterface::class)
            ->pdo()
            ->exec('DELETE FROM jackpot_events');

        $this->spins($userId, $this->openSession($userId), 200);

        self::assertSame(
            [],
            $this->jackpotRows(),
            'Once the override is cleared the game falls back to global settings and stops rolling a jackpot.'
        );
    }

    /** The effective profile must carry the override's jackpot settings. */
    public function testTheEffectiveProfileCarriesTheJackpotConfiguration(): void
    {
        $this->probabilityService()->saveOverride(
            self::GAME_ID,
            [
                'enabled'             => true,
                'jackpot_probability' => 0.001,
                'jackpot_tiers'       => ['grand' => ['probability' => 1.0, 'payout_multiplier' => 1000.0]],
            ],
            null,
            'admin'
        );

        $profile = $this->probabilityService()->effectiveFor(self::GAME_ID);

        self::assertSame(0.001, $profile->jackpotProbability);
        self::assertArrayHasKey('grand', $profile->jackpotTiers);
    }

    /** A game with no jackpot override must resolve a null jackpot probability. */
    public function testAnUnconfiguredGameResolvesNoJackpotProbability(): void
    {
        self::assertNull($this->probabilityService()->effectiveFor(self::GAME_ID)->jackpotProbability);
    }

    /**
     * The jackpot roll must be independent of the win-frequency governor: a
     * jackpot on a round the governor would classify as a loss must still be
     * accepted, so the configured probability is not silently reduced.
     */
    public function testTheGovernorDoesNotDiscardAJackpotRound(): void
    {
        $userId = $this->createUserWithBalance(100000000);

        $this->probabilityService()->saveOverride(
            self::GAME_ID,
            [
                'enabled'             => true,
                'any_win_target'      => 0.01,
                'jackpot_probability' => 0.10,
                'jackpot_tiers'       => ['grand' => ['probability' => 1.0, 'payout_multiplier' => 500.0]],
            ],
            null,
            'admin'
        );

        $this->spins($userId, $this->openSession($userId), 300);

        self::assertNotEmpty($this->jackpotRows(), 'A low any-win target must not discard jackpot rounds.');
    }
}
