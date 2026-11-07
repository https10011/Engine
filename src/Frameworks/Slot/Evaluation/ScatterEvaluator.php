<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolType;

/**
 * Scatter-only evaluation.
 *
 * Scatters pay by total count anywhere in the grid, regardless of position.
 * This evaluator is a standalone model for games where scatters are the only
 * win (a "scatter pays" game), and it is also usable as a component by the
 * composite evaluator to add scatter pays on top of a line or ways game.
 */
final class ScatterEvaluator implements GridEvaluatorInterface
{
    public function modelName(): string
    {
        return 'scatter';
    }

    public function evaluate(Grid $grid, Paytable $paytable): EvaluationResult
    {
        return self::evaluateScatters($grid, $paytable);
    }

    /** Scatter-only evaluation, reusable by other evaluators. */
    public static function evaluateScatters(Grid $grid, Paytable $paytable): EvaluationResult
    {
        $wins = [];
        $totalUnits = 0;
        $scatterCount = 0;
        $bonusCount = 0;

        /** @var array<string, list<array{0:int,1:int}>> $bySymbol */
        $bySymbol = [];

        foreach ($grid->cells() as $reel => $column) {
            foreach ($column as $row => $symbolId) {
                $symbol = $grid->symbolSet()->get($symbolId);

                if ($symbol->type === SymbolType::Scatter) {
                    $scatterCount++;
                    $bySymbol[$symbolId][] = [$reel, $row];
                } elseif ($symbol->type === SymbolType::Bonus) {
                    $bonusCount++;
                }
            }
        }

        foreach ($bySymbol as $symbolId => $positions) {
            $count = count($positions);
            $units = $paytable->scatterUnitsFor($symbolId, $count);
            if ($units <= 0) {
                continue;
            }

            $wins[] = new WinLine('scatter', $symbolId, $count, $units, $positions);
            $totalUnits += $units;
        }

        return new EvaluationResult($wins, $totalUnits, 1, $scatterCount, $bonusCount);
    }
}
