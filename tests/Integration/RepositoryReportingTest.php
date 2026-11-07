<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Integration;

use CasinoEngine\Cli\Application;
use CasinoEngine\Cli\Command\AuditPurgeCommand;
use CasinoEngine\Cli\Command\RateLimitPurgeCommand;
use CasinoEngine\Cli\Command\TokenPurgeCommand;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Persistence\Repository\AuditRepository;
use CasinoEngine\Persistence\Repository\BetRepository;
use CasinoEngine\Persistence\Repository\PayoutRepository;
use CasinoEngine\Persistence\Repository\RateLimitRepository;
use CasinoEngine\Persistence\Repository\RoundRepository;
use CasinoEngine\Persistence\Repository\StatisticsRepository;
use CasinoEngine\Persistence\Repository\TokenRepository;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * Repository read models and the retention commands, against a real database.
 *
 * These paths are not on the hot round-settlement route, so they are easy to
 * leave untested — which is exactly why they get their own test: a reporting
 * query with a typo returns an empty array, not a failure, and would otherwise
 * go unnoticed until someone read the numbers.
 */
final class RepositoryReportingTest extends DatabaseTestCase
{
    public function testRoundAndBetReportingHelpersReturnTheWrittenRows(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create(
            $userId,
            $game->metadata()->id(),
            $game->createInitialState($this->engine->contextFor($game))
        );

        $result = $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)));

        /** @var RoundRepository $rounds */
        $rounds = $this->engine->container()->get(RoundRepository::class);
        /** @var BetRepository $bets */
        $bets = $this->engine->container()->get(BetRepository::class);
        /** @var PayoutRepository $payouts */
        $payouts = $this->engine->container()->get(PayoutRepository::class);

        self::assertSame(2, $rounds->nextRoundNumber($session->id()), 'One round exists, so the next is 2.');
        self::assertNotEmpty($bets->listForRound($result->roundId));
        self::assertSame(100, (int) $bets->listForRound($result->roundId)[0]['amount_minor']);

        if ($result->payout->isPositive()) {
            self::assertNotEmpty($payouts->listForRound($result->roundId));
        }

        $summary = $payouts->summaryForUser($userId);
        self::assertSame($result->payout->isPositive() ? 1 : 0, $summary['count']);
    }

    public function testStatisticsDayLookupReturnsTheAggregatedRow(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');
        $session = $this->engine->sessions()->create(
            $userId,
            $game->metadata()->id(),
            $game->createInitialState($this->engine->contextFor($game))
        );
        $this->engine->rounds()->execute($session, GameAction::withBet('spin', Money::of(100)));

        /** @var StatisticsRepository $statistics */
        $statistics = $this->engine->container()->get(StatisticsRepository::class);
        $day = $this->clock()->now()->format('Y-m-d');

        $row = $statistics->forUserGameDay($userId, 'synthetic.slot3x3', $day);
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['rounds']);
        self::assertSame(100, (int) $row['wagered_minor']);
    }

    public function testUserExistenceAndWalletReferenceChecks(): void
    {
        $userId = $this->createUserWithBalance(1000);

        /** @var UserRepository $users */
        $users = $this->engine->container()->get(UserRepository::class);
        /** @var WalletRepository $wallets */
        $wallets = $this->engine->container()->get(WalletRepository::class);

        self::assertTrue($users->exists($userId));
        self::assertFalse($users->exists($userId + 999999));
        self::assertFalse($wallets->transactionReferenceExists('no-such-reference'));
    }

    public function testAuditLookupByEventAndPurge(): void
    {
        /** @var AuditRepository $audit */
        $audit = $this->engine->container()->get(AuditRepository::class);
        $now = $this->clock()->now()->format('Y-m-d H:i:s');

        $userId = $this->createUserWithBalance(0);
        $audit->record($userId, 'test.event', ['detail' => 'x'], '127.0.0.1', $now);
        self::assertNotEmpty($audit->forEvent('test.event'));
        self::assertNotEmpty($audit->recent(5));
        self::assertSame([], $audit->forEvent('never.recorded'));

        // A cutoff in the future retires every row; one in the past retires none.
        $future = $this->clock()->now()->modify('+1 day')->format('Y-m-d H:i:s');
        self::assertSame(1, $audit->purgeOlderThan($future));
        self::assertSame(0, $audit->purgeOlderThan($now));
    }

    public function testRateLimitPurgeRemovesOnlyOldWindows(): void
    {
        /** @var RateLimitRepository $buckets */
        $buckets = $this->engine->container()->get(RateLimitRepository::class);

        $buckets->hit('old-bucket', '2020-01-01 00:00:00', '2020-01-01 00:00:00');
        $buckets->hit('fresh-bucket', $this->clock()->now()->format('Y-m-d H:i:s'), $this->clock()->now()->format('Y-m-d H:i:s'));

        self::assertSame(1, $buckets->purgeOlderThan('2021-01-01 00:00:00'));

        $remaining = $this->pdo()->query('SELECT bucket FROM rate_limit_buckets')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['fresh-bucket'], $remaining);
    }

    public function testTheRetentionCommandsRunThroughTheCliApplication(): void
    {
        /** @var AuditRepository $audit */
        $audit = $this->engine->container()->get(AuditRepository::class);
        $audit->record($this->createUserWithBalance(0, 'audituser'), 'test.event', [], null, '2000-01-01 00:00:00');

        /** @var RateLimitRepository $buckets */
        $buckets = $this->engine->container()->get(RateLimitRepository::class);
        $buckets->hit('ancient', '2000-01-01 00:00:00', '2000-01-01 00:00:00');

        /** @var TokenRepository $tokens */
        $tokens = $this->engine->container()->get(TokenRepository::class);
        $userId = $this->createUserWithBalance(0, 'tokenuser');
        $tokens->create($userId, hash('sha256', 'expired-token'), '2000-01-01 00:00:00', '1999-01-01 00:00:00');

        $application = new Application($this->engine);
        $application->registerAll([
            new AuditPurgeCommand(),
            new RateLimitPurgeCommand(),
            new TokenPurgeCommand(),
        ]);

        // Success is the zero exit code plus the rows actually being gone, which
        // is what the caller of a cron command depends on.
        self::assertSame(0, $application->run(['console', 'audit:purge']));
        self::assertSame(0, $application->run(['console', 'ratelimit:purge']));
        self::assertSame(0, $application->run(['console', 'token:purge']));

        self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM audit_log')->fetchColumn(), 'A row from 2000 is past any sane retention window.');
        self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM rate_limit_buckets')->fetchColumn());
        self::assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn());
    }

    public function testSessionsListIsScopedAndPaginates(): void
    {
        $userId = $this->createUserWithBalance(100000);
        $game = $this->engine->registry()->get('synthetic.slot3x3');

        for ($i = 0; $i < 3; $i++) {
            $this->engine->sessions()->create(
                $userId,
                $game->metadata()->id(),
                $game->createInitialState($this->engine->contextFor($game))
            );
        }

        $sessions = $this->engine->sessions()->listForUser($userId, 2);
        self::assertCount(2, $sessions, 'The limit must be honoured, not ignored.');
    }

    private function pdo(): \PDO
    {
        return $this->engine->container()->get(ConnectionInterface::class)->pdo();
    }
}
