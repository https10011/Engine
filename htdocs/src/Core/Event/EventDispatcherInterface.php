<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Event;

/**
 * Minimal synchronous event bus.
 *
 * Listeners run in registration order after a round has committed, so a
 * listener never observes uncommitted money. A listener that throws is logged
 * and skipped rather than allowed to fail the request: an analytics side
 * effect must not be able to undo a completed round.
 */
interface EventDispatcherInterface
{
    public function listen(string $eventName, callable $listener, int $priority = 0): void;

    /** @param array<string, mixed> $payload */
    public function dispatch(string $eventName, array $payload = []): void;

    /** @return list<string> Names that currently have at least one listener. */
    public function listenerNames(): array;
}
