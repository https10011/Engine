<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Exception\ConfigurationException;
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
 *
 * `$leavesWild` implements the symbol-transformation mechanic shared by a whole
 * family of the target titles (Super Ace's Gold Card, Super Ace Deluxe's Golden
 * Symbol, Piñata Wins' gold frame, Wild Bounty Showdown's gold frame, the
 * Mahjong tiles): when a cell holding one of those symbols is removed by the
 * cascade, it refills as the wild rather than from the strip. That is exactly
 * what "becomes a wild when eliminated" means, and putting it here — rather than
 * in a separate feature — is what keeps the transformation inside the cascade
 * step that caused it, so the very next evaluation sees the wild.
 */
final class CascadeFeature implements FeatureInterface
{
    /** Strategies this feature implements. Anything else is a configuration error. */
    public const REFILL_STRATEGIES = ['reel_strip'];

    /**
     * @param list<string> $leavesWild Source symbols that refill as a wild when removed
     * @param string|null $leavesWildSymbol The wild they become
     */
    public function __construct(
        private readonly int $maximumCascades = 20,
        private readonly string $refillStrategy = 'reel_strip',
        private readonly array $leavesWild = [],
        private readonly ?string $leavesWildSymbol = null,
        private readonly float $leavesWildProbability = 1.0,
        private readonly bool $keepScatters = false
    ) {
        if (!in_array($refillStrategy, self::REFILL_STRATEGIES, true)) {
            throw new ConfigurationException(sprintf(
                "Unknown cascade refill strategy '%s'. Supported: %s.",
                $refillStrategy,
                implode(', ', self::REFILL_STRATEGIES)
            ));
        }
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

        // The cascade returns a new grid and asks for one more evaluation of
        // it. It deliberately does *not* set `continues`, which would re-apply
        // this feature to the same grid and never settle.
        return new FeatureStep(
            ['index' => $cascadeIndex + 1],
            $cascaded,
            1,
            false,
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
     * Positions removed by a cascade.
     *
     * Every position that has just been *paid* is removed, including scatters.
     * A cascade re-evaluates the grid, so a winning position that stayed put
     * would be paid again on the next step — the same scatter landing three
     * times would pay three times. Removing what was paid is the invariant that
     * makes a cascade chain a sequence of distinct wins rather than one win
     * counted repeatedly.
     *
     * `$keepScatters` exists for a game whose scatter is deliberately
     * persistent, and is off by default because the double-payment it causes is
     * almost never intended.
     *
     * @param list<\CasinoEngine\Frameworks\Slot\Evaluation\WinLine> $wins
     * @return list<array{0:int,1:int}>
     */
    private function winningPositions(array $wins): array
    {
        $positions = [];
        foreach ($wins as $win) {
            if ($win->kind === 'scatter' && $this->keepScatters) {
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

        // Mark the winning cells, then drop and refill them in one pass. The
        // removal is done here rather than through Grid::withoutPositions()
        // because a grid with an empty column is not a valid grid: the cells
        // must be refilled before one is constructed.
        $remove = [];
        foreach ($positions as [$reel, $row]) {
            $remove[$reel][$row] = true;
        }

        // Refill each reel from that reel's strip, not uniformly at random.
        // The strip carries the game's real symbol distribution, so a refilled
        // cell has the same probability as a spun one; picking uniformly would
        // quietly make cascades pay more than the maths intends.
        $reels = $context->configuration['reels'] ?? null;
        $strips = is_array($reels) ? ($reels['strips'] ?? null) : null;

        $cells = [];
        foreach ($grid->cells() as $reelIndex => $column) {
            $kept = [];
            foreach ($column as $rowIndex => $symbolId) {
                if (isset($remove[$reelIndex][$rowIndex])) {
                    // A removed source symbol refills as the wild instead of
                    // drawing from the strip: this is "the gold card becomes a
                    // joker wild when it is eliminated".
                    if ($this->becomesWild($symbolId, $context)) {
                        $kept[] = (string) $this->leavesWildSymbol;
                    }
                    continue;
                }
                $kept[] = $symbolId;
            }

            $height = $grid->rowsFor($reelIndex);
            $strip = is_array($strips) && isset($strips[$reelIndex]) && is_array($strips[$reelIndex])
                ? array_values(array_map('strval', $strips[$reelIndex]))
                : null;

            while (count($kept) < $height) {
                $kept[] = $strip === null || $strip === []
                    ? $context->rng->pick($grid->symbolSet()->ids())
                    : $strip[$context->rng->int(0, count($strip) - 1)];
            }

            $cells[$reelIndex] = $kept;
        }

        return Grid::fromCells($cells, $grid->symbolSet());
    }

    private function becomesWild(string $symbolId, FeatureContext $context): bool
    {
        if ($this->leavesWildSymbol === null || $this->leavesWild === []) {
            return false;
        }

        if (!in_array($symbolId, $this->leavesWild, true)) {
            return false;
        }

        return $this->leavesWildProbability >= 1.0
            || $context->rng->chance($this->leavesWildProbability);
    }
}
