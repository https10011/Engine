<?php

declare(strict_types=1);

namespace CasinoEngine\Games\CoinFlip;

use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\ValidationException;

final class CoinFlipState implements GameStateInterface
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private readonly int $flipsPlayed = 0)
    {
    }

    public static function initial(): self
    {
        return new self();
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function flipsPlayed(): int
    {
        return $this->flipsPlayed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['schema' => self::SCHEMA_VERSION, 'flips_played' => $this->flipsPlayed];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        $schema = (int) ($data['schema'] ?? 0);
        if ($schema !== self::SCHEMA_VERSION) {
            throw new ValidationException("Unsupported coin flip state schema $schema.");
        }

        return new self((int) ($data['flips_played'] ?? 0));
    }

    public function isComplete(): bool
    {
        return false;
    }
}
