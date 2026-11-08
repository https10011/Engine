<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

/**
 * Optional lifecycle hook for a feature whose state must reset per spin.
 *
 * Most feature state belongs to the *session* — free spins remaining, a
 * persistent multiplier meter — and must survive between spins. Some belongs to
 * the *spin*: the cascade index, the reel heights an expansion produced, the
 * positions already drawn for a symbol multiplier. Those must start empty on
 * every new spin, and a feature that had to detect "is this a new spin?" from
 * inside `apply()` would be guessing.
 *
 * The slot engine therefore calls {@see self::onSpinStart()} once, before the
 * first evaluation, for every feature that implements this interface. A feature
 * that does not implement it keeps whatever state it stored, which is the
 * correct default for session-scoped state.
 */
interface SpinLifecycleInterface
{
    /**
     * @param array<string, mixed> $state The feature state carried into this spin
     * @return array<string, mixed> The state the spin should actually begin with
     */
    public function onSpinStart(array $state): array;
}
