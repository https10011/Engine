<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract\Service;

use DateTimeImmutable;

/**
 * Injectable time source.
 *
 * Every timestamp the engine writes goes through this, so a test can freeze
 * time and assert on expiry behaviour instead of sleeping.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;

    public function timestamp(): int;
}
