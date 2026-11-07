<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;

/**
 * Classic payline evaluation.
 *
 * A payline is a path of one cell per reel. A win is a run of the same symbol
 * starting at reel 0, with wilds substituting for regular symbols. This is the
 * model used by Super Ace and most of the 3x5 JILI and Pragmatic titles.
 *
 * Two rules are configurable because real games differ on both:
 *
 *  - `$leftToRightOnly`: almost all payline games pay only runs anchored at
 *    reel 0, but some also pay right-to-left runs. Left-to-right is the default
 *    because paying both ways without saying so would double a game's RTP.
 *  - `$wildSubstitutesForScatter`: off by default. A wild that also counts as a
 *    scatter is unusual and would otherwise inflate scatter frequency.
 */
final class PaylineEvaluator implements GridEvaluatorInterface
{
    /**
     * @param list<list<int>> $paylines Each payline is one row index per reel.
     */
    public function __construct(
        private readonly array $paylines,
        private readonly bool $leftToRightOnly = true,
        private readonly bool $wildSubstitutesForScatter = false
    ) {
        foreach ($paylines as $index => $payline) {
            if ($payline === []) {
                throw new ConfigurationException("Payline $index is empty.");
            }
        }
    }

    public function modelName(): string
    {
        return 'payline';
    }

    /** @return list<list<int>> */
    public function paylines(): array
    {
        return $this->paylines;
    }

    public function evaluate(Grid $grid, Paytable $paytable): EvaluationResult
    {
        $wins = [];
        $totalUnits = 0;

        foreach ($this->paylines as $index => $payline) {
            foreach ($this->evaluateLine($grid, $paytable, $payline, $index, false) as $win) {
                $wins[] = $win;
                $totalUnits += $win->unitsWithMultiplier();
            }

            if (!$this->leftToRightOnly) {
                foreach ($this->evaluateLine($grid, $paytable, $payline, $index, true) as $win) {
                    $wins[] = $win;
                    $totalUnits += $win->unitsWithMultiplier();
                }
            }
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
     * @param list<int> $payline
     * @return list<WinLine>
     */
    private function evaluateLine(
        Grid $grid,
        Paytable $paytable,
        array $payline,
        int $paylineIndex,
        bool $reversed
    ): array {
        $reelCount = min(count($payline), $grid->reelCount());
        if ($reelCount < 2) {
            return [];
        }

        $reelOrder = range(0, $reelCount - 1);
        if ($reversed) {
            $reelOrder = array_reverse($reelOrder);
        }

        // Resolve the symbol of each cell along the line, skipping any cell the
        // line does not reach (a ragged grid shorter than the payline).
        $cells = [];
        foreach ($reelOrder as $reel) {
            $row = $payline[$reel];
            if (!$grid->has($reel, $row)) {
                break;
            }
            $cells[] = ['reel' => $reel, 'row' => $row, 'symbol' => $grid->symbol($reel, $row)];
        }

        if (count($cells) < 2) {
            return [];
        }

        $first = $cells[0]['symbol'];

        // A scatter never anchors a line win; a wild may, but only if the
        // paytable actually pays it, otherwise a line of wilds would pay zero.
        if ($first->isScatter() || $first->type === \CasinoEngine\Frameworks\Slot\Symbol\SymbolType::Bonus) {
            return [];
        }

        $targetId = $first->isWild() && $paytable->pays($first->id)
            ? $first->id
            : null;

        $runLength = 1;
        $runSymbolId = $first->id;
        $positions = [[$cells[0]['reel'], $cells[0]['row']]];
        $multiplier = $first->winMultiplier;

        for ($i = 1; $i < count($cells); $i++) {
            $symbol = $cells[$i]['symbol'];

            $matches = false;
            if ($symbol->isWild()) {
                // A wild continues the run whatever the run symbol is, unless
                // the run is itself a wild line (handled by targetId).
                $matches = true;
                if ($targetId === null && !$first->isWild()) {
                    $runSymbolId = $first->id;
                }
            } elseif ($targetId !== null) {
                // The run is anchored on a wild: only the same wild continues it.
                $matches = $symbol->id === $targetId;
            } else {
                $matches = $symbol->id === $first->id;
            }

            if (!$matches) {
                break;
            }

            $runLength++;
            $positions[] = [$cells[$i]['reel'], $cells[$i]['row']];
            $multiplier = max($multiplier, $symbol->winMultiplier);
        }

        // A wild-anchored run pays as a wild only when the paytable defines it;
        // otherwise the run is worth whatever the wild substitutes for, which
        // the game expresses by paying the wild itself.
        if ($first->isWild() && !$paytable->pays($first->id)) {
            return [];
        }

        $units = $paytable->unitsFor($runSymbolId, $runLength);
        if ($units <= 0) {
            return [];
        }

        return [new WinLine(
            'line',
            $runSymbolId,
            $runLength,
            $units,
            $positions,
            $paylineIndex,
            $multiplier
        )];
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
                if ($grid->symbolSet()->get($symbolId)->type
                    === \CasinoEngine\Frameworks\Slot\Symbol\SymbolType::Bonus) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
