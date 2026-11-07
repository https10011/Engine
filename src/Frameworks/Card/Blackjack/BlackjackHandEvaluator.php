<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Blackjack;

use CasinoEngine\Frameworks\Card\Hand\Hand;

/**
 * Blackjack hand evaluation.
 *
 * A natural is exactly two cards totalling 21, and a natural is *only* a
 * natural when the hand has not been split — the standard rule that keeps a
 * split 21 from paying 3:2. That distinction is the single most commonly
 * mishandled blackjack rule, so it is explicit here.
 */
final class BlackjackHandEvaluator
{
    public const BLACKJACK = 21;

    public static function total(Hand $hand): int
    {
        return $hand->blackjackTotal();
    }

    public static function isBust(Hand $hand): bool
    {
        return $hand->blackjackTotal() > self::BLACKJACK;
    }

    public static function isSoft(Hand $hand): bool
    {
        return $hand->isSoft();
    }

    public static function isHard(Hand $hand): bool
    {
        return !$hand->isSoft();
    }

    /**
     * A natural: two cards making 21 from an unsplit hand.
     */
    public static function isNatural(Hand $hand, bool $wasSplit): bool
    {
        return !$wasSplit
            && $hand->count() === 2
            && $hand->blackjackTotal() === self::BLACKJACK;
    }

    /** A pair that may be split: same rank, not just same value. */
    public static function isPair(Hand $hand): bool
    {
        if ($hand->count() !== 2) {
            return false;
        }

        $cards = $hand->cards();

        return $cards[0]->rank === $cards[1]->rank;
    }

    public static function isPairOfAces(Hand $hand): bool
    {
        return self::isPair($hand) && $hand->cards()[0]->isAce();
    }

    /** Dealer draws while the total is below 17, and on soft 17 when configured. */
    public static function dealerShouldHit(Hand $hand, BlackjackRules $rules): bool
    {
        $total = $hand->blackjackTotal();

        if ($total < 17) {
            return true;
        }

        if ($total === 17 && $rules->dealerHitsSoft17 && $hand->isSoft()) {
            return true;
        }

        return false;
    }
}
