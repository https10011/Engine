<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;

/**
 * Runs several evaluators over one grid and merges the results.
 *
 * This is how a game combines models: a ways game that also pays scatters, or
 * a line game with a cluster bonus. It exists because the target titles do
 * exactly this — Super Ace pays lines *and* scatters, and a game must be able
 * to compose those without the framework picking one model for it.
 */
final class CompositeEvaluator implements GridEvaluatorInterface
{
    /** @var list<GridEvaluatorInterface> */
    private array $evaluators;

    public function __construct(GridEvaluatorInterface ...$evaluators)
    {
        $this->evaluators = array_values($evaluators);
    }

    public function modelName(): string
    {
        return 'composite:' . implode('+', array_map(
            static fn (GridEvaluatorInterface $e): string => $e->modelName(),
            $this->evaluators
        ));
    }

    public function evaluate(Grid $grid, Paytable $paytable): EvaluationResult
    {
        $wins = [];
        $units = 0;
        $scatters = 0;
        $bonuses = 0;
        $meta = [];

        foreach ($this->evaluators as $evaluator) {
            $result = $evaluator->evaluate($grid, $paytable);

            foreach ($result->wins as $win) {
                $wins[] = $win;
            }

            $units += $result->units;
            $scatters = max($scatters, $result->scatterCount);
            $bonuses = max($bonuses, $result->bonusCount);
            $meta[$evaluator->modelName()] = $result->jsonSerialize();
        }

        return new EvaluationResult($wins, $units, 1, $scatters, $bonuses, $meta);
    }
}
