<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Support;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A clock that never moves.
 *
 * Session expiry, idempotency TTLs and transaction timestamps all depend on
 * the current time; a test that asserts on any of them needs time to hold
 * still, and a test that needs to cross an expiry boundary can advance it
 * explicitly rather than sleeping.
 */
final class FrozenClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function timestamp(): int
    {
        return $this->now->getTimestamp();
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
    }

    public function set(DateTimeImmutable $moment): void
    {
        $this->now = $moment;
    }
}
