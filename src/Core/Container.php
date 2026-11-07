<?php

declare(strict_types=1);

namespace CasinoEngine\Core;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\Connection;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Event\EventDispatcher;
use CasinoEngine\Core\Event\EventDispatcherInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Logging\LoggerFactory;
use CasinoEngine\Core\Registry\GameRegistry;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Core\Runtime\GameRuntime;
use CasinoEngine\Persistence\Migration\MigrationRunner;
use CasinoEngine\Persistence\Repository\AuditRepository;
use CasinoEngine\Persistence\Repository\BetRepository;
use CasinoEngine\Persistence\Repository\IdempotencyRepository;
use CasinoEngine\Persistence\Repository\PayoutRepository;
use CasinoEngine\Persistence\Repository\RoundRepository;
use CasinoEngine\Persistence\Repository\SessionRepository;
use CasinoEngine\Persistence\Repository\StatisticsRepository;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Betting\BettingService;
use CasinoEngine\Services\Betting\BettingServiceInterface;
use CasinoEngine\Services\History\HistoryService;
use CasinoEngine\Services\History\HistoryServiceInterface;
use CasinoEngine\Services\Idempotency\IdempotencyStore;
use CasinoEngine\Services\Idempotency\IdempotencyStoreInterface;
use CasinoEngine\Services\Payout\PayoutService;
use CasinoEngine\Services\Payout\PayoutServiceInterface;
use CasinoEngine\Services\Rng\RngFactory;
use CasinoEngine\Services\Round\RoundService;
use CasinoEngine\Services\Session\SessionService;
use CasinoEngine\Services\Session\SessionServiceInterface;
use CasinoEngine\Services\Statistics\StatisticsService;
use CasinoEngine\Services\Statistics\StatisticsServiceInterface;
use CasinoEngine\Services\Transaction\TransactionManager;
use CasinoEngine\Services\Transaction\TransactionManagerInterface;
use CasinoEngine\Services\Wallet\WalletService;
use CasinoEngine\Services\Wallet\WalletServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Lazy service container.
 *
 * Services are built on first request and reused, so booting the engine costs
 * nothing and a CLI command that only lists games never opens a database
 * connection. Every dependency is constructor-injected; there is no service
 * locator reachable from a game module.
 */
final class Container
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, callable(self): object> */
    private array $factories = [];

    public function __construct(private readonly Config $config)
    {
        $this->registerCoreFactories();
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Overrides a service. Used by tests to inject a clock, an RNG or a fake
     * connection without touching production wiring.
     *
     * @param callable(self): object $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /** @param object $instance */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new ConfigurationException("No service is registered as '$id'.");
        }

        $instance = ($this->factories[$id])($this);
        $this->instances[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    public function logger(): LoggerInterface
    {
        return $this->get(LoggerInterface::class);
    }

    private function registerCoreFactories(): void
    {
        $this->factories[Config::class] = fn (self $c): Config => $c->config;

        $this->factories[LoggerInterface::class] = fn (self $c): LoggerInterface => LoggerFactory::create(
            (string) $c->config->get('logging.channel', 'file'),
            (string) $c->config->get('logging.level', 'info'),
            $c->config->logPath(),
            is_array($c->config->get('logging.redact', [])) ? $c->config->get('logging.redact', []) : []
        );

        $this->factories[ClockInterface::class] = static fn (): ClockInterface
            => new \CasinoEngine\Core\Contract\Service\SystemClock();

        $this->factories[EventDispatcherInterface::class] = fn (self $c): EventDispatcherInterface
            => new EventDispatcher($c->logger());

        $this->factories[ConnectionInterface::class] = fn (self $c): ConnectionInterface => new Connection([
            'driver'           => (string) $c->config->get('database.driver', 'mysql'),
            'host'             => (string) $c->config->get('database.host', '127.0.0.1'),
            'port'             => (int) $c->config->get('database.port', 3306),
            'database'         => (string) $c->config->get('database.database', 'casino_engine'),
            'username'         => (string) $c->config->get('database.username', ''),
            'password'         => (string) $c->config->get('database.password', ''),
            'charset'          => (string) $c->config->get('database.charset', 'utf8mb4'),
            'persistent'       => (bool) $c->config->get('database.persistent', false),
            'emulate_prepares' => (bool) $c->config->get('database.emulate_prepares', false),
        ]);

        $this->registerRepositories();
        $this->registerServices();
    }

    private function registerRepositories(): void
    {
        $make = static fn (string $class): callable => static fn (self $c): object
            => new $class($c->get(ConnectionInterface::class));

        foreach ([
            UserRepository::class,
            WalletRepository::class,
            SessionRepository::class,
            RoundRepository::class,
            BetRepository::class,
            PayoutRepository::class,
            IdempotencyRepository::class,
            StatisticsRepository::class,
            AuditRepository::class,
        ] as $class) {
            $this->factories[$class] = $make($class);
        }
    }

    private function registerServices(): void
    {
        $this->factories[TransactionManagerInterface::class] = fn (self $c): TransactionManagerInterface
            => new TransactionManager($c->get(ConnectionInterface::class));

        $this->factories[WalletServiceInterface::class] = fn (self $c): WalletServiceInterface => new WalletService(
            $c->get(WalletRepository::class),
            $c->get(TransactionManagerInterface::class),
            $c->get(ClockInterface::class),
            $c->config
        );

        $this->factories[BettingServiceInterface::class] = static fn (): BettingServiceInterface
            => new BettingService();

        $this->factories[PayoutServiceInterface::class] = static fn (): PayoutServiceInterface
            => new PayoutService();

        $this->factories[SessionServiceInterface::class] = fn (self $c): SessionServiceInterface => new SessionService(
            $c->get(SessionRepository::class),
            $c->get(ClockInterface::class),
            $c->config,
            $c->get(EventDispatcherInterface::class)
        );

        $this->factories[StatisticsServiceInterface::class] = fn (self $c): StatisticsServiceInterface
            => new StatisticsService(
                $c->get(StatisticsRepository::class),
                $c->get(RoundRepository::class),
                $c->get(ClockInterface::class)
            );

        $this->factories[HistoryServiceInterface::class] = fn (self $c): HistoryServiceInterface => new HistoryService(
            $c->get(RoundRepository::class),
            $c->get(BetRepository::class),
            $c->get(WalletRepository::class)
        );

        $this->factories[IdempotencyStoreInterface::class] = fn (self $c): IdempotencyStoreInterface
            => new IdempotencyStore(
                $c->get(IdempotencyRepository::class),
                $c->get(ClockInterface::class),
                $c->config
            );

        $this->factories[GameRegistryInterface::class] = fn (self $c): GameRegistryInterface
            => new GameRegistry($c->get(EventDispatcherInterface::class));

        $this->factories[GameRuntime::class] = fn (self $c): GameRuntime
            => new GameRuntime($c->logger());

        $this->factories[RngFactory::class] = static fn (): RngFactory => new RngFactory();

        $this->factories[RoundService::class] = fn (self $c): RoundService => new RoundService(
            $c->get(GameRegistryInterface::class),
            $c->get(BettingServiceInterface::class),
            $c->get(SessionServiceInterface::class),
            $c->get(WalletServiceInterface::class),
            $c->get(PayoutServiceInterface::class),
            $c->get(StatisticsServiceInterface::class),
            $c->get(IdempotencyStoreInterface::class),
            $c->get(TransactionManagerInterface::class),
            $c->get(RoundRepository::class),
            $c->get(BetRepository::class),
            $c->get(PayoutRepository::class),
            $c->get(GameRuntime::class),
            $c->get(ClockInterface::class),
            $c->config,
            $c->get(RngFactory::class),
            $c->get(EventDispatcherInterface::class),
            $c->logger()
        );

        $this->factories[MigrationRunner::class] = fn (self $c): MigrationRunner => new MigrationRunner(
            $c->get(ConnectionInterface::class),
            rtrim($c->config->rootPath(), '/') . '/database/migrations',
            $c->get(ClockInterface::class)
        );
    }
}
