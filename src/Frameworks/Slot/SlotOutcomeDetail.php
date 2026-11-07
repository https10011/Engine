<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot;

use CasinoEngine\Core\Contract\GameOutcomeDetailInterface;
use CasinoEngine\Frameworks\Slot\Evaluation\EvaluationResult;

/**
 * The slot-specific result payload.
 *
 * Carries the grid, every winning line, the feature chain and the scatter
 * count, so a frontend can animate exactly what the server decided without
 * recomputing anything. This is the contract that keeps outcome logic on the
 * server.
 */
final class SlotOutcomeDetail implements GameOutcomeDetailInterface
{
    /**
     * @param list<list<string>> $grid
     * @param list<EvaluationResult> $evaluations One per cascade step
     * @param list<array<string, mixed>> $transitions
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly array $grid,
        private readonly array $evaluations,
        private readonly array $transitions = [],
        private readonly bool $freeSpin = false,
        private readonly int $freeSpinsRemaining = 0,
        private readonly int $totalMultiplier = 1,
        private readonly array $meta = []
    ) {
    }

    public function type(): string
    {
        return $this->freeSpin ? 'free_spin' : 'spin';
    }

    public function toArray(): array
    {
        return [
            'grid'                => $this->grid,
            'evaluations'         => array_map(
                static fn (EvaluationResult $e): array => $e->jsonSerialize(),
                $this->evaluations
            ),
            'transitions'         => $this->transitions,
            'free_spin'           => $this->freeSpin,
            'free_spins_remaining' => $this->freeSpinsRemaining,
            'total_multiplier'    => $this->totalMultiplier,
            'meta'                => $this->meta,
        ];
    }
}
