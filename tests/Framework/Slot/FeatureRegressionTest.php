<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Framework\Slot;

use CasinoEngine\Frameworks\Slot\Evaluation\EvaluationResult;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureContext;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Symbol\Symbol;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;
use CasinoEngine\Services\Rng\RngFactory;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the feature bugs the simulation harness exposed.
 *
 * Each of these failed in a way that was invisible in a single spin and only
 * showed up as an impossible RTP or a request that never returned. They are
 * kept as named tests so the behaviour cannot quietly regress.
 */
final class FeatureRegressionTest extends TestCase
{
    private SymbolSet $symbols;

    protected function setUp(): void
    {
        $this->symbols = new SymbolSet([
            Symbol::regular('A', 'Ace', 1),
            Symbol::regular('K', 'King', 1),
            Symbol::wild('W', 'Wild', 1),
            Symbol::scatter('S', 'Scatter', 1),
        ]);
    }

    private function paytable(): Paytable
    {
        return new Paytable(['A' => [3 => 100], 'K' => [3 => 50], 'W' => [3 => 200]], ['S' => [3 => 10]]);
    }

    /** @param list<list<string>> $cells */
    private function grid(array $cells): Grid
    {
        return Grid::fromCells($cells, $this->symbols);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $state
     */
    private function winContext(Grid $grid, array $state = [], bool $freeSpin = false, array $config = []): FeatureContext
    {
        $evaluation = (new PaylineEvaluator([[0, 0, 0]]))->evaluate($grid, $this->paytable());

        return new FeatureContext($grid, $evaluation, $state, RngFactory::seeded(1), $freeSpin, $config);
    }

    /**
     * A free spin must not ask for another evaluation of the same grid.
     *
     * When it did, a single winning line was paid once per remaining free
     * spin, which is what produced a 600% RTP on a paytable worth 50%.
     */
    public function testFreeSpinNeverContinuesTheSpinLoop(): void
    {
        $feature = new FreeSpinsFeature([3 => 10]);
        $grid = $this->grid([['A', 'K'], ['A', 'K'], ['A', 'K']]);

        $step = $feature->apply($this->winContext($grid, ['remaining' => 5, 'awarded' => 10], true));

        self::assertFalse(
            $step->continues,
            'A free spin must end the current spin; the next spin is a new action.'
        );
        self::assertSame(4, $step->state['remaining'], 'The pool is drawn down by exactly one.');
    }

    public function testPaidSpinTriggersAPoolWithoutContinuing(): void
    {
        $feature = new FreeSpinsFeature([3 => 10]);
        $grid = $this->grid([['S', 'K'], ['S', 'K'], ['S', 'K']]);

        $step = $feature->apply($this->winContext($grid));

        self::assertFalse($step->continues);
        self::assertSame(10, $step->state['remaining']);
    }

    public function testFreeSpinPoolIsCappedWhenRetriggering(): void
    {
        $feature = new FreeSpinsFeature([3 => 100], retrigger: true, retriggerCap: 20);
        $grid = $this->grid([['S', 'K'], ['S', 'K'], ['S', 'K']]);

        $step = $feature->apply($this->winContext($grid, ['remaining' => 1, 'awarded' => 20], true));

        self::assertLessThanOrEqual(20, $step->state['remaining']);
        self::assertLessThanOrEqual(20, $step->state['awarded']);
    }

    /**
     * A cascade must not re-apply itself to the grid it just produced.
     *
     * Setting `continues` made the loop re-run the cascade on its own output,
     * which never settles; the engine threw "did not settle within the maximum
     * number of steps". A cascade signals a new grid instead, and the engine
     * treats a new grid as the reason to evaluate again.
     */
    public function testCascadeReturnsANewGridWithoutSelfContinuing(): void
    {
        $feature = new CascadeFeature(10);
        $grid = $this->grid([['A', 'K'], ['A', 'K'], ['A', 'K']]);

        $step = $feature->apply($this->winContext($grid));

        self::assertNotNull($step->grid, 'A cascade replaces the grid.');
        self::assertFalse($step->continues, 'The cascade must not ask to run itself again.');
    }

    public function testCascadeLeavesAHandledGridAlone(): void
    {
        $feature = new CascadeFeature(10);
        $grid = $this->grid([['A', 'K'], ['K', 'A'], ['K', 'K']]);

        $step = $feature->apply($this->winContext($grid, ['index' => 0]));

        self::assertNull($step->grid);
        self::assertFalse($step->continues);
    }

    public function testCascadeStopsAtTheCap(): void
    {
        $feature = new CascadeFeature(3);
        $grid = $this->grid([['A', 'K'], ['A', 'K'], ['A', 'K']]);

        $step = $feature->apply($this->winContext($grid, ['index' => 3]));

        self::assertFalse($step->continues);
        self::assertTrue((bool) ($step->meta['capped'] ?? false));
    }

    /**
     * A cascade must refill from the reel strip, not uniformly over symbols.
     *
     * Refilling uniformly gives rare symbols the same chance as common ones,
     * which silently makes cascades pay far more than the reel maths intends.
     */
    public function testCascadeRefillsFromTheReelStrip(): void
    {
        $feature = new CascadeFeature(10);
        // A strip made entirely of 'A' means a refill can only ever be 'A'.
        $config = ['reels' => ['strips' => [['A'], ['A'], ['A']]]];
        $grid = $this->grid([['A', 'K'], ['A', 'K'], ['A', 'K']]);

        $step = $feature->apply($this->winContext($grid, [], false, $config));

        self::assertNotNull($step->grid);
        // Every cell of the refilled grid must be 'A' or the surviving 'K',
        // and never a symbol absent from the strip.
        foreach ($step->grid->cells() as $column) {
            foreach ($column as $symbolId) {
                self::assertContains($symbolId, ['A', 'K']);
            }
        }
    }

    public function testCascadeRefillPreservesReelHeight(): void
    {
        $feature = new CascadeFeature(10);
        $grid = $this->grid([['A', 'K', 'K'], ['A', 'K', 'K'], ['A', 'K', 'K']]);

        $step = $feature->apply($this->winContext($grid));

        self::assertNotNull($step->grid);
        for ($reel = 0; $reel < 3; $reel++) {
            self::assertSame(3, $step->grid->rowsFor($reel));
        }
    }
}
