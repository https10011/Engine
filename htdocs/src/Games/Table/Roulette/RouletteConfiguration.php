<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Table\Roulette;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;

/**
 * Roulette table configuration.
 *
 * The variant decides the wheel and the odds; the table limits decide what may
 * be staked on one number, on one inside bet and on the round as a whole. The
 * limits are per-table rather than global because an American double-zero table
 * and a French table with la partage are different products with different
 * exposure, and an operator sizes them separately.
 */
final class RouletteConfiguration implements GameConfigurationInterface
{
    public function __construct(
        private readonly ?RouletteVariant $variant = null,
        private readonly int $minBetMinor = 100,
        private readonly int $maxBetMinor = 500000,
        private readonly int $maxStraightBetMinor = 100000,
        private readonly int $maxOutsideBetMinor = 250000,
        private readonly int $maxTotalBetMinor = 1000000
    ) {
    }

    public static function defaultVariant(): RouletteVariant
    {
        return RouletteVariant::european();
    }

    public static function fromArray(array $values): static
    {
        $known = [
            'variant',
            'min_bet_minor',
            'max_bet_minor',
            'max_straight_bet_minor',
            'max_outside_bet_minor',
            'max_total_bet_minor',
        ];

        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw new ConfigurationException(
                'Unknown roulette configuration key(s): ' . implode(', ', $unknown) . '.'
            );
        }

        return new self(
            self::variantFromName((string) ($values['variant'] ?? RouletteVariant::EUROPEAN)),
            (int) ($values['min_bet_minor'] ?? 100),
            (int) ($values['max_bet_minor'] ?? 500000),
            (int) ($values['max_straight_bet_minor'] ?? 100000),
            (int) ($values['max_outside_bet_minor'] ?? 250000),
            (int) ($values['max_total_bet_minor'] ?? 1000000)
        );
    }

    public static function variantFromName(string $name): RouletteVariant
    {
        return match ($name) {
            RouletteVariant::AMERICAN => RouletteVariant::american(),
            RouletteVariant::FRENCH   => RouletteVariant::french(),
            RouletteVariant::EUROPEAN => RouletteVariant::european(),
            default => throw new ConfigurationException("Unknown roulette variant '$name'."),
        };
    }

    public function variant(): RouletteVariant
    {
        return $this->variant ?? self::defaultVariant();
    }

    public function minBetMinor(): int
    {
        return $this->minBetMinor;
    }

    public function maxBetMinor(): int
    {
        return $this->maxBetMinor;
    }

    public function maxStraightBetMinor(): int
    {
        return $this->maxStraightBetMinor;
    }

    public function maxOutsideBetMinor(): int
    {
        return $this->maxOutsideBetMinor;
    }

    public function maxTotalBetMinor(): int
    {
        return $this->maxTotalBetMinor;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'variant'                 => $this->variant()->name(),
            'min_bet_minor'           => $this->minBetMinor,
            'max_bet_minor'           => $this->maxBetMinor,
            'max_straight_bet_minor'  => $this->maxStraightBetMinor,
            'max_outside_bet_minor'   => $this->maxOutsideBetMinor,
            'max_total_bet_minor'     => $this->maxTotalBetMinor,
        ];
    }

    public function validate(): void
    {
        if ($this->variant()->pocketCount() < 2) {
            throw new ConfigurationException('A roulette wheel needs at least two pockets.');
        }

        if ($this->minBetMinor <= 0) {
            throw new ConfigurationException('The minimum bet must be positive.');
        }

        if ($this->maxBetMinor < $this->minBetMinor) {
            throw new ConfigurationException('The maximum bet cannot be below the minimum.');
        }

        if ($this->maxTotalBetMinor < $this->maxBetMinor) {
            throw new ConfigurationException('The total limit cannot be below the per-bet maximum.');
        }

        if ($this->maxStraightBetMinor <= 0 || $this->maxOutsideBetMinor <= 0) {
            throw new ConfigurationException('Per-bet-type limits must be positive.');
        }
    }
}
