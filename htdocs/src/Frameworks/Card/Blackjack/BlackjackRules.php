<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Blackjack;

use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Value\Ratio;

/**
 * Configurable blackjack rules.
 *
 * Every rule that varies between real tables is a field here, because the
 * difference between "dealer stands on soft 17" and "dealer hits soft 17" is
 * worth about half a percent of house edge — a variation far too large to be a
 * constant in shared code.
 */
final class BlackjackRules
{
    /**
     * @param bool $dealerHitsSoft17 Whether the dealer hits a soft 17
     * @param bool $doubleAfterSplit Whether doubling is allowed on a split hand
     * @param bool $resplitAces Whether a split ace may be split again
     * @param bool $hitSplitAces Whether a split ace receives a second card
     * @param int $maxHands Maximum hands from repeated splits
     * @param bool $blackjackBeatsAll Whether a natural wins over 21 (always true here)
     */
    public function __construct(
        public readonly int $decks = 6,
        public readonly bool $dealerHitsSoft17 = false,
        public readonly bool $dealerPeeks = true,
        public readonly bool $doubleAfterSplit = true,
        public readonly bool $resplitAces = false,
        public readonly bool $hitSplitAces = false,
        public readonly int $maxHands = 4,
        public readonly int $splitLimit = 3,
        ?Ratio $blackjackPayout = null,
        ?Ratio $standardPayout = null,
        ?Ratio $insurancePayout = null,
        public readonly bool $insuranceEnabled = true,
        public readonly bool $surrenderEnabled = false,
        public readonly bool $blackjackBeatsAll = true,
        public readonly float $reshuffleThreshold = 0.75
    ) {
        // A parameter default may not call a method, so the ratios are resolved
        // here where a call is legal. Null means "use the standard odds".
        $this->blackjackPayout = $blackjackPayout ?? Ratio::of(3, 2);
        $this->standardPayout = $standardPayout ?? Ratio::of(2, 1);
        $this->insurancePayout = $insurancePayout ?? Ratio::of(3, 1);
    }

    /** Natural blackjack returns 3:2 on the stake, plus the stake (5/2 total). */
    public readonly Ratio $blackjackPayout;

    /** A won hand returns 1:1 plus the stake (2/1 total). */
    public readonly Ratio $standardPayout;

    /** Insurance returns 2:1 plus the stake (3/1 total). */
    public readonly Ratio $insurancePayout;

    public function validate(): void
    {
        if ($this->decks < 1 || $this->decks > 8) {
            throw new ConfigurationException('A blackjack shoe must hold between 1 and 8 decks.');
        }
        if ($this->maxHands < 1 || $this->maxHands > 8) {
            throw new ConfigurationException('maxHands must be between 1 and 8.');
        }
        if ($this->blackjackPayout->numerator() < 1) {
            throw new ConfigurationException('A blackjack payout must be positive.');
        }
        if ($this->reshuffleThreshold <= 0.0 || $this->reshuffleThreshold > 1.0) {
            throw new ConfigurationException('The reshuffle threshold must be in (0, 1].');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'decks'               => $this->decks,
            'dealer_hits_soft_17' => $this->dealerHitsSoft17,
            'dealer_peeks'        => $this->dealerPeeks,
            'double_after_split'  => $this->doubleAfterSplit,
            'resplit_aces'        => $this->resplitAces,
            'hit_split_aces'      => $this->hitSplitAces,
            'max_hands'           => $this->maxHands,
            'split_limit'         => $this->splitLimit,
            'blackjack_payout'    => $this->blackjackPayout->jsonSerialize(),
            'standard_payout'     => $this->standardPayout->jsonSerialize(),
            'insurance_payout'    => $this->insurancePayout->jsonSerialize(),
            'insurance_enabled'   => $this->insuranceEnabled,
            'surrender_enabled'   => $this->surrenderEnabled,
            'reshuffle_threshold' => $this->reshuffleThreshold,
        ];
    }
}
