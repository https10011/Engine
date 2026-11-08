<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * A multiplier that accumulates and (optionally) never resets.
 *
 * Treasure of Aztec's free-spin multiplier is the clearest example: it starts
 * at x2 and rises by x2 per cascade, and it **never resets on a dead spin** —
 * the whole point of the feature. Golden Empire's free-game multiplier is the
 * same shape with no cap at all.
 *
 * The starting value and the step are configuration, and `$cap = null` means
 * genuinely uncapped, which is what "unlimited multiplier" means. An uncapped
 * multiplier is bounded in practice by the cascade cap the engine enforces
 * (`SlotEngine::$maximumFeatureIterations`), so it cannot run away.
 */
final class PersistentMultiplierFeature implements FeatureInterface
{
    public function __construct(
        private readonly int $startMultiplier = 1,
        private readonly int $step = 1,
        private readonly ?int $cap = null,
        private readonly bool $resetOnDeadSpin = true,
        private readonly bool $freeSpinsOnly = false,
        private readonly string $featureType = 'persistent_multiplier'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $state = $context->state;
        $current = (int) ($state['multiplier'] ?? $this->startMultiplier);
        $active = !$this->freeSpinsOnly || $context->isFreeSpin;

        if (!$active) {
            return new FeatureStep(['multiplier' => $this->startMultiplier], null, 1, false, [], ['inactive' => true]);
        }

        if (!$context->evaluation->isWin()) {
            if ($this->resetOnDeadSpin) {
                return new FeatureStep(
                    ['multiplier' => $this->startMultiplier],
                    null,
                    1,
                    false,
                    [],
                    ['reset' => true]
                );
            }

            // The multiplier survives a dead spin: the next winning step
            // continues from where the ladder had climbed to.
            return new FeatureStep(
                ['multiplier' => $current],
                null,
                1,
                false,
                [],
                ['held' => $current]
            );
        }

        $next = $current + $this->step;
        if ($this->cap !== null) {
            $next = min($this->cap, $next);
        }

        return new FeatureStep(
            ['multiplier' => $next],
            null,
            max(1, $next),
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $next,
                ['multiplier' => $next]
            )],
            ['multiplier' => $next]
        );
    }
}
