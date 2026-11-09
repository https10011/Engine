<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Integration;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Admin\AdminService;
use CasinoEngine\Services\Admin\GameControlService;
use CasinoEngine\Tests\Support\DatabaseTestCase;

/**
 * Operator read models and audited mutations.
 *
 * The dashboard is easy to leave untested because an empty result still renders;
 * these assertions check that the numbers come from the rows the operator just
 * looked at, and that an adjustment writes both the ledger and the audit trail.
 */
final class AdminServiceTest extends DatabaseTestCase
{
    private AdminService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->engine->container();
        /** @var UserRepository $users */
        $users = $container->get(UserRepository::class);
        /** @var WalletRepository $wallets */
        $wallets = $container->get(WalletRepository::class);
        /** @var ConnectionInterface $connection */
        $connection = $container->get(ConnectionInterface::class);
        /** @var GameControlService $controls */
        $controls = $container->get(GameControlService::class);

        $this->service = new AdminService(
            $connection,
            $users,
            $wallets,
            $container->get(GameRegistryInterface::class),
            $controls,
            $container->get(ClockInterface::class)
        );
    }

    public function testDashboardCountsRegisteredPlayersAndCreditsInCirculation(): void
    {
        $first = $this->createPlayer('09171110001', 30000, 'Ana');
        $this->createPlayer('09171110002', 50000, 'Ben');

        $dashboard = $this->service->dashboard();

        self::assertSame(2, $dashboard['total_users']);
        self::assertSame(2, $dashboard['active_users']);
        self::assertSame(80000, $dashboard['credits_minor']);
        self::assertSame(2, $dashboard['wallets']);
        self::assertSame($first, (int) $this->service->users('Ana')[0]['id']);
        self::assertGreaterThanOrEqual(1, $dashboard['games']);
    }

    public function testCreditAdjustmentUpdatesBalanceWritesLedgerAndAuditTrail(): void
    {
        $playerId = $this->createPlayer('09171110003', 30000, 'Cara');
        $admin = ['id' => 99, 'username' => 'admin'];

        $newBalance = $this->service->adjustCredits($playerId, 50000, 'Welcome bonus', $admin);

        self::assertSame(80000, $newBalance);
        self::assertSame(80000, (int) $this->service->user($playerId)['balance_minor']);

        $audit = $this->service->auditLog();
        self::assertSame('admin.credits.adjusted', $audit[0]['event']);
        self::assertStringContainsString('Welcome bonus', (string) $audit[0]['context']);

        $ledger = $this->service->user($playerId)['transactions'];
        self::assertSame('credit', $ledger[0]['type']);
        self::assertSame('admin_adjustment', $ledger[0]['reason']);
        self::assertSame(50000, (int) $ledger[0]['amount_minor']);
    }

    public function testAdjustmentCannotOverdrawAPlayer(): void
    {
        $playerId = $this->createPlayer('09171110004', 30000, 'Dana');

        $this->expectException(\CasinoEngine\Core\Exception\ValidationException::class);
        $this->service->adjustCredits($playerId, -40000, 'Too much', ['id' => 99, 'username' => 'admin']);
    }

    public function testSuspendingAndReactivatingAPlayerIsAudited(): void
    {
        $playerId = $this->createPlayer('09171110005', 30000, 'Eli');
        $admin = ['id' => 99, 'username' => 'admin'];

        $this->service->setStatus($playerId, 'suspended', $admin);
        self::assertSame('suspended', $this->service->user($playerId)['status']);

        $this->service->setStatus($playerId, 'active', $admin);
        self::assertSame('active', $this->service->user($playerId)['status']);

        $events = array_column($this->service->auditLog(), 'event');
        self::assertContains('admin.account.status', $events);
    }

    public function testSearchFindsAPlayerByPhoneDisplayNameOrId(): void
    {
        $playerId = $this->createPlayer('09171110006', 30000, 'Faye');

        self::assertCount(1, $this->service->users('09171110006'));
        self::assertCount(1, $this->service->users('Faye'));
        self::assertCount(1, $this->service->users((string) $playerId));
    }

    public function testAvailabilityAndFriendlyPlayArePersistedByTheControlService(): void
    {
        $controls = $this->engine->container()->get(GameControlService::class);
        $gameId = 'jili.super-ace';

        self::assertTrue($controls->isAvailable($gameId));

        $controls->save($gameId, false, true, 99);

        self::assertFalse($controls->isAvailable($gameId));
        self::assertTrue($controls->isFriendlyPlay($gameId));

        $controls->save($gameId, true, false, 99);

        self::assertTrue($controls->isAvailable($gameId));
        self::assertFalse($controls->isFriendlyPlay($gameId));
    }

    private function createPlayer(string $phone, int $balanceMinor, string $displayName): int
    {
        $userId = $this->createUserWithBalance($balanceMinor, 'p_' . substr($phone, -6) . '_' . strtolower($displayName));

        $pdo = $this->engine->container()->get(ConnectionInterface::class)->pdo();
        $statement = $pdo->prepare('UPDATE users SET phone = ?, display_name = ?, age = 25 WHERE id = ?');
        $statement->execute(['+63' . substr($phone, 1), $displayName, $userId]);

        return $userId;
    }
}
