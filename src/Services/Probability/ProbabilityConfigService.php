<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Probability;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Persistence\Repository\ProbabilityRepository;

/**
 * The centralised probability configuration service.
 *
 * It stores the global profile and the per-game overrides, resolves the
 * effective policy for a game, validates and versions every change, and records
 * each change in the audit log. Round execution asks it once per round through
 * {@see self::effectiveFor()}, so a configuration change applies to newly
 * initialised rounds and never retroactively to a round already settled.
 *
 * The service caches what it reads within a process. The cache is populated on
 * first use and invalidated by any write from the same process; a separate
 * process (an administrator's browser session) picks up its change on its next
 * read, which is the correct behaviour for a request-scoped PHP runtime.
 */
final class ProbabilityConfigService implements ProbabilityConfigServiceInterface
{
    private ?ProbabilityProfile $globalCache = null;

    /** @var array<string, ProbabilityProfile>|null */
    private ?array $overrideCache = null;

    public function __construct(
        private readonly ProbabilityRepository $repository,
        private readonly ClockInterface $clock
    ) {
    }

    public function effectiveFor(string $gameId): ProbabilityProfile
    {
        $override = $this->overrides()[$gameId] ?? null;
        if ($override !== null && $override->isActive()) {
            return $override;
        }

        // An override that exists but is disabled falls back to global, which
        // is what "disable this override" means to an administrator.
        return $this->globalProfile();
    }

    public function globalProfile(): ProbabilityProfile
    {
        if ($this->globalCache === null) {
            $this->globalCache = $this->repository->findProfile(ProbabilityProfile::NAME_GLOBAL)
                ?? ProbabilityProfile::inactive();
        }

        return $this->globalCache;
    }

    public function overrides(): array
    {
        if ($this->overrideCache === null) {
            $this->overrideCache = [];
            foreach ($this->repository->allProfiles() as $profile) {
                if ($profile->gameId !== null) {
                    $this->overrideCache[$profile->gameId] = $profile;
                }
            }
        }

        return $this->overrideCache;
    }

    public function hasOverride(string $gameId): bool
    {
        return isset($this->overrides()[$gameId]);
    }

    /**
     * Saves the global profile.
     *
     * @param array<string, mixed> $values
     */
    public function saveGlobal(array $values, ?int $adminUserId, string $adminUsername): ProbabilityProfile
    {
        $values['name'] = ProbabilityProfile::NAME_GLOBAL;
        $values['game_id'] = null;
        $profile = ProbabilityProfile::fromArray($values, null);

        $this->persist($profile, $adminUserId, $adminUsername, 'global', 'Global probability profile updated');

        return $profile;
    }

    /**
     * Saves a per-game override.
     *
     * @param array<string, mixed> $values
     */
    public function saveOverride(
        string $gameId,
        array $values,
        ?int $adminUserId,
        string $adminUsername
    ): ProbabilityProfile {
        if (trim($gameId) === '') {
            throw new \CasinoEngine\Core\Exception\ValidationException('A game id is required for an override.');
        }

        $values['name'] = $gameId;
        $values['game_id'] = $gameId;
        $profile = ProbabilityProfile::fromArray($values, $gameId);

        $this->persist($profile, $adminUserId, $adminUsername, $gameId, "Override saved for '$gameId'");

        return $profile;
    }

    /** Removes an override so the game falls back to the global profile. */
    public function resetToGlobal(string $gameId, ?int $adminUserId, string $adminUsername): void
    {
        $existing = $this->repository->findProfile($gameId);

        $this->repository->deleteProfile($gameId);
        $this->repository->recordAudit(
            $adminUserId,
            $adminUsername,
            'override.reset',
            $gameId,
            [$gameId],
            $existing?->toArray(),
            null,
            "Override for '$gameId' reset to global settings",
            $existing === null ? 0 : $existing->version,
            $this->now()
        );

        $this->invalidate();
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 100): array
    {
        return $this->repository->auditLog($limit);
    }

    /** @return list<array<string, mixed>> */
    public function versions(string $profileName, int $limit = 50): array
    {
        return $this->repository->versions($profileName, $limit);
    }

    /**
     * Restores a previous version by re-saving its snapshot as a new version.
     *
     * History is never rewritten: the restore is itself a new version, so the
     * record of what was configured when is preserved.
     */
    public function restoreVersion(
        string $profileName,
        int $version,
        ?int $adminUserId,
        string $adminUsername
    ): ProbabilityProfile {
        foreach ($this->repository->versions($profileName, 200) as $row) {
            if ((int) $row['version'] !== $version) {
                continue;
            }

            $snapshot = self::decode((string) $row['snapshot']);
            $snapshot['enabled'] = true;

            $profile = ProbabilityProfile::fromArray(
                $snapshot,
                $profileName === ProbabilityProfile::NAME_GLOBAL ? null : $profileName
            );
            $profile = new ProbabilityProfile(
                $profile->name,
                $profile->gameId,
                $profile->anyWinTarget,
                $profile->netPositiveTarget,
                $profile->rtpTarget,
                $profile->jackpotProbability,
                $profile->bonusTriggerProbability,
                $profile->freeSpinTriggerProbability,
                $profile->payoutScale,
                $profile->jackpotTiers,
                true,
                $profile->maxResampleAttempts,
                $profile->version
            );

            $this->persist(
                $profile,
                $adminUserId,
                $adminUsername,
                $profileName,
                "Restored version $version of '$profileName'"
            );

            return $profile;
        }

        throw new \CasinoEngine\Core\Exception\ValidationException(
            "No version $version exists for profile '$profileName'."
        );
    }

    /** Drops the in-process cache so the next read sees the latest rows. */
    public function invalidate(): void
    {
        $this->globalCache = null;
        $this->overrideCache = null;
    }

    private function persist(
        ProbabilityProfile $profile,
        ?int $adminUserId,
        string $adminUsername,
        string $profileName,
        string $description
    ): void {
        $before = $this->repository->findProfile($profileName)?->toArray();

        $version = $this->repository->saveProfile(
            $profile,
            $adminUserId,
            $profile->gameId === null ? ['*'] : [$profile->gameId],
            $description,
            $this->now()
        );

        $this->repository->recordAudit(
            $adminUserId,
            $adminUsername,
            'configuration.applied',
            $profileName,
            $profile->gameId === null ? ['*'] : [$profile->gameId],
            $before,
            $profile->toArray(),
            $description,
            $version,
            $this->now()
        );

        $this->invalidate();
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }

    /** @return array<string, mixed> */
    private static function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
