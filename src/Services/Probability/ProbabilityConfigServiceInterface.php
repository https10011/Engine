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

    /**
     * Saves the global profile as a new version.
     *
     * @param array<string, mixed> $values
     */
    public function saveGlobal(array $values, ?int $adminUserId, string $adminUsername): ProbabilityProfile;

    /**
     * Saves a per-game override, which then takes precedence over the global
     * profile for that game alone.
     *
     * @param array<string, mixed> $values
     */
    public function saveOverride(
        string $gameId,
        array $values,
        ?int $adminUserId,
        string $adminUsername
    ): ProbabilityProfile;

    /** Removes a game's override so it inherits the global profile again. */
    public function resetToGlobal(string $gameId, ?int $adminUserId, string $adminUsername): void;

    /**
     * The configuration history for a profile, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function versions(string $profileName, int $limit = 50): array;

    /**
     * Restores an earlier version by saving it again as a new version, so the
     * record of what was configured when is never rewritten.
     */
    public function restoreVersion(
        string $profileName,
        int $version,
        ?int $adminUserId,
        string $adminUsername
    ): ProbabilityProfile;

    /**
     * The audit trail, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function auditLog(int $limit = 100): array;

    /** Drops the in-process cache so the next read sees the stored profile. */
    public function invalidate(): void;
}
