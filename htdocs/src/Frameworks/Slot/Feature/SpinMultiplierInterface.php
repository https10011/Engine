<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Marker for a feature that multiplies the *whole spin* rather than one
 * evaluation step.
 *
 * The distinction matters. A tumble ladder (a combo trail, a doubling cascade)
 * multiplies each step's own units, so the first cascade pays 1x and the third
 * pays 3x. A symbol multiplier collected during a tumble sequence — the
 * multiplier symbols in Gates of Olympus or the candy bombs in Sweet Bonanza —
 * is different: it accumulates across the sequence and multiplies the win the
 * sequence produced *once*, at the end.
 *
 * Both are legitimate, and which one a game means cannot be inferred from the
 * feature's code alone, so a feature declares it. {@see \CasinoEngine\Frameworks\Slot\SlotEngine}
 * collects the step multipliers of spin-scoped features separately and applies
 * them once to the spin's total.
 */
interface SpinMultiplierInterface
{
    /**
     * The multiplier this feature contributes to the whole spin.
     *
     * Called once per evaluation step, with the value the feature reported for
     * that step. The returned value replaces any previous contribution from
     * this feature, so a feature that keeps climbing reports its highest value
     * each time and the spin is multiplied by it once.
     *
     * @param int $reported the feature's per-step multiplier
     * @param int $current this feature's contribution so far this spin
     */
    public function spinMultiplier(int $reported, int $current): int;

    /** A transition announcing the final spin multiplier, or null for none. */
    public function spinMultiplierTransition(int $applied): ?FeatureTransition;
}
