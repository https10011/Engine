<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Probability;

/**
 * Resolves the effective probability policy for a game.
 *
 * The resolution order is fixed and testable:
 *
 *   1. a per-game override, when one exists and is enabled;
 *   2. the global profile;
 *   3. no constraint at all.
 *
 * Every game therefore resolves its policy through one place, which is what the
 * directive asks for: one centralised architecture, not eighteen unrelated
 * implementations. A game's *evaluator* still owns its own mechanics — the
 * policy never rewrites a paytable or invents a payout, it only sets targets the
 * governor is allowed to steer towards.
 */
interface ProbabilityConfigServiceInterface
{
    /**
     * The policy that actually applies to a game.
     *
     * Always returns a profile; `isActive()` is false when nothing is
     * configured, which is the engine's default behaviour.
     */
    public function effectiveFor(string $gameId): ProbabilityProfile;

    /** The global profile, or an inactive one when none is configured. */
    public function globalProfile(): ProbabilityProfile;

    /** @return array<string, ProbabilityProfile> game id => override */
    public function overrides(): array;

    /** True when a game has its own override rather than inheriting global. */
    public function hasOverride(string $gameId): bool;
}
