<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Exception\ValidationException;

/**
 * Exact rational number, used for payout multipliers.
 *
 * Blackjack pays 3:2 and roulette pays 35:1; both are exact fractions that a
 * float would only approximate. Keeping multipliers rational means
 * `Ratio::of(3, 2)` multiplied by 100 minor units gives exactly 150, with the
 * rounding decision made once, explicitly, at the point money is computed.
 */
final class Ratio implements \JsonSerializable
{
    private function __construct(
        private readonly int $numerator,
        private readonly int $denominator
    ) {
        if ($denominator === 0) {
            throw new ValidationException('Ratio denominator cannot be zero.');
        }
    }

    public static function of(int $numerator, int $denominator = 1): self
    {
        if ($denominator === 0) {
            throw new ValidationException('Ratio denominator cannot be zero.');
        }

        if ($numerator < 0) {
            throw new ValidationException('Ratio cannot be negative.');
        }

        // Normalise the sign onto the numerator and reduce by the GCD so that
        // 2/4 and 1/2 compare equal.
        if ($denominator < 0) {
            $numerator   = -$numerator;
            $denominator = -$denominator;
        }

        $gcd = self::gcd(abs($numerator), $denominator);

        return new self(intdiv($numerator, $gcd), intdiv($denominator, $gcd));
    }

    public static function zero(): self
    {
        return new self(0, 1);
    }

    public static function one(): self
    {
        return new self(1, 1);
    }

    /** @param int|float $value */
    public static function fromNumber(int|float $value): self
    {
        if (is_int($value)) {
            return self::of($value);
        }

        if ($value < 0) {
            throw new ValidationException('Ratio cannot be negative.');
        }

        // Convert a float with up to 6 decimal places into an exact fraction.
        $scaled = (int) round($value * 1_000_000);

        return self::of($scaled, 1_000_000);
    }

    public function numerator(): int
    {
        return $this->numerator;
    }

    public function denominator(): int
    {
        return $this->denominator;
    }

    public function isZero(): bool
    {
        return $this->numerator === 0;
    }

    public function multiply(self $other): self
    {
        return self::of($this->numerator * $other->numerator, $this->denominator * $other->denominator);
    }

    public function add(self $other): self
    {
        return self::of(
            $this->numerator * $other->denominator + $other->numerator * $this->denominator,
            $this->denominator * $other->denominator
        );
    }

    /**
     * Applies the ratio to a minor-unit amount, rounding half-up.
     *
     * This is the single place where a rational multiplier becomes an integer
     * amount, so the rounding policy is consistent for every game.
     */
    public function applyToMinorUnits(int $minorUnits): int
    {
        $product = $minorUnits * $this->numerator;

        return intdiv($product * 2 + $this->denominator, $this->denominator * 2);
    }

    public function toFloat(): float
    {
        return $this->numerator / $this->denominator;
    }

    public function equals(self $other): bool
    {
        return $this->numerator === $other->numerator && $this->denominator === $other->denominator;
    }

    public function __toString(): string
    {
        return $this->denominator === 1
            ? (string) $this->numerator
            : $this->numerator . '/' . $this->denominator;
    }

    /** @return array{numerator:int,denominator:int,value:float} */
    public function jsonSerialize(): array
    {
        return [
            'numerator'   => $this->numerator,
            'denominator' => $this->denominator,
            'value'       => round($this->toFloat(), 6),
        ];
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a === 0 ? 1 : $a;
    }
}
