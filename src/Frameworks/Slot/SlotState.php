<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot;

use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\ValidationException;

/**
 * Serialisable state of a slot game.
 *
 * Holds the feature state (free spins remaining, cascade index, combo index)
 * and the last grid so a reloaded session can render the same screen. The
 * schema version lets a future change migrate an old session instead of
 * misreading it.
 */
final class SlotState implements GameStateInterface
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param array<string, array<string, mixed>> $features feature type => state
     * @param list<list<string>>|null $lastGrid
     */
    public function __construct(
        private readonly array $features = [],
        private readonly ?array $lastGrid = null,
        private readonly int $spinsPlayed = 0
    ) {
    }

    public static function initial(): self
    {
        return new self([], null, 0);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /** @return array<string, mixed> */
    public function featureState(string $type): array
    {
        return $this->features[$type] ?? [];
    }

    public function freeSpinsRemaining(): int
    {
        return (int) ($this->features['free_spins']['remaining'] ?? 0);
    }

    public function isInFreeSpins(): bool
    {
        return $this->freeSpinsRemaining() > 0;
    }

    /** @return array<string, array<string, mixed>> */
    public function features(): array
    {
        return $this->features;
    }

    /** @return list<list<string>>|null */
    public function lastGrid(): ?array
    {
        return $this->lastGrid;
    }

    public function spinsPlayed(): int
    {
        return $this->spinsPlayed;
    }

    /**
     * @param array<string, array<string, mixed>> $features
     * @param list<list<string>>|null $lastGrid
     */
    public function withFeatures(array $features, ?array $lastGrid = null, ?int $spinsPlayed = null): self
    {
        return new self($features, $lastGrid ?? $this->lastGrid, $spinsPlayed ?? $this->spinsPlayed);
    }

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'features'       => $this->features,
            'last_grid'      => $this->lastGrid,
            'spins_played'   => $this->spinsPlayed,
        ];
    }

    public static function fromArray(array $data): static
    {
        $version = (int) ($data['schema_version'] ?? 1);
        if ($version > self::SCHEMA_VERSION) {
            throw new ValidationException('This session was created by a newer engine version.');
        }

        /** @var array<string, array<string, mixed>> $features */
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];

        /** @var list<list<string>>|null $grid */
        $grid = is_array($data['last_grid'] ?? null) ? $data['last_grid'] : null;

        return new self($features, $grid, (int) ($data['spins_played'] ?? 0));
    }

    public function isComplete(): bool
    {
        return false;
    }
}
