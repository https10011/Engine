<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract\Service;

use DateTimeImmutable;

/**
 * Test clock. Time only moves when a test tells it to, which is what makes
 * session-expiry behaviour deterministic.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public static function at(string $iso8601): self
    {
        return new self(new DateTimeImmutable($iso8601));
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
        $this->now = $this->now->modify(sprintf('%+d seconds', $seconds));
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
