<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolType;

/**
 * Ways-to-win evaluation (the 1024-ways / 243-ways family).
 *
 * A win is a run of the same symbol from reel 0 where *every* matching cell on
 * each reel counts, and the win is paid once per combination. The framework
 * reports the run length and the number of ways; multiplying units by the way
 * count is done here so the paytable stays a simple per-run table.
 *
 * Wilds substitute on every reel. A run of all wilds pays the wild's own entry
 * when the paytable defines one.
 */
final class WaysEvaluator implements GridEvaluatorInterface
{
    public function modelName(): string
    {
        return 'ways';
    }

    public function evaluate(Grid $grid, Paytable $paytable): EvaluationResult
    {
        $wins = [];
        $totalUnits = 0;

        $candidates = $this->candidateSymbols($grid, $paytable);

        foreach ($candidates as $symbolId) {
            $result = $this->evaluateSymbol($grid, $paytable, $symbolId);
            if ($result === null) {
                continue;
            }
            $wins[] = $result;
            $totalUnits += $result->unitsWithMultiplier();
        }

        return new EvaluationResult(
            $wins,
            $totalUnits,
            1,
            $this->countScatters($grid),
            $this->countBonuses($grid)
        );
    }

    /**
     * Symbols that could anchor a run: every payable symbol that appears on
     * reel 0, including the wild.
     *
     * @return list<string>
     */
    private function candidateSymbols(Grid $grid, Paytable $paytable): array
    {
        $candidates = [];

        foreach ($grid->cells()[0] as $symbolId) {
            $symbol = $grid->symbolSet()->get($symbolId);
            if ($symbol->type === SymbolType::Scatter || $symbol->type === SymbolType::Bonus) {
                continue;
            }
            if ($symbol->isWild()) {
                if ($paytable->pays($symbolId)) {
                    $candidates[] = $symbolId;
                }
                continue;
            }
            if ($paytable->pays($symbolId)) {
                $candidates[$symbolId] = $symbolId;
            }
        }

        return array_values($candidates);
    }

    private function evaluateSymbol(Grid $grid, Paytable $paytable, string $symbolId): ?WinLine
    {
        $ways = 1;
        $runLength = 0;
        $positions = [];
        $multiplier = 1;

        for ($reel = 0; $reel < $grid->reelCount(); $reel++) {
            $matchesOnReel = 0;

            for ($row = 0; $row < $grid->rowsFor($reel); $row++) {
                $cellSymbol = $grid->symbol($reel, $row);

                $matches = $cellSymbol->id === $symbolId
                    || ($cellSymbol->isWild() && $symbolId !== $cellSymbol->id);

                if ($matches) {
                    $matchesOnReel++;
                    $positions[] = [$reel, $row];
                    $multiplier = max($multiplier, $cellSymbol->winMultiplier);
                }
            }

            if ($matchesOnReel === 0) {
                break;
            }

            $ways *= $matchesOnReel;
            $runLength++;
        }

        if ($runLength < 2) {
            return null;
        }

        $units = $paytable->unitsFor($symbolId, $runLength);
        if ($units <= 0) {
            return null;
        }

        // Ways multiply the run's units: this is the defining rule of the model.
        return new WinLine(
            'ways',
            $symbolId,
            $runLength,
            $units * $ways,
            $positions,
            null,
            $multiplier
        );
    }

    private function countScatters(Grid $grid): int
    {
        $count = 0;
        foreach ($grid->cells() as $column) {
            foreach ($column as $symbolId) {
                if ($grid->symbolSet()->get($symbolId)->isScatter()) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function countBonuses(Grid $grid): int
    {
        $count = 0;
        foreach ($grid->cells() as $column) {
            foreach ($column as $symbolId) {
                if ($grid->symbolSet()->get($symbolId)->type === SymbolType::Bonus) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
