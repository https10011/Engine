<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Payout;

use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;

/**
 * Turns a game outcome multiplier into an amount.
 *
 * The multiplier comes from the module's evaluated outcome and never from a
 * client. Rounding happens once, here, using the exact rational ratio, so a
 * 3:2 blackjack on a 1.00 bet pays exactly 1.50.
 */
interface PayoutServiceInterface
{
    public function compute(Money $bet, Ratio $payoutMultiplier): Money;

    public function net(Money $bet, Money $payout): Money;

    /** @throws \CasinoEngine\Core\Exception\ValidationException */
    public function assertWithinMaxWin(Money $bet, Money $payout, int $maxWinMultiplier): void;
}
