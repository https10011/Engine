<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

use CasinoEngine\Frameworks\Slot\Grid\Grid;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;

/**
 * The contract every win-evaluation model implements.
 *
 * The framework ships payline, ways, cluster and scatter evaluators. A game
 * with a structure none of them express implements this interface and is
 * plugged into the slot engine without any change to the framework — which is
 * the extension point the research into the target titles demanded.
 */
interface GridEvaluatorInterface
{
    public function evaluate(Grid $grid, Paytable $paytable): EvaluationResult;

    /** Identifies the model, for logs, metadata and simulation reports. */
    public function modelName(): string;
}
