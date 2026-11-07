<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Blackjack;

use CasinoEngine\Core\Value\Ratio;

/**
 * Turns a hand outcome into a payout multiplier.
 *
 * The rounding decision for a 3:2 natural on an odd bet is made here, once, and
 * uses the same rational-ratio rounding as the rest of the engine, so a 1.00
 * bet at 3:2 pays exactly 2.50 and never 2.499999.
 */
final class BlackjackSettlement
{
    public static function multiplier(BlackjackOutcome $outcome, BlackjackRules $rules): Ratio
    {
        if ($outcome->isPush()) {
            // The stake returns; nothing is won or lost.
            return Ratio::one();
        }

        if ($outcome->result === BlackjackOutcome::SURRENDER) {
            return Ratio::of(1, 2);
        }

        if (!$outcome->playerWins()) {
            return Ratio::zero();
        }

        if ($outcome->result === BlackjackOutcome::PLAYER_BLACKJACK) {
            // Payout is 3:2 on the stake, so 1 (stake) + 3/2 = 5/2 returned.
            return Ratio::one()->add($rules->blackjackPayout);
        }

        return $rules->standardPayout;
    }

    /**
     * Compares two totals with blackjack rules.
     *
     * A natural beats a drawn 21, which is why the flag is passed rather than
     * inferred from the total.
     */
    public static function resolve(
        int $playerTotal,
        int $dealerTotal,
        bool $playerNatural,
        bool $dealerNatural,
        bool $playerBust,
        bool $dealerBust,
        bool $wasSplit = false
    ): BlackjackOutcome {
        if ($playerBust) {
            return new BlackjackOutcome(BlackjackOutcome::PLAYER_BUST, $playerTotal, $dealerTotal, false, $wasSplit);
        }

        if ($dealerBust) {
            return new BlackjackOutcome(BlackjackOutcome::DEALER_BUST, $playerTotal, $dealerTotal, false, $wasSplit);
        }

        if ($playerNatural && !$dealerNatural) {
            return new BlackjackOutcome(
                BlackjackOutcome::PLAYER_BLACKJACK,
                $playerTotal,
                $dealerTotal,
                true, $wasSplit);
        }

        if ($dealerNatural && !$playerNatural) {
            return new BlackjackOutcome(
                BlackjackOutcome::DEALER_BLACKJACK,
                $playerTotal,
                $dealerTotal,
                false, $wasSplit);
        }

        if ($playerNatural && $dealerNatural) {
            return new BlackjackOutcome(BlackjackOutcome::PUSH, $playerTotal, $dealerTotal, true, $wasSplit);
        }

        if ($playerTotal > $dealerTotal) {
            return new BlackjackOutcome(BlackjackOutcome::PLAYER_WIN, $playerTotal, $dealerTotal, false, $wasSplit);
        }

        if ($playerTotal < $dealerTotal) {
            return new BlackjackOutcome(BlackjackOutcome::DEALER_WIN, $playerTotal, $dealerTotal, false, $wasSplit);
        }

        return new BlackjackOutcome(BlackjackOutcome::PUSH, $playerTotal, $dealerTotal, false, $wasSplit);
    }
}
