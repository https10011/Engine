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
        $remaining = (int) ($state['remaining'] ?? 0);
        $transitions = [];

        // During free spins, a fresh scatter may add more spins.
        if ($context->isFreeSpin && $remaining > 0 && $this->retrigger) {
            $award = $this->awardFor($context->evaluation->scatterCount);
            if ($award > 0) {
                $totalAwarded = (int) ($state['awarded'] ?? 0);
                $allowed = max(0, min($award, $this->retriggerCap - $totalAwarded));
                if ($allowed > 0) {
                    $remaining += $allowed;
                    $state['awarded'] = $totalAwarded + $allowed;
                    $transitions[] = new FeatureTransition(
                        $this->type(),
                        FeatureTransition::UPDATED,
                        $remaining,
                        ['retrigger' => true, 'added' => $allowed]
                    );
                }
            }

            $remaining--;
            if ($remaining > 0) {
                $transitions[] = new FeatureTransition($this->type(), FeatureTransition::UPDATED, $remaining);
            } else {
                $transitions[] = new FeatureTransition($this->type(), FeatureTransition::ENDED, 0);
            }

            return new FeatureStep(
                ['remaining' => max(0, $remaining), 'awarded' => (int) ($state['awarded'] ?? 0)],
                null,
                1,
                $remaining > 0,
                $transitions,
                ['free_spin' => true]
            );
        }

        // A paid spin may trigger free spins.
        $award = $this->awardFor($context->evaluation->scatterCount);
        if ($award > 0) {
            $remaining = min($award, $this->retriggerCap);

            return new FeatureStep(
                ['remaining' => $remaining, 'awarded' => $remaining],
                null,
                1,
                true,
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
