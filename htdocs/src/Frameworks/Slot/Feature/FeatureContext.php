<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Frameworks\Slot\Evaluation\EvaluationResult;
use CasinoEngine\Frameworks\Slot\Grid\Grid;

/** Everything a feature is allowed to see when deciding what happens next. */
final class FeatureContext
{
    /**
     * @param array<string, mixed> $state Current feature state, already decoded
     * @param array<string, int> $configuration Game configuration values
     */
    public function __construct(
        public readonly Grid $grid,
        public readonly EvaluationResult $evaluation,
        public readonly array $state,
        public readonly RngServiceInterface $rng,
        public readonly bool $isFreeSpin,
        public readonly array $configuration = []
    ) {
    }

    public function config(string $key, int $default = 0): int
    {
        return (int) ($this->configuration[$key] ?? $default);
    }
}
