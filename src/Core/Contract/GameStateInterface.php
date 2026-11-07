<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

/**
 * Serialisable snapshot of everything a game needs to continue.
 *
 * State is stored as JSON on the session row, so it must round-trip without
 * loss. It carries a schema version so a future change can be migrated rather
 * than silently misinterpreted.
 */
interface GameStateInterface
{
    /** Version of the state layout; bumped when the shape changes. */
    public function schemaVersion(): int;

    /** @return array<string, mixed> */
    public function toArray(): array;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static;

    /** True when the game cannot accept further money-moving actions. */
    public function isComplete(): bool;
}
