<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract\Service;

use DateTimeImmutable;
use DateTimeZone;

/** Production clock reading the real system time in UTC. */
final class SystemClock implements ClockInterface
{
    public function __construct(private readonly DateTimeZone $zone = new DateTimeZone('UTC'))
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->zone);
    }

    public function timestamp(): int
    {
        return time();
    }
}
