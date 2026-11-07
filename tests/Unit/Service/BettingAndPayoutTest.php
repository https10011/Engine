<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Unit\Service;

use CasinoEngine\Core\Exception\InvalidBetException;
use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Services\Betting\BettingService;
use CasinoEngine\Services\Payout\PayoutService;
use PHPUnit\Framework\TestCase;

/**
 * Bet validation and payout arithmetic.
 *
 * Both are pure and are the last line of defence before money moves, so they
 * are tested directly rather than through the round service.
 */
final class BettingAndPayoutTest extends TestCase
{
    private function metadata(int $min, int $max, int $step): GameMetadata
    {
        return new GameMetadata(
            'test.game',
            'Test Game',
            \CasinoEngine\Core\Contract\GameCategory::Slot,
            '1.0.0',
            '1.0.0',
            'tests',
            Money::of($min),
            Money::of($max),
            Money::of($step),
            \CasinoEngine\Core\Contract\Volatility::Medium,
            100
        );
    }

    public function testBetWithinLimitsIsAccepted(): void
    {
        $service = new BettingService();

        $service->validate(Money::of(500), $this->metadata(100, 10000, 100));

        $this->addToAssertionCount(1);
    }

    public function testBetBelowMinimumIsRejected(): void
    {
        $this->expectException(InvalidBetException::class);

        (new BettingService())->validate(Money::of(50), $this->metadata(100, 10000, 100));
    }

    public function testBetAboveMaximumIsRejected(): void
    {
        $this->expectException(InvalidBetException::class);

        (new BettingService())->validate(Money::of(20000), $this->metadata(100, 10000, 100));
    }

    public function testBetNotOnTheStepIsRejected(): void
    {
        $this->expectException(InvalidBetException::class);

        (new BettingService())->validate(Money::of(150), $this->metadata(100, 10000, 100));
    }

    public function testBetLadderStaysWithinLimits(): void
    {
        $ladder = (new BettingService())->ladder($this->metadata(100, 1000, 100), 5);

        self::assertNotEmpty($ladder);
        foreach ($ladder as $bet) {
            self::assertGreaterThanOrEqual(100, $bet->minorUnits());
            self::assertLessThanOrEqual(1000, $bet->minorUnits());
        }
    }

    public function testPayoutIsExact(): void
    {
        $payout = (new PayoutService())->compute(Money::of(100), Ratio::of(3, 2));

        self::assertSame(150, $payout->minorUnits());
    }

    public function testPayoutAboveMaxWinIsRejected(): void
    {
        $this->expectException(\CasinoEngine\Core\Exception\ValidationException::class);

        (new PayoutService())->assertWithinMaxWin(Money::of(100), Money::of(100000), 100);
    }

    public function testPayoutAtMaxWinIsAllowed(): void
    {
        (new PayoutService())->assertWithinMaxWin(Money::of(100), Money::of(10000), 100);

        $this->addToAssertionCount(1);
    }
}
