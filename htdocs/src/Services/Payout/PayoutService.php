<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Payout;

use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;

final class PayoutService implements PayoutServiceInterface
{
    public function compute(Money $bet, Ratio $payoutMultiplier): Money
    {
        return Money::of(
            $payoutMultiplier->applyToMinorUnits($bet->minorUnits()),
            $bet->currency(),
            $bet->scale()
        );
    }

    public function net(Money $bet, Money $payout): Money
    {
        return $payout->subtract($bet);
    }

    public function assertWithinMaxWin(Money $bet, Money $payout, int $maxWinMultiplier): void
    {
        if ($maxWinMultiplier <= 0) {
            return;
        }

        $ceiling = $bet->multiply($maxWinMultiplier);
        if ($payout->isGreaterThan($ceiling)) {
            // A payout above the game's declared cap means the module's
            // evaluation is wrong. Refusing is safer than paying it: the cap is
            // what an operator relies on for exposure.
            throw new ValidationException(
                'The computed payout exceeds the game maximum win.',
                ['payout' => $payout->minorUnits(), 'ceiling' => $ceiling->minorUnits()]
            );
        }
    }
}
