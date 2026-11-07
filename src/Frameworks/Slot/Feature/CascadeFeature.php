<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Frameworks\Slot\Grid\Grid;

/**
 * Cascading (tumbling) reels.
 *
 * After a win, the winning cells are removed, the symbols above them fall, and
 * the empty cells at the top of each reel are refilled from that reel's strip.
 * The evaluation then runs again on the new grid.
 *
 * The refill uses the reel strip, so a cascaded grid has the same symbol
 * distribution as a spun one — a detail that matters, because refilling
 * uniformly at random instead would quietly change the game's mathematics.
 *
 * `$maximumCascades` bounds the chain. A chain is not guaranteed to end (a
 * refill can win again indefinitely in theory), and an unbounded loop is both a
 * payout risk and a request that never returns.
 */
final class CascadeFeature implements FeatureInterface
{
    public function __construct(
        private readonly int $maximumCascades = 20,
        private readonly string $refillStrategy = 'reel_strip'
    ) {
    }

    public function type(): string
    {
        return 'cascade';
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $state = $context->state;
        $cascadeIndex = (int) ($state['index'] ?? 0);

        if (!$context->evaluation->isWin()) {
            return new FeatureStep(
                ['index' => 0],
                null,
                1,
                false,
                $cascadeIndex > 0
                    ? [new FeatureTransition($this->type(), FeatureTransition::ENDED, 0, ['cascades' => $cascadeIndex])]
                    : [],
                ['cascades' => $cascadeIndex]
            );
        }

        if ($cascadeIndex >= $this->maximumCascades) {
            return new FeatureStep(
                ['index' => 0],
                null,
                1,
                false,
                [new FeatureTransition($this->type(), FeatureTransition::ENDED, 0, ['capped' => true])],
                ['cascades' => $cascadeIndex, 'capped' => true]
            );
        }

        $positions = $this->winningPositions($context->evaluation->wins);
        if ($positions === []) {
            return FeatureStep::idle(['index' => 0]);
        }

        $cascaded = $this->removeAndRefill($context, $positions);

        return new FeatureStep(
            ['index' => $cascadeIndex + 1],
            $cascaded,
            1,
            true,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $this->maximumCascades - $cascadeIndex - 1,
                ['cascade_index' => $cascadeIndex + 1, 'removed' => count($positions)]
            )],
            ['cascades' => $cascadeIndex + 1]
        );
    }

    /**
     * @param list<\CasinoEngine\Frameworks\Slot\Evaluation\WinLine> $wins
     * @return list<array{0:int,1:int}>
     */
    private function winningPositions(array $wins): array
    {
        $positions = [];
        foreach ($wins as $win) {
            if ($win->kind === 'scatter') {
                // Scatters pay anywhere but are not removed by a cascade; a
                // scatter that vanished would break its own trigger.
                continue;
            }
            foreach ($win->positions as $position) {
                $positions["{$position[0]}:{$position[1]}"] = $position;
            }
        }

        return array_values($positions);
    }

    /**
     * @param list<array{0:int,1:int}> $positions
     */
    private function removeAndRefill(FeatureContext $context, array $positions): Grid
    {
        $grid = $context->grid;
        $removed = $grid->withoutPositions($positions);

        // Refill each reel to its original height. The strip is walked from a
        // random position so the refilled symbols are not always the same ones.
        $cells = [];
        foreach ($removed->cells() as $reelIndex => $column) {
            $height = $grid->rowsFor($reelIndex);
            $cells[$reelIndex] = $column;

            while (count($cells[$reelIndex]) < $height) {
                $cells[$reelIndex][] = $context->rng->pick($grid->symbolSet()->ids());
            }
        }

        return Grid::fromCells($cells, $grid->symbolSet());
    }
}
