<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Symbol;

use CasinoEngine\Core\Exception\ConfigurationException;

/** One distinct symbol in a game's symbol set. */
final class Symbol
{
    /**
     * @param int $payoutWeight Relative frequency weight used when a reel is
     *        generated from weights rather than an explicit strip. Higher means
     *        more common.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly SymbolType $type = SymbolType::Regular,
        public readonly int $payoutWeight = 1,
        /** Multiplier applied when this symbol is part of a win (e.g. a x2 wild). */
        public readonly int $winMultiplier = 1
    ) {
        if (preg_match('/^[A-Za-z0-9_]{1,32}$/', $id) !== 1) {
            throw new ConfigurationException("Symbol id '$id' must be 1-32 alphanumeric or underscore characters.");
        }
        if ($payoutWeight < 0) {
            throw new ConfigurationException("Symbol '$id' weight cannot be negative.");
        }
        if ($winMultiplier < 1) {
            throw new ConfigurationException("Symbol '$id' win multiplier must be at least 1.");
        }
    }

    public static function regular(string $id, string $name, int $weight = 1, int $winMultiplier = 1): self
    {
        return new self($id, $name, SymbolType::Regular, $weight, $winMultiplier);
    }

    public static function wild(string $id, string $name = 'Wild', int $weight = 1, int $winMultiplier = 1): self
    {
        return new self($id, $name, SymbolType::Wild, $weight, $winMultiplier);
    }

    public static function scatter(string $id, string $name = 'Scatter', int $weight = 1): self
    {
        return new self($id, $name, SymbolType::Scatter, $weight);
    }

    public static function bonus(string $id, string $name = 'Bonus', int $weight = 1): self
    {
        return new self($id, $name, SymbolType::Bonus, $weight);
    }

    public static function blank(string $id = 'BLANK'): self
    {
        return new self($id, 'Blank', SymbolType::Blank, 0);
    }

    public function isWild(): bool
    {
        return $this->type === SymbolType::Wild;
    }

    public function isScatter(): bool
    {
        return $this->type === SymbolType::Scatter;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'type'           => $this->type->value,
            'payout_weight'  => $this->payoutWeight,
            'win_multiplier' => $this->winMultiplier,
        ];
    }
}
