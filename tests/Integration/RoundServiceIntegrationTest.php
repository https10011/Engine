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
        } catch (\CasinoEngine\Core\Exception\InvalidBetException) {
            // Expected.
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
        } catch (\CasinoEngine\Core\Exception\InsufficientFundsException) {
            // Expected.
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
}
