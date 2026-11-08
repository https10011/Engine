<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Position-keyed multipliers that double when a position wins again.
 *
 * Sugar Rush 1000's mechanic: whenever a winning symbol explodes, its spot is
 * *marked*. A second explosion on the same spot adds a multiplier starting at
 * x2 and doubling up to a cap (x1,024). The multiplier applies to every win
 * formed on top of that position.
 *
 * `$persistAcrossSpins` keeps the marked grid alive between spins, which is
 * what makes it a *feature-scoped* multiplier rather than a per-spin one: in
 * Sugar Rush the marked spots and multipliers remain in place until the round
 * ends. When the round ends (`isFreeSpin` false and the pool empty) the module
 * clears the state, so the multipliers never leak into the next paid spin.
 */
final class PositionMultiplierFeature implements FeatureInterface
{
    public function __construct(
        private readonly int $startMultiplier = 2,
        private readonly int $cap = 1024,
        private readonly bool $persistAcrossSpins = true
    ) {
    }

    public function type(): string
    {
        return 'position_multiplier';
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        /** @var array<string, int> $grid */
        $grid = $this->decode($context->state['positions'] ?? []);

        // Every position that wins this step either gains a mark or doubles.
        $touched = 0;
        if ($context->evaluation->isWin()) {
            foreach ($context->evaluation->wins as $win) {
                if ($win->kind === 'scatter') {
                    continue;
                }
                foreach ($win->positions as $position) {
                    $key = $position[0] . ':' . $position[1];
                    $current = $grid[$key] ?? 1;
                    $grid[$key] = min($this->cap, $current === 1 ? $this->startMultiplier : $current * 2);
                    $touched++;
                }
            }
        }

        // The multiplier this step contributes is the highest one active on a
        // winning position, so a win formed on top of a x16 mark pays x16.
        $applied = $context->evaluation->isWin() ? $this->highestActive($grid, $context) : 1;

        $transitions = [];
        if ($touched > 0) {
            $transitions[] = new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $applied,
                ['marked' => count($grid), 'applied' => $applied]
            );
        }

        // The marked grid survives between spins only inside a free-spin
        // feature; a paid spin clears it, so a mark can never leak from one
        // paid spin into the next and quietly multiply a later win.
        $state = ($this->persistAcrossSpins && $context->isFreeSpin) ? ['positions' => $grid] : [];

        return new FeatureStep($state, null, $applied, false, $transitions, ['applied' => $applied]);
    }

    /**
     * @param array<string, int> $grid
     */
    private function highestActive(array $grid, FeatureContext $context): int
    {
        $highest = 1;
        foreach ($context->evaluation->wins as $win) {
            if ($win->kind === 'scatter') {
                continue;
            }
            foreach ($win->positions as $position) {
                $highest = max($highest, $grid[$position[0] . ':' . $position[1]] ?? 1);
            }
        }

        return $highest;
    }

    /**
     * @param mixed $raw
     * @return array<string, int>
     */
    private function decode(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $grid = [];
        foreach ($raw as $key => $value) {
            $grid[(string) $key] = (int) $value;
        }

        return $grid;
    }
}
