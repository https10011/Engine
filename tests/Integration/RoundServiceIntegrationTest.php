<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Integration;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\RoundResult;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * The engine and its shared services against a real database.
 *
 * This is the seam where the pieces have to agree on things a unit test cannot
 * see: that a session round-trips through its table, that a wager leaves the
 * wallet and a payout returns it, and that history records what happened.
 */
final class RoundServiceIntegrationTest extends DatabaseTestCase
{
    public function testSlotRoundDebitsAndCreditsTheWallet(): void
    {
        $userId = $this->createUserWithBalance(10000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');

        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $before = $this->engine->wallet()->balance($userId)->minorUnits();

        $result = $this->engine->rounds()->execute(
            $session,
            GameAction::withBet('spin', Money::of(100))
        );

        $after = $this->engine->wallet()->balance($userId)->minorUnits();
        $expected = $before - 100 + $result->payout->minorUnits();

        self::assertSame($expected, $after, 'The balance must move by exactly the wager and the payout.');
        self::assertSame(100, $result->bet->minorUnits());
        self::assertGreaterThan(0, $result->roundId);
    }

    public function testBalanceIsExactAfterManyRounds(): void
    {
        $userId = $this->createUserWithBalance(1000000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $expected = 1000000;
        $sessions = [$session];

        // Free spins are their own action; the round settles the whole spin
        // group, so the driver below mirrors what the API does.
        for ($i = 0; $i < 200; $i++) {
            $current = $sessions[0];
            $result = $this->engine->rounds()->execute($current, GameAction::withBet('spin', Money::of(100)));
            $expected -= 100;
            $expected += $result->payout->minorUnits();

            // Drain any free spins this round awarded, each with no bet.
            while ($this->freeSpinsRemaining($result)) {
                $result = $this->engine->rounds()->execute(
                    $this->engine->sessions()->load($current->id()),
                    GameAction::create('spin')
                );
                $expected += $result->payout->minorUnits();
            }
        }

        self::assertSame(
            $expected,
            $this->engine->wallet()->balance($userId)->minorUnits(),
            'Two hundred rounds must leave the wallet at exactly the expected balance.'
        );
    }

    private function freeSpinsRemaining(RoundResult $result): bool
    {
        $state = $result->state->toArray();

        return (int) ($state['features']['free_spins']['remaining'] ?? 0) > 0;
    }

    public function testBetBelowMinimumIsRejectedAndWalletUntouched(): void
    {
        $userId = $this->createUserWithBalance(10000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $before = $this->engine->wallet()->balance($userId)->minorUnits();

        try {
            $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(1)));
            self::fail('A bet below the minimum should have been rejected.');
        } catch (\CasinoEngine\Core\Exception\InvalidBetException $e) {
            self::assertSame('INVALID_BET', $e->errorCode());
        }

        self::assertSame($before, $this->engine->wallet()->balance($userId)->minorUnits());
    }

    public function testInsufficientFundsLeavesTheWalletUnchanged(): void
    {
        $userId = $this->createUserWithBalance(50);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        try {
            $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)));
            self::fail('A wager larger than the balance should have been rejected.');
        } catch (\CasinoEngine\Core\Exception\InsufficientFundsException $e) {
            self::assertSame('INSUFFICIENT_FUNDS', $e->errorCode());
        }

        self::assertSame(50, $this->engine->wallet()->balance($userId)->minorUnits());
    }

    public function testSessionStateIsPersistedBetweenActions(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $result = $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)));

        $reloaded = $this->engine->sessions()->loadForUser($session->id(), $userId);

        self::assertSame(
            $result->state->toArray()['spins_played'] ?? null,
            $reloaded->state()->toArray()['spins_played'] ?? null,
            'The stored state must match what the round produced.'
        );
        self::assertSame(1, $reloaded->roundCount());
    }

    public function testHistoryRecordsTheRound(): void
    {
        $userId = $this->createUserWithBalance(10000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)));

        $history = $this->engine->history()->gameHistory($userId, 10);

        self::assertNotEmpty($history, 'A settled round must appear in the history.');
    }

    public function testStatisticsAccumulateWageredAndReturned(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $totalReturned = 0;
        $totalWagered = 0;
        for ($i = 0; $i < 20; $i++) {
            // A fresh session per round keeps the count unambiguous: a free
            // spin is a separate action and would add a second round.
            $fresh = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
                $this->engine->contextFor($game)
            ));
            $result = $this->engine->rounds()->execute($fresh, GameAction::withBet('spin', Money::of(100)));
            $totalReturned += $result->payout->minorUnits();
            $totalWagered += $result->bet->minorUnits();
        }

        $summary = $this->engine->statistics()->forUser($userId);

        self::assertSame($totalWagered, $summary['wagered'] ?? null);
        self::assertSame($totalReturned, $summary['returned'] ?? null);
    }

    public function testCardRoundSettlesMultiActionGameAgainstTheOriginalStake(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $game = $this->engine->registry()->get('synthetic.blackjack');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $balance = 100000;

        $deal = $this->engine->rounds()->execute($session, GameAction::withBet('deal', Money::of(500)));
        $balance -= $deal->bet->minorUnits();
        $balance += $deal->payout->minorUnits();

        // The player decides; hit until 17 or more, then stand.
        $action = $this->playerAction($deal->state->toArray());
        $guard = 0;
        while ($action !== null && $guard++ < 20) {
            $result = $this->engine->rounds()->execute(
                $this->engine->sessions()->load($session->id()),
                GameAction::create($action)
            );
            $balance -= $result->bet->minorUnits();
            $balance += $result->payout->minorUnits();
            $action = $this->playerAction($result->state->toArray());
        }

        self::assertSame(
            $balance,
            $this->engine->wallet()->balance($userId)->minorUnits(),
            'A hit or stand must not wager again; the round settles against the deal stake.'
        );
    }

    /** @param array<string, mixed> $state */
    private function playerAction(array $state): ?string
    {
        if (($state['phase'] ?? null) !== 'player') {
            return null;
        }

        return ((int) ($state['player_total'] ?? 0)) < 17 ? 'hit' : 'stand';
    }

    public function testRouletteRoundSettles(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $game = $this->engine->registry()->get('synthetic.roulette');
        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $before = $this->engine->wallet()->balance($userId)->minorUnits();

        $result = $this->engine->rounds()->execute(
            $session,
            GameAction::withBet('spin', Money::of(200), [
                'bets' => [
                    ['type' => 'red', 'numbers' => $this->redNumbers(), 'stake_minor' => 100],
                    ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 100],
                ],
            ])
        );

        $after = $this->engine->wallet()->balance($userId)->minorUnits();

        self::assertSame($before - 200 + $result->payout->minorUnits(), $after);
    }

    /** @return list<string> */
    private function redNumbers(): array
    {
        return ['1', '3', '5', '7', '9', '12', '14', '16', '18', '19', '21', '23', '25', '27', '30', '32', '34', '36'];
    }

    public function testEngineBootsAndDiscoversTheFixtures(): void
    {
        $ids = $this->engine->registry()->ids();

        self::assertContains('synthetic.slot3x3', $ids);
        self::assertContains('synthetic.blackjack', $ids);
        self::assertContains('synthetic.roulette', $ids);
    }

    /**
     * A pinned seed must vary per round while keeping the whole session
     * reproducible. Before the round number was folded into the seed, every
     * round of a seeded session drew the same RNG stream and therefore the same
     * result — degenerate, and exactly the kind of defect a seeded integration
     * test should catch.
     */
    public function testSeededRoundsVaryAndStayReproducible(): void
    {
        $userId = $this->createUserWithBalance(100000000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');

        $first = $this->seededSessionPayouts($userId, $game->metadata()->id(), 8);

        // A second session under the same seed must replay the same sequence.
        $second = $this->seededSessionPayouts($userId, $game->metadata()->id(), 8);

        self::assertSame(
            $first,
            $second,
            'The same seed must replay the same sequence of round payouts.'
        );
        self::assertGreaterThan(
            1,
            count(array_unique($first)),
            'A seeded session must not hand every round the identical outcome.'
        );
    }

    /**
     * The simulation driver is the second implementation of the settlement
     * arithmetic (it applies multipliers itself so a run never touches a
     * wallet). This asserts the two agree on an identical seeded outcome, which
     * is the whole reason a simulation is allowed to stand in for real play.
     *
     * Roulette is used because a paid round is exactly one action, so the
     * driver's round-to-round stream lines up with the round service's without
     * the free-spin folding that a slot round needs.
     */
    public function testSimulationDriverAgreesWithTheRoundService(): void
    {
        $userId = $this->createUserWithBalance(100000000);
        $game = $this->engine->registry()->get('synthetic.roulette');
        $bets = ['bets' => [
            ['type' => 'red', 'numbers' => $this->redNumbers(), 'stake_minor' => 100],
            ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => 100],
        ]];

        $session = $this->engine->sessions()->create($userId, $game->metadata()->id(), $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        // The first round of a seeded session uses the raw seed.
        $seed = (int) $this->engine->config()->get('engine.rng_seed');
        $serviceResult = $this->engine->rounds()->execute(
            $session,
            GameAction::withBet('spin', Money::of(200), $bets)
        );

        $context = $this->engine->contextFor($game, $seed);
        $driver = new \CasinoEngine\Simulation\GameDriver($game, $context);
        $statistics = new \CasinoEngine\Simulation\SimulationStatistics();
        $state = $game->createInitialState($context);
        $driver->playRound($state, GameAction::withBet('spin', Money::of(200), $bets), $statistics);

        $observed = $statistics->toArray();

        self::assertSame(
            $serviceResult->payout->minorUnits(),
            $observed['total_returned_minor'],
            'The simulation driver must return exactly what the round service pays on the same seed.'
        );
        self::assertSame(
            $serviceResult->bet->minorUnits(),
            $observed['total_wagered_minor'],
            'The simulation driver must wager exactly what the round service debits on the same seed.'
        );
    }

    /**
     * Plays a whole session and returns one payout per paid round, folding any
     * free spins into the round that awarded them, mirroring the API.
     *
     * @return list<int>
     */
    private function seededSessionPayouts(int $userId, string $gameId, int $rounds): array
    {
        $game = $this->engine->registry()->get($gameId);
        $session = $this->engine->sessions()->create($userId, $gameId, $game->createInitialState(
            $this->engine->contextFor($game)
        ));

        $payouts = [];
        for ($i = 0; $i < $rounds; $i++) {
            $result = $this->engine->rounds()->execute(
                $this->engine->sessions()->load($session->id()),
                GameAction::withBet('spin', Money::of(100))
            );
            $total = $result->payout->minorUnits();

            while ($this->freeSpinsRemaining($result)) {
                $result = $this->engine->rounds()->execute(
                    $this->engine->sessions()->load($session->id()),
                    GameAction::create('spin')
                );
                $total += $result->payout->minorUnits();
            }

            $payouts[] = $total;
        }

        return $payouts;
    }
}
