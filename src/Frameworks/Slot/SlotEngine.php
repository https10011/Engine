<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Slot\Evaluation\EvaluationResult;
use CasinoEngine\Frameworks\Slot\Evaluation\GridEvaluatorInterface;
use CasinoEngine\Frameworks\Slot\Feature\FeatureContext;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Reel\ReelConfiguration;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;

/**
 * The reusable slot spin engine.
 *
 * Composes four independent pieces — reels, paytable, evaluator and features —
 * into one spin. It carries no game-specific rules: a module supplies the
 * pieces, and this class performs the mechanics every slot shares.
 *
 * The spin algorithm, in order:
 *
 *  1. if free spins are active the spin is free, and the module reports a zero
 *     wager multiplier; otherwise it costs one bet;
 *  2. spin the reels (or keep the grid left behind by a cascade);
 *  3. evaluate the grid;
 *  4. consult each feature; a feature may transform the grid and ask for
 *     another evaluation (a cascade), which loops;
 *  5. sum the units across every evaluation, apply the combined multiplier,
 *     and convert units into a payout multiplier.
 *
 * Units become a payout multiplier through `$unitDivisor`, the game's own
 * definition of what one paytable unit is worth relative to the bet (commonly
 * the number of lines). Keeping that divisor explicit is what stops the
 * framework from assuming every game is a payline game.
 */
final class SlotEngine
{
    /**
     * @param list<FeatureInterface> $features
     */
    public function __construct(
        private readonly ReelConfiguration $reels,
        private readonly SymbolSet $symbols,
        private readonly Paytable $paytable,
        private readonly GridEvaluatorInterface $evaluator,
        private readonly array $features = [],
        private readonly int $unitDivisor = 1,
        private readonly int $maximumFeatureIterations = 50
    ) {
    }

    public function symbolSet(): SymbolSet
    {
        return $this->symbols;
    }

    public function paytable(): Paytable
    {
        return $this->paytable;
    }

    public function evaluator(): GridEvaluatorInterface
    {
        return $this->evaluator;
    }

    public function reels(): ReelConfiguration
    {
        return $this->reels;
    }

    public function unitDivisor(): int
    {
        return max(1, $this->unitDivisor);
    }

    /**
     * Runs one spin.
     *
     * @param array<string, mixed> $configuration
     * @param list<int>|null $forcedPositions Test hook: stop each reel exactly
     *        here instead of drawing, which is how a known grid is built.
     */
    public function spin(
        SlotState $state,
        RngServiceInterface $rng,
        array $configuration = [],
        ?array $forcedPositions = null
    ): SpinResult {
        $isFreeSpin = $state->isInFreeSpins();

        $grid = $forcedPositions === null
            ? Grid::spin($this->reels, $this->symbols, $rng)
            : Grid::fromStopPositions($this->reels, $this->symbols, $forcedPositions);

        /** @var list<EvaluationResult> $evaluations */
        $evaluations = [];
        /** @var list<FeatureTransition> $transitions */
        $transitions = [];
        $totalUnits = 0;
        $spinMultiplier = 1;

        // Feature states are tracked per feature so a cascade loop advances a
        // feature without discarding the others' progress.
        $pending = [];
        foreach ($this->features as $feature) {
            $pending[$feature->type()] = $state->featureState($feature->type());
        }

        $iterations = 0;

        do {
            $iterations++;
            if ($iterations > $this->maximumFeatureIterations) {
                // A chain that will not settle is a configuration bug; failing
                // loudly beats hanging the request.
                throw new GameRuleException(
                    'The spin did not settle within the maximum number of steps.',
                    ['iterations' => $iterations]
                );
            }

            $evaluation = $this->evaluator->evaluate($grid, $this->paytable);
            $evaluations[] = $evaluation;
            $totalUnits += $evaluation->units;

            $continueChain = false;

            foreach ($this->features as $feature) {
                $type = $feature->type();
                $context = new FeatureContext(
                    $grid,
                    $evaluation,
                    $pending[$type] ?? [],
                    $rng,
                    $isFreeSpin,
                    $configuration
                );

                $step = $feature->apply($context);
                $pending[$type] = $step->state;

                foreach ($step->transitions as $transition) {
                    $transitions[] = $transition;
                }

                if ($step->multiplier > 1) {
                    $spinMultiplier *= $step->multiplier;
                }

                if ($step->grid !== null) {
                    $grid = $step->grid;
                }

                if ($step->continues) {
                    $continueChain = true;
                }
            }
        } while ($continueChain);

        $finalEvaluation = $evaluations[count($evaluations) - 1] ?? EvaluationResult::empty();

        // A combo or free-spin multiplier applies to the whole spin, once, at
        // the end. Applying it per evaluation would compound it across
        // cascades, which is a different and much richer game.
        $payoutMultiplier = $this->unitsToMultiplier($totalUnits * $spinMultiplier);

        $nextState = $state->withFeatures($pending, $grid->cells(), $state->spinsPlayed() + 1);

        return new SpinResult(
            $grid,
            $evaluations,
            $transitions,
            $totalUnits,
            $spinMultiplier,
            $payoutMultiplier,
            $isFreeSpin,
            $nextState,
            $finalEvaluation->scatterCount,
            $finalEvaluation->bonusCount
        );
    }

    /**
     * Converts paytable units into a payout multiplier.
     *
     * `units / unitDivisor` is the fraction of the bet won, so a line paying
     * 100 units in a 20-line game pays 5x the total bet.
     */
    public function unitsToMultiplier(int $units): Ratio
    {
        if ($units <= 0) {
            return Ratio::zero();
        }

        return Ratio::of($units, $this->unitDivisor);
    }
}
