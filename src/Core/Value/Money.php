<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Exception\ValidationException;
use JsonSerializable;

/**
 * Immutable money amount held in **integer minor units**.
 *
 * Balances, wagers and payouts are never floats: a float cannot represent
 * 0.10 exactly, and repeated arithmetic on one drifts. Every amount in the
 * engine is an integer number of cents (or the configured minor unit), with
 * the currency carried alongside so an amount can never be silently combined
 * with one in a different currency.
 */
final class Money implements JsonSerializable
{
    private function __construct(
        private readonly int $minorUnits,
        private readonly string $currency,
        private readonly int $scale
    ) {
    }

    public static function of(int $minorUnits, string $currency = 'PHP', int $scale = 2): self
    {
        if ($scale < 0 || $scale > 6) {
            throw new ValidationException('Money scale must be between 0 and 6.');
        }

        return new self($minorUnits, strtoupper($currency), $scale);
    }

    public static function zero(string $currency = 'PHP', int $scale = 2): self
    {
        return new self(0, strtoupper($currency), $scale);
    }

    /**
     * Parses a decimal string such as `"1.50"` into minor units without ever
     * going through a float.
     */
    public static function fromDecimalString(string $decimal, string $currency = 'PHP', int $scale = 2): self
    {
        $decimal = trim($decimal);
        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $decimal, $matches)) {
            throw new ValidationException('Amount must be a decimal number.');
        }

        $sign       = $matches[1] === '-' ? -1 : 1;
        $whole      = $matches[2];
        $fraction   = $matches[3] ?? '';
        $fraction   = str_pad(substr($fraction, 0, $scale), $scale, '0');

        $minor = (int) $whole * (10 ** $scale) + (int) ($fraction === '' ? '0' : $fraction);

        return new self($sign * $minor, strtoupper($currency), $scale);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function scale(): int
    {
        return $this->scale;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency, $this->scale);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency, $this->scale);
    }

    public function multiply(int|float $factor): self
    {
        if ($factor < 0) {
            throw new ValidationException('Money cannot be multiplied by a negative factor.');
        }

        // Round half-up on the exact rational value so that 0.5 * 1 = 1 minor
        // unit rather than 0, which truncation would give.
        $product = $this->minorUnits * $factor;

        return new self((int) round($product, 0, PHP_ROUND_HALF_UP), $this->currency, $this->scale);
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits <=> $other->minorUnits;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorUnits === $other->minorUnits;
    }

    /** Human-readable decimal string, e.g. `"1.50"`. */
    public function toDecimalString(): string
    {
        if ($this->scale === 0) {
            return (string) $this->minorUnits;
        }

        $divisor = 10 ** $this->scale;
        $sign    = $this->minorUnits < 0 ? '-' : '';
        $absolute = abs($this->minorUnits);
        $whole   = intdiv($absolute, $divisor);
        $fraction = $absolute % $divisor;

        return sprintf('%s%d.%s', $sign, $whole, str_pad((string) $fraction, $this->scale, '0', STR_PAD_LEFT));
    }

    /** Formatted for display, e.g. `"₱1.50"`. */
    public function format(): string
    {
        $symbol = match ($this->currency) {
            'PHP'  => '₱',
            'USD'  => '$',
            'EUR'  => '€',
            default => $this->currency . ' ',
        };

        return $symbol . $this->toDecimalString();
    }

    /** @return array{amount:int,currency:string,scale:int,formatted:string} */
    public function jsonSerialize(): array
    {
        return [
            'amount'    => $this->minorUnits,
            'currency'  => $this->currency,
            'scale'     => $this->scale,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new ValidationException(
                sprintf('Cannot combine %s with %s.', $this->currency, $other->currency)
            );
        }
        if ($this->scale !== $other->scale) {
            throw new ValidationException('Cannot combine amounts with different scales.');
        }
    }
}
