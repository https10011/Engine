<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Slot\Evaluation\EvaluationResult;
use CasinoEngine\Frameworks\Slot\Grid\Grid;

/** The complete result of one spin, before it becomes a money outcome. */
final class SpinResult
{
    /**
     * @param list<EvaluationResult> $evaluations One per cascade step
     * @param list<FeatureTransition> $transitions
     */
    public function __construct(
        public readonly Grid $grid,
        public readonly array $evaluations,
        public readonly array $transitions,
        public readonly int $totalUnits,
        public readonly int $spinMultiplier,
        public readonly Ratio $payoutMultiplier,
        public readonly bool $isFreeSpin,
        public readonly SlotState $nextState,
        public readonly int $scatterCount = 0,
        public readonly int $bonusCount = 0
    ) {
    }

    public function isWin(): bool
    {
        return $this->payoutMultiplier->numerator() > 0;
    }

    public function cascadeCount(): int
    {
        return max(0, count($this->evaluations) - 1);
    }

    public function detail(): SlotOutcomeDetail
    {
        return new SlotOutcomeDetail(
            $this->grid->cells(),
            $this->evaluations,
            array_map(static fn (FeatureTransition $t): array => $t->toArray(), $this->transitions),
            $this->isFreeSpin,
            $this->nextState->freeSpinsRemaining(),
            $this->spinMultiplier,
            [
                'total_units'   => $this->totalUnits,
                'scatter_count' => $this->scatterCount,
                'bonus_count'   => $this->bonusCount,
                'cascade_count' => $this->cascadeCount(),
            ]
        );
    }
}
