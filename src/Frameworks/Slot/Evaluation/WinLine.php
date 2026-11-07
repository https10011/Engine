<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

/**
 * One winning combination found during evaluation.
 *
 * A line win, a way win, a cluster win and a scatter win all reduce to this
 * shape, which is what lets the payout layer treat them uniformly without
 * caring which evaluator produced them.
 */
final class WinLine implements \JsonSerializable
{
    /**
     * @param list<array{0:int,1:int}> $positions
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $symbolId,
        public readonly int $count,
        public readonly int $units,
        public readonly array $positions = [],
        public readonly ?int $paylineIndex = null,
        public readonly int $multiplier = 1
    ) {
    }

    public function unitsWithMultiplier(): int
    {
        return $this->units * max(1, $this->multiplier);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'kind'          => $this->kind,
            'symbol'        => $this->symbolId,
            'count'         => $this->count,
            'units'         => $this->units,
            'multiplier'    => $this->multiplier,
            'payline_index' => $this->paylineIndex,
            'positions'     => array_map(
                static fn (array $p): array => ['reel' => $p[0], 'row' => $p[1]],
                $this->positions
            ),
        ];
    }
}
