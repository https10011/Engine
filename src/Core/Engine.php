<?php

declare(strict_types=1);

namespace CasinoEngine\Core;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Discovery\GameDiscovery;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Services\History\HistoryServiceInterface;
use CasinoEngine\Services\Rng\RngFactory;
use CasinoEngine\Services\Round\RoundService;
use CasinoEngine\Services\Session\SessionServiceInterface;
use CasinoEngine\Services\Statistics\StatisticsServiceInterface;
use CasinoEngine\Services\Wallet\WalletServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * The engine facade.
 *
 * This is the entry point a CLI command, an API controller or a test uses. It
 * exposes the engine's capabilities and hides how they are wired, so the
 * composition of services can change without touching callers.
 *
 * The engine holds no game rules. It knows how to find games, start sessions
 * and settle rounds; it knows nothing about reels, cards or numbers.
 */
final class Engine
{
    public function __construct(private readonly Container $container)
    {
    }

    public static function boot(Config $config): self
    {
        return new self(new Container($config));
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->container->config();
    }

    public function logger(): LoggerInterface
    {
        return $this->container->logger();
    }

    public function clock(): ClockInterface
    {
        return $this->container->get(ClockInterface::class);
    }

    public function registry(): GameRegistryInterface
    {
        return $this->container->get(GameRegistryInterface::class);
    }

    public function sessions(): SessionServiceInterface
    {
        return $this->container->get(SessionServiceInterface::class);
    }

    public function wallet(): WalletServiceInterface
    {
        return $this->container->get(WalletServiceInterface::class);
    }

    public function rounds(): RoundService
    {
        return $this->container->get(RoundService::class);
    }

    public function statistics(): StatisticsServiceInterface
    {
        return $this->container->get(StatisticsServiceInterface::class);
    }

    public function history(): HistoryServiceInterface
    {
        return $this->container->get(HistoryServiceInterface::class);
    }

    public function rngFactory(): RngFactory
    {
        return $this->container->get(RngFactory::class);
    }

    /** Registers a single game module explicitly. */
    public function register(GameInterface $game): void
    {
        $this->registry()->register($game);
    }

    /**
     * Discovers and registers game modules from the configured namespaces.
     *
     * @return list<string> ids registered by this call
     */
    public function discoverGames(): array
    {
        $namespaces = $this->config()->get('games.discovery_namespaces', ['CasinoEngine\\Games']);
        $namespaces = is_array($namespaces) ? array_values(array_map('strval', $namespaces)) : [];

        $paths = $this->namespacePaths($namespaces);

        $discovery = new GameDiscovery(
            $this->registry(),
            $namespaces,
            $paths,
            $this->logger()
        );

        return $discovery->discover(
            fn (?GameInterface $game): GameContext => $this->contextFor($game)
        );
    }

    /**
     * Builds the context handed to a game module.
     *
     * A module receives randomness, its own metadata and configuration, a
     * logger and a clock. It receives no wallet and no transaction manager, so
     * it cannot move money even by mistake.
     */
    public function contextFor(?GameInterface $game = null, ?int $seed = null): GameContext
    {
        $metadata = $game?->metadata();
        $configuration = $game?->defaultConfiguration();

        return new GameContext(
            $this->rngFactory()->forEnvironment($this->config(), $seed ?? $this->config()->get('engine.rng_seed')),
            $metadata ?? new \CasinoEngine\Core\Value\GameMetadata(
                'placeholder',
                'Placeholder',
                \CasinoEngine\Core\Contract\GameCategory::Slot,
                '0.0.0',
                '1.0.0',
                'engine',
                \CasinoEngine\Core\Value\Money::of(0),
                \CasinoEngine\Core\Value\Money::of(0),
                \CasinoEngine\Core\Value\Money::of(1),
                \CasinoEngine\Core\Contract\Volatility::Medium,
                0
            ),
            $configuration ?? new \CasinoEngine\Core\Value\NullConfiguration(),
            $this->logger(),
            $this->clock()
        );
    }

    /**
     * Maps configured namespaces to directories using Composer's PSR-4 map.
     *
     * @param list<string> $namespaces
     * @return array<string, string> prefix => directory
     */
    private function namespacePaths(array $namespaces): array
    {
        $composerPath = $this->config()->rootPath() . '/vendor/composer/autoload_psr4.php';
        $map = [];

        if (is_file($composerPath)) {
            /** @var array<string, list<string>> $psr4 */
            $psr4 = require $composerPath;
            foreach ($psr4 as $prefix => $directories) {
                foreach ($namespaces as $namespace) {
                    $normalised = rtrim($namespace, '\\') . '\\';
                    if (str_starts_with($prefix, $normalised) || $prefix === $normalised) {
                        $map[$prefix] = $directories[0];
                    }
                }
            }
        }

        return $map;
    }
}
