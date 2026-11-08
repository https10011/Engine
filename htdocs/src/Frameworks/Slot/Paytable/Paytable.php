<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Paytable;

use CasinoEngine\Core\Exception\ConfigurationException;

/**
 * Payout table for one evaluation model.
 *
 * Values are integers in *paytable units*, not currency. A unit is defined by
 * the game's model (commonly "times the total bet divided by a line count"),
 * and converting units to money is the module's job when it builds its outcome
 * multiplier. Keeping the table unit-free lets the same table serve a
 * payline game and a ways game with different unit definitions.
 */
final class Paytable
{
    /**
     * @param array<string, array<int, int>> $payouts symbol id => count => units
     * @param array<string, array<int, int>> $scatterPayouts scatter id => count => units
     */
    public function __construct(
        private readonly array $payouts,
        private readonly array $scatterPayouts = []
    ) {
        foreach ($payouts as $symbolId => $byCount) {
            if ($byCount === []) {
                throw new ConfigurationException("Paytable for '$symbolId' has no entries.");
            }
            foreach ($byCount as $count => $units) {
                if ($count < 2) {
                    throw new ConfigurationException(
                        "Paytable entries must start at a run of 2; got $count for '$symbolId'."
                    );
                }
                if ($units < 0) {
                    throw new ConfigurationException("Paytable units cannot be negative for '$symbolId'.");
                }
            }
        }
    }

    /**
     * Units paid for a run of `$count` of `$symbolId`.
     *
     * Returns 0 when the symbol has no entry for that run length, which is the
     * correct answer for a run shorter than the symbol's minimum.
     */
    public function unitsFor(string $symbolId, int $count): int
    {
        if (!isset($this->payouts[$symbolId])) {
            return 0;
        }

        return $this->payouts[$symbolId][$count] ?? 0;
    }

    public function scatterUnitsFor(string $symbolId, int $count): int
    {
        return $this->scatterPayouts[$symbolId][$count] ?? 0;
    }

    /** True when the symbol appears anywhere in the table. */
    public function pays(string $symbolId): bool
    {
        return isset($this->payouts[$symbolId]);
    }

    public function paysScatter(string $symbolId): bool
    {
        return isset($this->scatterPayouts[$symbolId]);
    }

    /** Smallest run length that can pay for a symbol; null when it never pays. */
    public function minimumRun(string $symbolId): ?int
    {
        if (!isset($this->payouts[$symbolId])) {
            return null;
        }

        return min(array_keys($this->payouts[$symbolId]));
    }

    /** Highest single value in the table, in units. */
    public function highestUnit(): int
    {
        $highest = 0;
        foreach ($this->payouts as $byCount) {
            foreach ($byCount as $units) {
                $highest = max($highest, $units);
            }
        }
        foreach ($this->scatterPayouts as $byCount) {
            foreach ($byCount as $units) {
                $highest = max($highest, $units);
            }
        }

        return $highest;
    }

    /** @return list<string> */
    public function symbolIds(): array
    {
        return array_keys($this->payouts);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'line_payouts'    => $this->payouts,
            'scatter_payouts' => $this->scatterPayouts,
        ];
    }
}
