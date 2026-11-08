<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Frameworks\Slot\Grid\Grid;

/**
 * Nudge.
 *
 * Fortune Garuda's mechanic: a symbol *shifts* into a paying position and the
 * grid is then re-evaluated. Unlike a cascade the grid is not refilled — the
 * symbols move, which is what makes it a nudge rather than a tumble.
 *
 * The feature nudges only when the configured symbol is present, and only up to
 * `$maxSteps` times, so it cannot loop. It shifts the configured symbol upward
 * within its own reel, which is the direction a physical nudge reel moves.
 */
final class NudgeFeature implements FeatureInterface, SpinLifecycleInterface
{
    public function __construct(
        private readonly string $nudgeSymbol,
        private readonly float $probability = 0.08,
        private readonly int $maxSteps = 2,
        private readonly string $featureType = 'nudge'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function onSpinStart(array $state): array
    {
        return ['steps' => 0];
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $steps = (int) ($context->state['steps'] ?? 0);

        if ($steps >= $this->maxSteps) {
            return new FeatureStep(['steps' => $steps], null, 1, false, [], ['capped' => true]);
        }

        // A nudge is a chance event, not a certainty; a guaranteed nudge would
        // make the game's payout a function of the symbol's presence alone.
        if (!$context->rng->chance($this->probability)) {
            return new FeatureStep(['steps' => $steps], null, 1, false, [], ['nudged' => false]);
        }

        $grid = $context->grid;
        $nudged = false;

        foreach ($grid->cells() as $reel => $column) {
            foreach ($column as $row => $symbolId) {
                if ($symbolId !== $this->nudgeSymbol || $row === 0) {
                    continue;
                }

                // Swap the symbol with the cell above it. Nudging is a
                // movement, not a replacement: the symbol that was above moves
                // down, so the reel's contents are preserved.
                $above = $grid->symbolAt($reel, $row - 1);
                $grid = $grid->withSymbol($reel, $row - 1, $this->nudgeSymbol)
                    ->withSymbol($reel, $row, $above);
                $nudged = true;
                break 2;
            }
        }

        if (!$nudged) {
            return new FeatureStep(['steps' => $steps], null, 1, false, [], ['nudged' => false]);
        }

        return new FeatureStep(
            ['steps' => $steps + 1],
            $grid,
            1,
            false,
            [new FeatureTransition($this->type(), FeatureTransition::UPDATED, $steps + 1, ['steps' => $steps + 1])],
            ['nudged' => true, 'steps' => $steps + 1]
        );
    }
}
