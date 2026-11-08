<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Combo multiplier trail.
 *
 * A winning spin starts (or advances) a combo, and the multiplier climbs a
 * configured ladder on each successive winning spin in the chain. A losing spin
 * resets it. This is the mechanic Super Ace presents as its combo trail and
 * that a cascade game expresses as a "tumble multiplier".
 *
 * The ladder is a list of integers applied in order; when the chain outruns the
 * ladder the final value repeats, which is the usual convention and keeps the
 * multiplier bounded.
 */
final class ComboMultiplierFeature implements FeatureInterface, SpinLifecycleInterface
{
    /**
     * The trail is per spin.
     *
     * A combo multiplier is a tumble ladder: the first cascade of a spin pays
     * the ladder's first rung and the third pays its third. Without this reset
     * the index would survive a spin that ended on a win and the next spin
     * would open part-way up the ladder, so the multiplier a player saw would
     * depend on the previous spin rather than on this one's cascades.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function onSpinStart(array $state): array
    {
        return ['index' => 0, 'multiplier' => 1];
    }

    /**
     * @param list<int> $ladder Multiplier per consecutive winning spin
     */
    public function __construct(
        private readonly array $ladder = [1, 2, 3, 5, 10],
        private readonly bool $resetsOnLoss = true
    ) {
    }

    public function type(): string
    {
        return 'combo_multiplier';
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $state = $context->state;
        $comboIndex = (int) ($state['index'] ?? 0);

        if (!$context->evaluation->isWin()) {
            if ($comboIndex === 0) {
                return FeatureStep::idle(['index' => 0, 'multiplier' => 1]);
            }

            if (!$this->resetsOnLoss) {
                $multiplier = $this->multiplierAt($comboIndex);

                return new FeatureStep(['index' => $comboIndex, 'multiplier' => $multiplier], null, $multiplier);
            }

            return new FeatureStep(
                ['index' => 0, 'multiplier' => 1],
                null,
                1,
                false,
                [new FeatureTransition($this->type(), FeatureTransition::ENDED, 0, ['combo' => $comboIndex])],
                ['combo' => $comboIndex, 'reset' => true]
            );
        }

        $nextIndex = $comboIndex + 1;
        $multiplier = $this->multiplierAt($nextIndex);

        return new FeatureStep(
            ['index' => $nextIndex, 'multiplier' => $multiplier],
            null,
            $multiplier,
            false,
            [new FeatureTransition(
                $this->type(),
                $comboIndex === 0 ? FeatureTransition::STARTED : FeatureTransition::UPDATED,
                $nextIndex,
                ['combo' => $nextIndex, 'multiplier' => $multiplier]
            )],
            ['combo' => $nextIndex, 'multiplier' => $multiplier]
        );
    }

    /** Multiplier for a 1-based combo index, saturating at the ladder's end. */
    private function multiplierAt(int $index): int
    {
        if ($this->ladder === []) {
            return 1;
        }

        $position = min($index - 1, count($this->ladder) - 1);

        return max(1, $this->ladder[max(0, $position)]);
    }
}
