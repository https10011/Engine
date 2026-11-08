<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Evaluation;

/**
 * The outcome of evaluating one grid.
 *
 * `units` is the sum of every winning line's units, already multiplied by any
 * line multipliers. `totalMultiplier` is the per-spin multiplier contributed by
 * features such as a combo trail; it is applied to the units total once, at the
 * end, so the order of multiplication is explicit and testable.
 */
final class EvaluationResult implements \JsonSerializable
{
    /**
     * @param list<WinLine> $wins
     */
    public function __construct(
        public readonly array $wins,
        public readonly int $units,
        public readonly int $totalMultiplier = 1,
        public readonly int $scatterCount = 0,
        public readonly int $bonusCount = 0,
        /** @var array<string, mixed> */
        public readonly array $meta = []
    ) {
    }

    public static function empty(): self
    {
        return new self([], 0, 1, 0, 0);
    }

    public function isWin(): bool
    {
        return $this->units > 0;
    }

    public function multipliedUnits(): int
    {
        return $this->units * max(1, $this->totalMultiplier);
    }

    public function winCount(): int
    {
        return count($this->wins);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'wins'             => array_map(static fn (WinLine $w): array => $w->jsonSerialize(), $this->wins),
            'units'            => $this->units,
            'total_multiplier' => $this->totalMultiplier,
            'multiplied_units' => $this->multipliedUnits(),
            'scatter_count'    => $this->scatterCount,
            'bonus_count'      => $this->bonusCount,
            'meta'             => $this->meta,
        ];
    }
}
