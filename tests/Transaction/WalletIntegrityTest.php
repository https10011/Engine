<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Transaction;

use CasinoEngine\Core\Exception\IdempotencyConflictException;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\RoundResult;
use CasinoEngine\Tests\Support\DatabaseTestCase;
use CasinoEngine\Tests\Support\FrozenClock;

/**
 * Wallet and round integrity under failure, retry and concurrency.
 *
 * These are the tests that matter most: a game that pays the wrong amount is a
 * bug, but a wallet that loses or invents credits under a retry is a
 * correctness failure the whole engine exists to prevent.
 */
final class WalletIntegrityTest extends DatabaseTestCase
{
    private function openSession(int $userId, string $gameId = 'synthetic.slot3x3'): \CasinoEngine\Core\Contract\GameSessionInterface
    {
        $game = $this->engine->registry()->get($gameId);

        return $this->engine->sessions()->create($userId, $gameId, $game->createInitialState(
            $this->engine->contextFor($game)
        ));
    }

    public function testDuplicateIdempotencyKeyReturnsTheOriginalResult(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $session = $this->openSession($userId);
        $action = GameAction::withBet('spin', Money::of(100));

        $first = $this->engine->rounds()->execute($session, $action, 'key-1');
        $balanceAfterFirst = $this->engine->wallet()->balance($userId)->minorUnits();

        $second = $this->engine->rounds()->execute($session, $action, 'key-1');

        self::assertSame($first->roundId, $second->roundId, 'A replay returns the original round.');
        self::assertTrue($second->replayed);
        self::assertSame(
            $balanceAfterFirst,
            $this->engine->wallet()->balance($userId)->minorUnits(),
            'A replayed request must not wager a second time.'
        );
    }

    public function testSameKeyWithDifferentPayloadIsRejected(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $session = $this->openSession($userId);

        $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)), 'key-2');

        $this->expectException(IdempotencyConflictException::class);

        $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(500)), 'key-2');
    }

    public function testTenRetriesOfTheSameKeyWagerExactlyOnce(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $session = $this->openSession($userId);
        $action = GameAction::withBet('spin', Money::of(100));

        $first = $this->engine->rounds()->execute($session, $action, 'retry-key');
        $expected = $first->payout->minorUnits();

        for ($i = 0; $i < 10; $i++) {
            $replay = $this->engine->rounds()->execute($session, $action, 'retry-key');
            self::assertTrue($replay->replayed);
            self::assertSame($first->roundId, $replay->roundId);
        }

        self::assertSame(
            100000 - 100 + $expected,
            $this->engine->wallet()->balance($userId)->minorUnits(),
            'Eleven calls with one key must debit exactly one wager.'
        );
    }

    public function testFailedRoundReleasesItsKeyForRetry(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $session = $this->openSession($userId);

        // An invalid action fails inside the transaction.
        try {
            $this->engine->rounds()->execute($session, GameAction::create('cash_out'), 'fail-key');
            self::fail('The invalid action should have failed.');
        } catch (\CasinoEngine\Core\Exception\GameRuleException $e) {
            self::assertStringContainsString('cash_out', $e->getMessage());
        }

        // The same key must be reusable after the failure.
        $result = $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)), 'fail-key');

        self::assertFalse($result->replayed);
        self::assertGreaterThan(0, $result->roundId);
    }

    public function testARejectedRoundWritesNothing(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $session = $this->openSession($userId);

        $pdo = $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class)->pdo();
        $roundsBefore = (int) $pdo->query('SELECT COUNT(*) FROM game_rounds')->fetchColumn();
        $transactionsBefore = (int) $pdo->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn();

        try {
            $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(999999)));
            self::fail('A bet above the maximum should have been rejected.');
        } catch (\CasinoEngine\Core\Exception\InvalidBetException $e) {
            self::assertSame('INVALID_BET', $e->errorCode());
        }

        self::assertSame($roundsBefore, (int) $pdo->query('SELECT COUNT(*) FROM game_rounds')->fetchColumn());
        self::assertSame(
            $transactionsBefore,
            (int) $pdo->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn(),
            'A rejected round must not leave a transaction behind.'
        );
    }

    public function testTwoRoundsCannotDriveTheWalletNegative(): void
    {
        // The wallet holds exactly one bet. Whether the first round wins is up
        // to the RNG, so this asserts the invariant that must hold either way
        // rather than a fixed number of successes.
        $userId = $this->createUserWithBalance(100);

        foreach ([$this->openSession($userId), $this->openSession($userId)] as $session) {
            try {
                $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)));
            } catch (\CasinoEngine\Core\Exception\InsufficientFundsException $e) {
                // Expected whenever the balance has already been spent.
                self::assertSame('INSUFFICIENT_FUNDS', $e->errorCode());
            }
        }

        self::assertGreaterThanOrEqual(
            0,
            $this->engine->wallet()->balance($userId)->minorUnits(),
            'The wallet must never be driven negative.'
        );
    }

    /**
     * The wallet row lock under real concurrency.
     *
     * Eight forked processes each try to debit the whole balance inside their
     * own transaction. The `SELECT ... FOR UPDATE` lock must serialise them so
     * exactly one succeeds; a lock that is missing or ineffective shows up
     * here as several successes and a negative balance.
     */
    public function testConcurrentDebitsCannotOverspendTheWallet(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the concurrency test.');
        }

        $userId = $this->createUserWithBalance(100);
        $workers = 8;
        $pids = [];

        for ($i = 0; $i < $workers; $i++) {
            $pid = \pcntl_fork();
            if ($pid === -1) {
                self::markTestSkipped('Could not fork a worker process.');
            }

            if ($pid === 0) {
                // Child: a fresh engine and its own connection, so nothing is
                // shared with the parent or the siblings.
                $code = 2;
                try {
                    $engine = \CasinoEngine\Core\Bootstrap::testEngine();
                    $engine->container()->instance(
                        \CasinoEngine\Core\Contract\Service\ClockInterface::class,
                        new FrozenClock()
                    );
                    $engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class)->pdo();

                    $engine->container()->get(\CasinoEngine\Services\Transaction\TransactionManagerInterface::class)
                        ->transactional(function () use ($engine, $userId): void {
                            $engine->wallet()->debit($userId, Money::of(100), 'wager');
                        });

                    $code = 0;
                } catch (\CasinoEngine\Core\Exception\InsufficientFundsException) {
                    $code = 1;
                } catch (\Throwable) {
                    $code = 2;
                }

                exit($code);
            }

            $pids[] = $pid;
        }

        $succeeded = 0;
        $insufficient = 0;
        foreach ($pids as $pid) {
            \pcntl_waitpid($pid, $status);
            match (\pcntl_wexitstatus($status)) {
                0 => $succeeded++,
                1 => $insufficient++,
                default => null,
            };
        }

        self::assertSame(1, $succeeded, 'The row lock must let exactly one debit through.');
        self::assertSame($workers - 1, $insufficient);

        // The forked children shared this process's socket and closed it on
        // exit, so the parent reads the balance through a fresh connection.
        $fresh = \CasinoEngine\Core\Bootstrap::testEngine();
        self::assertSame(0, $fresh->wallet()->balance($userId)->minorUnits());
    }

    public function testWalletTransactionLogBalancesAgainstTheBalance(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $session = $this->openSession($userId);

        for ($i = 0; $i < 25; $i++) {
            $result = $this->engine->rounds()->execute(
                $this->engine->sessions()->load($session->id()),
                GameAction::withBet('spin', Money::of(100))
            );
            while ($this->freeSpinsRemaining($result)) {
                $result = $this->engine->rounds()->execute(
                    $this->engine->sessions()->load($session->id()),
                    GameAction::create('spin')
                );
            }
        }

        $pdo = $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class)->pdo();

        $sum = (int) $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN amount_minor ELSE -amount_minor END), 0)
             FROM wallet_transactions WHERE user_id = $userId"
        )->fetchColumn();

        self::assertSame(
            100000 + $sum,
            $this->engine->wallet()->balance($userId)->minorUnits(),
            'The balance must equal the opening balance plus every logged transaction.'
        );
    }

    public function testConcurrentSessionsOnOneWalletKeepTheLedgerConsistent(): void
    {
        $userId = $this->createUserWithBalance(1000000);
        $pdo = $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class)->pdo();

        // Interleave ten rounds across five sessions, reloading each session so
        // the round service re-reads the locked state as a real caller would.
        $sessions = [];
        for ($i = 0; $i < 5; $i++) {
            $sessions[] = $this->openSession($userId);
        }

        for ($round = 0; $round < 10; $round++) {
            $session = $sessions[$round % 5];
            $result = $this->engine->rounds()->execute(
                $this->engine->sessions()->load($session->id()),
                GameAction::withBet('spin', Money::of(100))
            );

            // Drain any free spins this round awarded, on the same session.
            while ($this->freeSpinsRemaining($result)) {
                $result = $this->engine->rounds()->execute(
                    $this->engine->sessions()->load($session->id()),
                    GameAction::create('spin')
                );
            }
        }

        $sum = (int) $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN amount_minor ELSE -amount_minor END), 0)
             FROM wallet_transactions WHERE user_id = $userId"
        )->fetchColumn();

        self::assertSame(1000000 + $sum, $this->engine->wallet()->balance($userId)->minorUnits());
    }

    private function freeSpinsRemaining(RoundResult $result): bool
    {
        $state = $result->state->toArray();

        return (int) ($state['features']['free_spins']['remaining'] ?? 0) > 0;
    }

    public function testPayoutCannotExceedTheGameMaximumWin(): void
    {
        // The slot fixture caps a win at 200x the stake. A bet of 100 can never
        // return more than 20000, whatever the grid does.
        $userId = $this->createUserWithBalance(1000000);
        $session = $this->openSession($userId);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $cap = 100 * $game->metadata()->maxWinMultiplier();

        for ($i = 0; $i < 300; $i++) {
            $result = $this->engine->rounds()->execute(
                $this->engine->sessions()->load($session->id()),
                GameAction::withBet('spin', Money::of(100))
            );

            self::assertLessThanOrEqual(
                $cap,
                $result->payout->minorUnits(),
                'A payout above the advertised maximum win must be refused, not paid.'
            );

            // Drain any free spins, each a no-bet action, so the next paid
            // spin starts from a clean state.
            while ($this->freeSpinsRemaining($result)) {
                $result = $this->engine->rounds()->execute(
                    $this->engine->sessions()->load($session->id()),
                    GameAction::create('spin')
                );
                self::assertLessThanOrEqual($cap, $result->payout->minorUnits());
            }
        }
    }
}
