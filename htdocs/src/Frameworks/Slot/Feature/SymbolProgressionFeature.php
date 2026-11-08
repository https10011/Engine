<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Multi-stage symbol progression: silver → gold → wild.
 *
 * Treasure of Aztec's "Wilds on the Way". A symbol lands framed; if it wins it
 * upgrades one stage; if the upgraded symbol wins again it upgrades once more,
 * and the final stage is the wild.
 *
 * The progression is tracked per *position*, not per symbol id, because two
 * silver symbols on the same grid are at different stages independently. A
 * position's stage advances by at most one per evaluation step, which is what
 * stops a single win from jumping straight from silver to wild.
 *
 * The stage survives the cascade chain (a position keeps its frame after the
 * cells above it fall) and is cleared between spins.
 */
final class SymbolProgressionFeature implements FeatureInterface, SpinLifecycleInterface
{
    /**
     * @param list<string> $stages Symbol ids, cheapest first; the last is the wild
     */
    public function __construct(
        private readonly array $stages,
        private readonly string $featureType = 'symbol_progression'
    ) {
        if (count($stages) < 2) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                'A symbol progression needs at least two stages.'
            );
        }
    }

    public function type(): string
    {
        return $this->featureType;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function onSpinStart(array $state): array
    {
        return ['stages' => []];
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        /** @var array<string, int> $positions */
        $positions = [];
        $raw = $context->state['stages'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                $positions[(string) $key] = (int) $value;
            }
        }

        $grid = $context->grid;
        $advanced = 0;

        if ($context->evaluation->isWin()) {
            foreach ($context->evaluation->wins as $win) {
                if ($win->kind === 'scatter') {
                    continue;
                }
                foreach ($win->positions as $position) {
                    [$reel, $row] = $position;
                    if (!$grid->has($reel, $row)) {
                        continue;
                    }

                    $key = $reel . ':' . $row;
                    $currentStage = $positions[$key] ?? null;
                    if ($currentStage === null) {
                        continue;
                    }

                    $nextStage = min(count($this->stages) - 1, $currentStage + 1);
                    if ($nextStage === $currentStage) {
                        continue;
                    }

                    $positions[$key] = $nextStage;
                    $grid = $grid->withSymbol($reel, $row, $this->stages[$nextStage]);
                    $advanced++;
                }
            }
        }

        // A new framed symbol may land on a cell that is not yet tracked. Only
        // the cheapest stage is seeded, so a symbol cannot appear pre-upgraded.
        foreach ($grid->cells() as $reel => $column) {
            foreach ($column as $row => $symbolId) {
                $key = $reel . ':' . $row;
                if (isset($positions[$key])) {
                    continue;
                }
                $stage = array_search($symbolId, $this->stages, true);
                if ($stage !== false && $stage > 0) {
                    $positions[$key] = 0;
                }
            }
        }

        if ($advanced === 0) {
            return new FeatureStep(['stages' => $positions], null, 1, false, [], ['advanced' => 0]);
        }

        return new FeatureStep(
            ['stages' => $positions],
            $grid,
            1,
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $advanced,
                ['advanced' => $advanced]
            )],
            ['advanced' => $advanced]
        );
    }
}
