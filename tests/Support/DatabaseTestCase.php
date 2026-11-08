<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Support;

use CasinoEngine\Core\Bootstrap;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\EngineException;
use CasinoEngine\Persistence\Migration\MigrationRunner;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Throwable;

/**
 * Base class for tests that need the real database.
 *
 * These tests are skipped — not failed — when no database is reachable, so the
 * unit and framework suites stay runnable on a machine without MySQL. The skip
 * message says exactly what was missing, which keeps the record honest.
 *
 * The test database is a separate schema (`casino_engine_test` by default). The
 * suite truncates the tables it uses between tests so a test never inherits
 * another's rows.
 */
abstract class DatabaseTestCase extends BaseTestCase
{
    protected Engine $engine;

    /** @var list<string> Tables cleared before each test. */
    protected array $truncateTables = [
        'wallet_transactions',
        'payouts',
        'bets',
        'statistics_daily',
        'game_rounds',
        'idempotency_keys',
        'game_sessions',
        'wallets',
        'user_credentials',
        'api_tokens',
        'rate_limit_buckets',
        'probability_audit_log',
        'probability_versions',
        'probability_profiles',
        'jackpot_events',
        'users',
        'games',
        'audit_log',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = Bootstrap::testEngine();
        $this->engine->container()->instance(ClockInterface::class, new FrozenClock());

        // Pin the RNG seed in the testing environment so a round's outcome is
        // reproducible. Production never honours a seed (RngFactory refuses),
        // so this only ever affects tests.
        $this->engine = \CasinoEngine\Core\Engine::boot(
            $this->engine->config()->with('engine.rng_seed', 20260101)
        );
        $this->engine->container()->instance(ClockInterface::class, new FrozenClock());

        try {
            $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class)->pdo();
        } catch (Throwable $e) {
            $this->markTestSkipped(
                'No test database reachable (' . $e->getMessage() . '). '
                . 'Set DB_TEST_DATABASE and DB_USERNAME/DB_PASSWORD in .env to run integration tests.'
            );
        }

        $this->migrate();
        $this->truncate();
        $this->engine->discoverGames();
    }

    private function migrate(): void
    {
        /** @var MigrationRunner $runner */
        $runner = $this->engine->container()->get(MigrationRunner::class);
        $runner->migrate();
    }

    private function truncate(): void
    {
        $connection = $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class);
        $pdo = $connection->pdo();

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->truncateTables as $table) {
            try {
                $pdo->exec("TRUNCATE TABLE `$table`");
            } catch (Throwable) {
                // A table that does not exist yet is not a failure: the
                // migration list is the source of truth and this is a reset.
                // The list is hand-maintained, so an extra entry is tolerated.
                continue;
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /** Creates a funded user and returns its id. */
    protected function createUserWithBalance(int $minorUnits = 100000, string $username = 'tester'): int
    {
        $connection = $this->engine->container()->get(\CasinoEngine\Core\Database\ConnectionInterface::class);
        $pdo = $connection->pdo();

        $statement = $pdo->prepare(
            'INSERT INTO users (username, display_name, status, created_at, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([$username, 'Tester', 'active']);
        $userId = (int) $pdo->lastInsertId();

        $statement = $pdo->prepare(
            'INSERT INTO wallets (user_id, currency, balance_minor, created_at, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([$userId, 'PHP', $minorUnits]);

        return $userId;
    }

    protected function clock(): FrozenClock
    {
        /** @var FrozenClock $clock */
        $clock = $this->engine->container()->get(ClockInterface::class);

        return $clock;
    }
}
