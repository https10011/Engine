<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolType;

/**
 * Cluster-pays evaluation.
 *
 * A win is a group of at least `$minimumCluster` identical symbols that are
 * connected orthogonally — the model used by cluster games such as the Mahjong
 * family. The paytable is read by *cluster size*, so a game configures its
 * table with keys 5, 6, 7, ... rather than run lengths.
 *
 * Wilds join a cluster. A cluster that consists only of wilds pays the wild's
 * own entry when the paytable defines one.
 */
final class ClusterEvaluator implements GridEvaluatorInterface
{
    public function __construct(
        private readonly int $minimumCluster = 5,
        private readonly bool $diagonalConnects = false
    ) {
        if ($minimumCluster < 2) {
            throw new ConfigurationException('A cluster must contain at least two symbols.');
        }
    }

    public function modelName(): string
    {
        return 'cluster';
    }

    public function evaluate(Grid $grid, Paytable $paytable): EvaluationResult
    {
        $wins = [];
        $totalUnits = 0;
        $visited = [];

        foreach ($grid->cells() as $reel => $column) {
            foreach ($column as $row => $symbolId) {
                if (isset($visited["$reel:$row"])) {
                    continue;
                }

                $symbol = $grid->symbolSet()->get($symbolId);
                if ($symbol->type === SymbolType::Scatter || $symbol->type === SymbolType::Bonus) {
                    continue;
                }

                $cluster = $this->floodFill($grid, $reel, $row, $symbolId);

                foreach ($cluster as [$clusterReel, $clusterRow]) {
                    $visited["$clusterReel:$clusterRow"] = true;
                }

                if (count($cluster) < $this->minimumCluster) {
                    continue;
                }

                if (!$paytable->pays($symbolId)) {
                    continue;
                }

                $units = $paytable->unitsFor($symbolId, count($cluster));
                if ($units <= 0) {
                    continue;
                }

                $wins[] = new WinLine(
                    'cluster',
                    $symbolId,
                    count($cluster),
                    $units,
                    $cluster
                );
                $totalUnits += $units;
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
     * @return list<array{0:int,1:int}>
     */
    private function floodFill(Grid $grid, int $startReel, int $startRow, string $symbolId): array
    {
        $stack = [[$startReel, $startRow]];
        $seen = ["$startReel:$startRow" => true];
        $cluster = [];

        $neighbours = [[1, 0], [-1, 0], [0, 1], [0, -1]];
        if ($this->diagonalConnects) {
            $neighbours = array_merge($neighbours, [[1, 1], [1, -1], [-1, 1], [-1, -1]]);
        }

        while ($stack !== []) {
            [$reel, $row] = array_pop($stack);
            $cluster[] = [$reel, $row];

            foreach ($neighbours as [$deltaReel, $deltaRow]) {
                $nextReel = $reel + $deltaReel;
                $nextRow = $row + $deltaRow;

                if (!$grid->has($nextReel, $nextRow)) {
                    continue;
                }

                $key = "$nextReel:$nextRow";
                if (isset($seen[$key])) {
                    continue;
                }

                $candidate = $grid->symbol($nextReel, $nextRow);
                $matches = $candidate->id === $symbolId
                    || ($candidate->isWild() && $symbolId !== $candidate->id);

                if (!$matches) {
                    continue;
                }

                $seen[$key] = true;
                $stack[] = [$nextReel, $nextRow];
            }
        }

        return $cluster;
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
