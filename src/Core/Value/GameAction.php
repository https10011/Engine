<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Exception\ValidationException;

/** Immutable player instruction. */
final class GameAction implements GameActionInterface
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        private readonly string $name,
        private readonly array $payload,
        private readonly ?Money $bet
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function create(string $name, array $payload = [], ?Money $bet = null): self
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/', $name) !== 1) {
            throw new ValidationException("Action name '$name' is not a valid identifier.");
        }

        return new self($name, $payload, $bet);
    }

    /** Convenience for a money-moving action. */
    public static function withBet(string $name, Money $bet, array $payload = []): self
    {
        return self::create($name, $payload, $bet);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function bet(): ?Money
    {
        return $this->bet;
    }

    public function fingerprint(): string
    {
        $payload = $this->payload;
        ksort($payload);

        return hash('sha256', json_encode([
            'name' => $this->name,
            'bet'  => $this->bet?->minorUnits(),
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }
}
