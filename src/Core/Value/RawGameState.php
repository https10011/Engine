<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\GameStateInterface;

/**
 * An untyped state snapshot.
 *
 * Used when the engine needs to carry state without knowing its shape — for
 * example a session loaded from the database before the game module has been
 * asked to interpret it. Modules never use this: they have their own typed
 * state classes.
 */
final class RawGameState implements GameStateInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly array $data,
        private readonly bool $complete = false
    ) {
    }

    public function schemaVersion(): int
    {
        return (int) ($this->data['schema_version'] ?? 1);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public static function fromArray(array $data): static
    {
        return new self($data);
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }
}
