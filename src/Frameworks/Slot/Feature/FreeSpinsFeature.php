<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Scatter-triggered free spins, with retrigger support.
 *
 * Two behaviours are configurable because the target titles differ:
 *
 *  - `$retrigger` — whether a scatter landing during free spins adds spins.
 *  - `$retriggerCap` — a ceiling on awarded spins, so a game cannot run away
 *    from an unlucky seed. Without a cap, a retriggering game has an unbounded
 *    upper tail and no operator can price it.
 *
 * The award table maps scatter count to spins. A count below the table's
 * minimum triggers nothing.
 */
final class FreeSpinsFeature implements FeatureInterface
{
    /**
     * @param array<int, int> $awardTable scatter count => spins awarded
     */
    public function __construct(
        private readonly array $awardTable,
        private readonly bool $retrigger = true,
        private readonly int $retriggerCap = 200,
        private readonly string $scatterSymbolId = 'SCATTER'
    ) {
    }

    public function type(): string
    {
        return 'free_spins';
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $state = $context->state;
        $transitions = [];

        // A free spin is a spin of its own, consumed by the *next* action, not
        // an extra evaluation of this grid. A feature that asked for another
        // evaluation of the same grid would pay the same winning line twice.
        // The feature therefore never continues the intra-spin loop; it only
        // manages the pool of spins the session carries forward.
        if ($context->isFreeSpin) {
            $remaining = max(0, (int) ($state['remaining'] ?? 0) - 1);
            $awarded = (int) ($state['awarded'] ?? 0);

            // A scatter landing during free spins may add more, up to the cap.
            if ($this->retrigger) {
                $award = $this->awardFor($context->evaluation->scatterCount);
                $allowed = max(0, min($award, $this->retriggerCap - $awarded));
                if ($allowed > 0) {
                    $remaining += $allowed;
                    $awarded += $allowed;
                    $transitions[] = new FeatureTransition(
                        $this->type(),
                        FeatureTransition::UPDATED,
                        $remaining,
                        ['retrigger' => true, 'added' => $allowed]
                    );
                }
            }

            $transitions[] = $remaining > 0
                ? new FeatureTransition($this->type(), FeatureTransition::UPDATED, $remaining)
                : new FeatureTransition($this->type(), FeatureTransition::ENDED, 0);

            return new FeatureStep(
                ['remaining' => $remaining, 'awarded' => $awarded],
                null,
                1,
                false,
                $transitions,
                ['free_spin' => true]
            );
        }

        // A paid spin may trigger a pool of free spins for the following spins.
        $award = $this->awardFor($context->evaluation->scatterCount);
        if ($award > 0) {
            $remaining = min($award, $this->retriggerCap);

            return new FeatureStep(
                ['remaining' => $remaining, 'awarded' => $remaining],
                null,
                1,
                false,
                [new FeatureTransition(
                    $this->type(),
                    FeatureTransition::STARTED,
                    $remaining,
                    ['scatter_count' => $context->evaluation->scatterCount]
                )],
                ['free_spin' => false]
            );
        }

        return FeatureStep::idle(['remaining' => 0, 'awarded' => 0]);
    }

    private function awardFor(int $scatterCount): int
    {
        if (isset($this->awardTable[$scatterCount])) {
            return $this->awardTable[$scatterCount];
        }

        // A count above the table awards the highest configured tier, which is
        // the usual convention for "5+ scatters".
        $keys = array_keys($this->awardTable);
        if ($keys === []) {
            return 0;
        }

        $highest = max($keys);

        return $scatterCount >= $highest ? $this->awardTable[$highest] : 0;
    }
}
