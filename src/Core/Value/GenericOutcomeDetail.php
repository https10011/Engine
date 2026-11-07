<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\GameOutcomeDetailInterface;

/**
 * A plain key/value outcome detail.
 *
 * Frameworks return richer, typed details of their own; this exists for
 * modules whose result really is just a bag of values, and as the neutral
 * detail used by tests.
 */
final class GenericOutcomeDetail implements GameOutcomeDetailInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly string $type,
        private readonly array $data = []
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
