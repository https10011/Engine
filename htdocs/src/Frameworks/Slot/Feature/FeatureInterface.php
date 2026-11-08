<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Frameworks\Slot\Evaluation\EvaluationResult;
use CasinoEngine\Frameworks\Slot\Grid\Grid;

/**
 * A reusable slot feature.
 *
 * Features are composable and independent of the evaluator. A feature observes
 * a spin's evaluation and decides whether to start, continue or end, and
 * whether to transform the grid before the next evaluation. Free spins,
 * cascades and a combo-multiplier trail are all implemented this way, which is
 * what lets a game combine them (a game with free spins *and* cascades) without
 * a framework change.
 *
 * A feature never touches money: it reports transitions and multiplier
 * contributions, and the module turns those into an outcome multiplier.
 */
interface FeatureInterface
{
    public function type(): string;

    /**
     * @return FeatureStep Result of consulting the feature for this spin. The
     *         step carries any feature state that must survive to the session.
     */
    public function apply(FeatureContext $context): FeatureStep;
}
