<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Probability;

/**
 * How one round's outcome is classified against the configured targets.
 *
 * Classification is deliberately separate from the outcome itself: the governor
 * decides whether to *keep* an outcome, never what the outcome is. A round that
 * pays nothing is a genuine losing spin; it is only ever re-drawn, never
 * rewritten into a winner.
 */
final class OutcomeClassification
{
    public function __construct(
        public readonly bool $anyWin,
        public readonly bool $netPositive,
        public readonly bool $bonusTriggered,
        public readonly bool $freeSpinTriggered,
        public readonly bool $jackpotTriggered = false,
        public readonly ?string $jackpotTier = null
    ) {
    }

    public static function losing(): self
    {
        return new self(false, false, false, false);
    }
}
