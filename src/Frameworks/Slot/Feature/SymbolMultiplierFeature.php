<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolType;

/**
 * Random multiplier symbols whose values are combined at the end of a sequence.
 *
 * Two lifetimes exist in the target titles and both are supported here:
 *
 *  - `$roundScoped = false` (Gates of Olympus 1000 base game, Wild Bounty
 *    Showdown): values are **combined at the end of a tumble sequence** and
 *    applied to that sequence's win.
 *  - `$roundScoped = true` (Gates of Olympus 1000 bonus): a multiplier symbol's
 *    value is **added to the round's running total**, which is then applied to
 *    any win whenever a new symbol hits, and survives between free spins.
 *
 * A multiplier symbol is a grid symbol that is not itself paid; the paytable
 * simply has no entry for it, so the evaluator skips it. This feature reads its
 * positions from the grid and draws a value from the configured ladder.
 */
final class SymbolMultiplierFeature implements FeatureInterface, SpinLifecycleInterface
{
    /**
     * @param list<int> $values Multiplier values the symbol may carry
     */
    public function __construct(
        private readonly string $symbolId,
        private readonly array $values = [2, 3, 5, 10, 25, 50, 100, 250, 500, 1000],
        private readonly bool $roundScoped = false
    ) {
    }

    public function type(): string
    {
        return 'symbol_multiplier';
    }

    /**
     * A round-scoped multiplier survives between spins of a feature; a
     * sequence-scoped one starts empty on every spin. The positions already
     * drawn are per-spin either way, so a symbol is never double-counted.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function onSpinStart(array $state): array
    {
        $state['sequence'] = 0;
        $state['drawn'] = [];

        if (!$this->roundScoped) {
            $state['accumulated'] = 0;
        }

        return $state;
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $state = $context->state;
        $accumulated = (int) ($state['accumulated'] ?? 0);
        $sequence = (int) ($state['sequence'] ?? 0);

        // A multiplier symbol must be drawn once, not once per evaluation step.
        // Without this the same symbol sitting on the grid would be re-drawn on
        // every cascade step and its value counted repeatedly, which would
        // inflate the win far beyond the configured ladder.
        $drawnPositions = $this->decodePositions($state['drawn'] ?? []);

        $drawn = 0;
        foreach ($context->grid->cells() as $reel => $column) {
            foreach ($column as $row => $symbolId) {
                if ($symbolId !== $this->symbolId) {
                    continue;
                }
                $key = $reel . ':' . $row;
                if (isset($drawnPositions[$key])) {
                    continue;
                }
                $drawnPositions[$key] = true;

                $value = $this->values === []
                    ? 1
                    : $this->values[$context->rng->int(0, count($this->values) - 1)];
                $sequence += $value;
                $drawn++;
            }
        }

        $transitions = [];
        if ($drawn > 0) {
            $transitions[] = new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $sequence,
                ['drawn' => $drawn, 'sequence' => $sequence]
            );
        }

        if ($this->roundScoped) {
            // The value is added to the round's running total and applied to any
            // win this step produces.
            $accumulated += $sequence;
            $applied = $context->evaluation->isWin() ? max(1, $accumulated) : 1;

            return new FeatureStep(
                ['accumulated' => $accumulated, 'sequence' => 0, 'drawn' => array_keys($drawnPositions)],
                null,
                $applied,
                false,
                $transitions,
                ['applied' => $applied, 'accumulated' => $accumulated]
            );
        }

        // Sequence-scoped: the values collected across the tumble sequence are
        // summed and applied to the sequence's win once.
        $applied = $sequence > 0 ? max(1, $sequence) : 1;

        return new FeatureStep(
            ['sequence' => $sequence, 'accumulated' => 0, 'drawn' => array_keys($drawnPositions)],
            null,
            $applied,
            false,
            $transitions,
            ['applied' => $applied, 'sequence' => $sequence]
        );
    }

    /**
     * @param mixed $raw
     * @return array<string, bool>
     */
    private function decodePositions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $positions = [];
        foreach ($raw as $value) {
            $positions[(string) $value] = true;
        }

        return $positions;
    }

    /** Whether a symbol id is a multiplier symbol. */
    public function handles(string $symbolId): bool
    {
        return $symbolId === $this->symbolId;
    }

    /** Kept so a caller can assert the symbol is not a payable one. */
    public static function isPayable(\CasinoEngine\Frameworks\Slot\Symbol\Symbol $symbol): bool
    {
        return $symbol->type !== SymbolType::Bonus && $symbol->type !== SymbolType::Blank;
    }
}
