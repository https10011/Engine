<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolType;

/**
 * Pay-anywhere evaluation.
 *
 * A symbol pays when at least `$minimumCount` of it appear anywhere on the
 * grid, regardless of adjacency. This is the model the "symbols pay anywhere on
 * the screen" titles use (Gates of Olympus 1000, Sweet Bonanza 1000), and it is
 * genuinely different from a cluster pay, which requires the symbols to be
 * connected.
 *
 * The paytable is read by count, like a scatter table, so a game configures its
 * entries with keys 8, 9, 10, ... rather than run lengths.
 */
final class PayAnywhereEvaluator implements GridEvaluatorInterface
{
    public function __construct(private readonly int $minimumCount = 8)
    {
        if ($minimumCount < 2) {
            throw new ConfigurationException('A pay-anywhere win needs at least two symbols.');
        }
    }

    public function modelName(): string
    {
        return 'pay_anywhere';
    }

    public function minimumCount(): int
    {
        return $this->minimumCount;
    }

    public function evaluate(Grid $grid, Paytable $paytable): EvaluationResult
    {
        $wins = [];
        $totalUnits = 0;
        $scatterCount = 0;
        $bonusCount = 0;

        /** @var array<string, list<array{0:int,1:int}>> $positions */
        $positions = [];

        foreach ($grid->cells() as $reel => $column) {
            foreach ($column as $row => $symbolId) {
                $symbol = $grid->symbolSet()->get($symbolId);

                if ($symbol->type === SymbolType::Scatter) {
                    $scatterCount++;
                    continue;
                }

                if ($symbol->type === SymbolType::Bonus) {
                    $bonusCount++;
                    continue;
                }

                if ($symbol->type === SymbolType::Blank) {
                    continue;
                }

                $positions[$symbolId][] = [$reel, $row];
            }
        }

        foreach ($positions as $symbolId => $cells) {
            $count = count($cells);
            if ($count < $this->minimumCount) {
                continue;
            }

            if ($grid->symbolSet()->get($symbolId)->isScatter()) {
                continue;
            }

            // A multiplier symbol is not a paying symbol; it contributes a
            // multiplier through a feature instead. The paytable decides, so a
            // symbol with no entry is simply skipped.
            $units = $paytable->unitsFor($symbolId, $count);
            if ($units <= 0) {
                continue;
            }

            $wins[] = new WinLine('anywhere', $symbolId, $count, $units, $cells);
            $totalUnits += $units;
        }

        return new EvaluationResult($wins, $totalUnits, 1, $scatterCount, $bonusCount);
    }
}
