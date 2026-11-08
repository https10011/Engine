<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Reel\ReelConfiguration;

/**
 * Expanding reels.
 *
 * Golden Empire's mechanic: eliminating a symbol expands the reel it was on, so
 * the ways count grows during the spin. The grid is *ragged* after an expansion
 * — one column is taller than the others — which is why {@see Grid} accepts a
 * per-reel height rather than assuming a rectangle.
 *
 * The feature owns the per-reel heights in its state so an expansion survives
 * the rest of the spin and is reset at the start of the next one.
 */
final class ExpandingReelsFeature implements FeatureInterface, SpinLifecycleInterface
{
    /**
     * @param list<int> $baseHeights
     */
    public function __construct(
        private readonly array $baseHeights,
        private readonly int $maxHeight = 7,
        private readonly string $featureType = 'expanding_reels'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    /**
     * A new spin starts at the base heights.
     *
     * An expansion belongs to the spin that caused it: carrying the grown
     * height into the next paid spin would permanently enlarge the game's grid
     * and with it the ways count and the RTP.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function onSpinStart(array $state): array
    {
        return ['heights' => $this->baseHeights];
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        /** @var list<int> $heights */
        $heights = $this->decodeHeights($context->state['heights'] ?? null);
        if ($heights === []) {
            $heights = $this->baseHeights;
        }

        if (!$context->evaluation->isWin()) {
            // A losing step leaves the heights as they are; they are reset by
            // the module at the start of the next spin, not here, because the
            // cascade chain is not finished yet.
            return new FeatureStep(['heights' => $heights], null, 1, false, [], ['heights' => $heights]);
        }

        // Every reel that contributed a winning position grows by one row, up
        // to the configured ceiling. Growing the reel that won is the mechanic;
        // growing every reel would be a different, far more generous one.
        $grow = [];
        foreach ($context->evaluation->wins as $win) {
            if ($win->kind === 'scatter') {
                continue;
            }
            foreach ($win->positions as $position) {
                $grow[$position[0]] = true;
            }
        }

        $changed = false;
        foreach (array_keys($grow) as $reelIndex) {
            if (!isset($heights[$reelIndex])) {
                continue;
            }
            if ($heights[$reelIndex] < $this->maxHeight) {
                $heights[$reelIndex]++;
                $changed = true;
            }
        }

        if (!$changed) {
            return new FeatureStep(['heights' => $heights], null, 1, false, [], ['heights' => $heights]);
        }

        $grid = $this->expandGrid($context, $heights);

        return new FeatureStep(
            ['heights' => $heights],
            $grid,
            1,
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                max($heights),
                ['heights' => $heights]
            )],
            ['heights' => $heights]
        );
    }

    /**
     * @param list<int> $heights
     */
    private function expandGrid(FeatureContext $context, array $heights): Grid
    {
        $cells = [];
        foreach ($context->grid->cells() as $reelIndex => $column) {
            $target = $heights[$reelIndex] ?? count($column);
            $grown = $column;
            while (count($grown) < $target) {
                $grown[] = $context->rng->pick($context->grid->symbolSet()->ids());
            }
            $cells[$reelIndex] = $grown;
        }

        return Grid::fromCells($cells, $context->grid->symbolSet());
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function decodeHeights(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $heights = [];
        foreach ($raw as $value) {
            $heights[] = (int) $value;
        }

        return $heights;
    }

    /**
     * Applies the current heights to a reel configuration.
     *
     * Used by the module when it builds the engine, so a session resumed
     * mid-feature spins the grid at the expanded height it left off at.
     *
     * @param list<int> $heights
     */
    public function applyToReels(ReelConfiguration $reels, array $heights): ReelConfiguration
    {
        if ($heights === []) {
            return $reels;
        }

        $clamped = [];
        foreach ($reels->rows() as $index => $rows) {
            $clamped[] = max($rows, $heights[$index] ?? $rows);
        }

        return new ReelConfiguration($reels->reels(), $clamped);
    }
}
