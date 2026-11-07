<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Frameworks\Slot\Grid\Grid;

/**
 * A feature's answer for one spin.
 *
 * `grid` is non-null only when the feature transforms the grid (a cascade
 * refill, a wild upgrade). `multiplier` is a per-spin contribution that
 * multiplies the whole spin's win.
 */
final class FeatureStep
{
    /**
     * @param array<string, mixed> $state Feature state after this spin
     * @param list<FeatureTransition> $transitions
     */
    public function __construct(
        public readonly array $state,
        public readonly ?Grid $grid = null,
        public readonly int $multiplier = 1,
        public readonly bool $continues = false,
        public readonly array $transitions = [],
        /** @var array<string, mixed> */
        public readonly array $meta = []
    ) {
    }

    /** @param array<string, mixed> $state */
    public static function idle(array $state = []): self
    {
        return new self($state);
    }
}
