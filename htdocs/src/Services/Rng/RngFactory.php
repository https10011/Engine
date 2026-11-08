<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Rng;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Contract\Service\RngServiceInterface;

/**
 * Chooses the randomness implementation.
 *
 * Production always gets the CSPRNG. A seed is honoured only outside
 * production, and even then the seeded generator refuses to construct in a
 * production environment — two independent guards against the same mistake.
 */
final class RngFactory
{
    public static function forEnvironment(Config $config, ?int $seed = null): RngServiceInterface
    {
        $environment = $config->environment();

        if ($seed !== null && $environment !== 'production') {
            return new SeededRandomGenerator($seed, $environment);
        }

        return new SecureRandomGenerator();
    }

    public static function secure(): RngServiceInterface
    {
        return new SecureRandomGenerator();
    }

    public static function seeded(int $seed, string $environment = 'testing'): RngServiceInterface
    {
        return new SeededRandomGenerator($seed, $environment);
    }
}
