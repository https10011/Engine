<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Framework\Slot;

use CasinoEngine\Frameworks\Slot\Evaluation\ClusterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Symbol\Symbol;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;
use PHPUnit\Framework\TestCase;

/**
 * The four win-evaluation models.
 *
 * Each is exercised on a hand-built grid so the expected result is known
 * exactly; a test that only asserted "units > 0" would pass on a broken
 * evaluator that paid everything.
 */
final class EvaluationModelsTest extends TestCase
{
    private function symbols(): SymbolSet
    {
        return new SymbolSet([
            Symbol::regular('A', 'Ace', 1),
            Symbol::regular('K', 'King', 1),
            Symbol::regular('Q', 'Queen', 1),
            Symbol::wild('W', 'Wild', 1),
            Symbol::scatter('S', 'Scatter', 1),
        ]);
    }

    private function paytable(): Paytable
    {
        return new Paytable(
            ['A' => [6 => 500, 5 => 200, 3 => 100, 2 => 10], 'K' => [3 => 50, 2 => 5], 'Q' => [3 => 20], 'W' => [3 => 200]],
            ['S' => [3 => 30]]
        );
    }

    /** @param list<list<string>> $cells */
    private function grid(array $cells): Grid
    {
        return Grid::fromCells($cells, $this->symbols());
    }

    public function testPaylinePaysARunFromReelZero(): void
    {
        $evaluator = new PaylineEvaluator([[0, 0, 0]]);
        $grid = $this->grid([['A', 'K', 'Q'], ['A', 'Q', 'K'], ['A', 'K', 'Q']]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(100, $result->units);
        self::assertCount(1, $result->wins);
        self::assertSame('A', $result->wins[0]->symbolId);
        self::assertSame(3, $result->wins[0]->count);
    }

    public function testPaylineDoesNotPayARunNotAnchoredAtReelZero(): void
    {
        $evaluator = new PaylineEvaluator([[0, 0, 0]]);
        $grid = $this->grid([['K', 'K', 'K'], ['A', 'Q', 'K'], ['A', 'K', 'Q']]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(0, $result->units);
    }

    public function testWildSubstitutesInAPaylineRun(): void
    {
        $evaluator = new PaylineEvaluator([[0, 0, 0]]);
        $grid = $this->grid([['A', 'K', 'Q'], ['W', 'K', 'Q'], ['A', 'K', 'Q']]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(100, $result->units);
        self::assertSame('A', $result->wins[0]->symbolId);
    }

    public function testPaylineOnlyPaysTheLongestRunPerLine(): void
    {
        $evaluator = new PaylineEvaluator([[0, 0, 0]]);
        // Three Aces: the run pays 100, not 100 + 10 for the pair prefix.
        $grid = $this->grid([['A', 'K', 'Q'], ['A', 'K', 'Q'], ['A', 'K', 'Q']]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(100, $result->units);
        self::assertCount(1, $result->wins);
    }

    public function testWaysEvaluatorCountsEveryMatchingReelSymbol(): void
    {
        // Three reels, each showing two Aces: 2*2*2 = 8 ways of three Aces.
        $evaluator = new WaysEvaluator();
        $grid = $this->grid([['A', 'A'], ['A', 'A'], ['A', 'A']]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(100 * 8, $result->units);
    }

    public function testWaysEvaluatorIgnoresScattersWhenCountingWays(): void
    {
        $evaluator = new WaysEvaluator();
        // Each reel shows two Aces and a scatter. The scatter is not a ways
        // symbol, so the count is 2*2*2 = 8 ways of three Aces.
        $grid = $this->grid([['A', 'A', 'S'], ['A', 'A', 'S'], ['A', 'A', 'S']]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(100 * 8, $result->units);
    }

    public function testClusterEvaluatorPaysAConnectedBlock(): void
    {
        $evaluator = new ClusterEvaluator(5);
        // Five Aces in an L shape form one connected cluster of five, which
        // the paytable pays at its 5-entry.
        $grid = $this->grid([
            ['A', 'A', 'K'],
            ['A', 'A', 'K'],
            ['A', 'K', 'Q'],
        ]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(200, $result->units);
        self::assertSame('A', $result->wins[0]->symbolId);
        self::assertSame(5, $result->wins[0]->count);
    }

    public function testClusterEvaluatorIgnoresClustersBelowTheMinimum(): void
    {
        $evaluator = new ClusterEvaluator(5);
        $grid = $this->grid([
            ['A', 'K', 'Q'],
            ['A', 'K', 'Q'],
            ['K', 'K', 'Q'],
        ]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(0, $result->units);
    }

    public function testScatterEvaluatorCountsScattersAnywhere(): void
    {
        $evaluator = new ScatterEvaluator('S');
        $grid = $this->grid([['S', 'A'], ['K', 'S'], ['S', 'Q']]);

        $result = $evaluator->evaluate($grid, $this->paytable());

        self::assertSame(3, $result->scatterCount);
        self::assertSame(30, $result->units);
    }

    public function testCompositeEvaluatorSumsItsModels(): void
    {
        $composite = new CompositeEvaluator(
            new PaylineEvaluator([[0, 0, 0]]),
            new ScatterEvaluator('S')
        );

        $grid = $this->grid([['A', 'S'], ['A', 'K'], ['A', 'S']]);

        $result = $composite->evaluate($grid, $this->paytable());

        // The line pays 100 and the two scatters pay nothing (3 needed).
        self::assertSame(100, $result->units);
        self::assertSame(2, $result->scatterCount);
    }

    public function testGridRejectsAnUnknownSymbol(): void
    {
        $this->expectException(\CasinoEngine\Core\Exception\ConfigurationException::class);

        Grid::fromCells([['A', 'Z']], $this->symbols());
    }
}
