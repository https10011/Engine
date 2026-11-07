<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Synthetic;

use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\ValidationException;

/** State for the synthetic roulette fixture: the last spin and a running count. */
final class SyntheticRouletteState implements GameStateInterface
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param list<array<string, mixed>> $history
     * @param array<string, mixed>|null $lastSpin
     */
    public function __construct(
        private readonly array $history = [],
        private readonly ?array $lastSpin = null
    ) {
    }

    public static function initial(): self
    {
        return new self();
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /** @return list<array<string, mixed>> */
    public function history(): array
    {
        return $this->history;
    }

    /** @return array<string, mixed>|null */
    public function lastSpin(): ?array
    {
        return $this->lastSpin;
    }

    public function spinsPlayed(): int
    {
        return count($this->history);
    }

    /** @param array<string, mixed> $spin */
    public function withSpin(array $spin): self
    {
        // Only the last 50 spins are retained; the full history lives in the
        // database, and an unbounded array on the session row would grow the
        // row without limit.
        $history = $this->history;
        $history[] = [
            'number' => $spin['winning_number'] ?? null,
            'colour' => $spin['colour'] ?? null,
            'payout' => $spin['total_payout']['amount'] ?? 0,
            'staked' => $spin['total_staked']['amount'] ?? 0,
        ];

        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }

        return new self($history, $spin);
    }

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'history'        => $this->history,
            'last_spin'      => $this->lastSpin,
            'spins_played'   => count($this->history),
        ];
    }

    public static function fromArray(array $data): static
    {
        $version = (int) ($data['schema_version'] ?? 1);
        if ($version > self::SCHEMA_VERSION) {
            throw new ValidationException('This session was created by a newer engine version.');
        }

        /** @var list<array<string, mixed>> $history */
        $history = is_array($data['history'] ?? null) ? array_values($data['history']) : [];
        /** @var array<string, mixed>|null $lastSpin */
        $lastSpin = is_array($data['last_spin'] ?? null) ? $data['last_spin'] : null;

        return new self($history, $lastSpin);
    }

    public function isComplete(): bool
    {
        return false;
    }
}
