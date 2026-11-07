<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Support;

use CasinoEngine\Core\Bootstrap;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Engine;
use CasinoEngine\Services\Rng\RngFactory;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for tests that need a booted engine but no database.
 *
 * Provides a fixed clock so a test can assert on timestamps, and a seeded RNG
 * so anything that draws randomness is reproducible.
 */
abstract class TestCase extends BaseTestCase
{
    protected Engine $engine;

    /** @var list<string> */
    private array $registeredGames = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = Bootstrap::engine();
        $this->engine->container()->instance(ClockInterface::class, new FrozenClock());
        $this->engine->discoverGames();
        $this->registeredGames = $this->engine->registry()->ids();
    }

    /** A deterministic RNG for tests that need one directly. */
    protected function seededRng(int $seed = 1234): RngServiceInterface
    {
        return RngFactory::seeded($seed);
    }

    /**
     * The engine's context for a game, with a seeded RNG.
     */
    protected function context(string $gameId, int $seed = 1234): \CasinoEngine\Core\GameContext
    {
        return $this->engine->contextFor($this->engine->registry()->get($gameId), $seed);
    }

    /** @return list<string> */
    protected function registeredGames(): array
    {
        return $this->registeredGames;
    }
}
