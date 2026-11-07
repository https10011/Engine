<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Unit\Value;

use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The two value types every payout passes through.
 *
 * Money is integer minor units, Ratio is an exact fraction, and their rounding
 * behaviour is what decides whether a 3:2 payout on an odd stake is right.
 */
final class MoneyAndRatioTest extends TestCase
{
    public function testMoneyIsExactInMinorUnits(): void
    {
        $money = Money::of(1050);

        self::assertSame(1050, $money->minorUnits());
        self::assertSame('10.50', $money->toDecimalString());
        self::assertSame('PHP', $money->currency());
    }

    public function testMoneyRejectsMixedCurrencies(): void
    {
        $this->expectException(ValidationException::class);

        Money::of(100, 'PHP')->add(Money::of(100, 'USD'));
    }

    public function testMoneySubtractionCanGoNegative(): void
    {
        $result = Money::of(100)->subtract(Money::of(250));

        self::assertSame(-150, $result->minorUnits());
        self::assertTrue($result->isNegative());
    }

    /** A 3:2 payout on 1.00 must be exactly 2.50. */
    public function testRatioAppliesExactly(): void
    {
        self::assertSame(250, Ratio::of(5, 2)->applyToMinorUnits(100));
    }

    /** @return iterable<string, array{Ratio, int, int}> */
    public static function roundingCases(): iterable
    {
        yield 'one third of 100' => [Ratio::of(1, 3), 100, 33];
        yield 'two thirds of 100' => [Ratio::of(2, 3), 100, 67];
        yield 'split 18x on 25' => [Ratio::of(18, 1), 25, 450];
        yield 'half of 101 rounds up' => [Ratio::of(1, 2), 101, 51];
        yield 'zero multiplier' => [Ratio::zero(), 100, 0];
    }

    #[DataProvider('roundingCases')]
    public function testRatioRounding(Ratio $ratio, int $minorUnits, int $expected): void
    {
        self::assertSame($expected, $ratio->applyToMinorUnits($minorUnits));
    }

    public function testRatioNormalisesSign(): void
    {
        $ratio = Ratio::of(2, -4);

        self::assertSame(-1, $ratio->numerator());
        self::assertSame(2, $ratio->denominator());
    }

    public function testRatioRejectsZeroDenominator(): void
    {
        $this->expectException(ValidationException::class);

        Ratio::of(1, 0);
    }

    public function testRatioAddsExactly(): void
    {
        self::assertTrue(Ratio::one()->add(Ratio::of(3, 2))->equals(Ratio::of(5, 2)));
    }
}
