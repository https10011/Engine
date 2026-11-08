<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Event;

use Psr\Log\LoggerInterface;
use Throwable;

final class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<int, list<callable>>> priority => listeners */
    private array $listeners = [];

    /** @var list<array{name:string,payload:array<string,mixed>}> */
    private array $dispatched = [];

    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $listener;
    }

    public function dispatch(string $eventName, array $payload = []): void
    {
        $this->dispatched[] = ['name' => $eventName, 'payload' => $payload];

        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $byPriority = $this->listeners[$eventName];
        krsort($byPriority);

        foreach ($byPriority as $listeners) {
            foreach ($listeners as $listener) {
                try {
                    $listener($eventName, $payload);
                } catch (Throwable $e) {
                    // A failing listener must never roll back committed money.
                    $this->logger?->error('Event listener failed', [
                        'event'     => $eventName,
                        'exception' => $e::class,
                        'message'   => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function listenerNames(): array
    {
        return array_keys($this->listeners);
    }

    /** @return list<array{name:string,payload:array<string,mixed>}> */
    public function dispatched(): array
    {
        return $this->dispatched;
    }

    public function reset(): void
    {
        $this->dispatched = [];
    }
}
