<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

use CasinoEngine\Services\Probability\ProbabilityProfile;

/**
 * Storage for probability profiles, versions and audit records.
 *
 * Every statement is prepared. Profiles are keyed by name: `global` is the
 * single global profile and a per-game profile is named after the game id, so a
 * lookup never has to guess which level it is reading.
 */
final class ProbabilityRepository extends AbstractRepository
{
    public function findProfile(string $name): ?ProbabilityProfile
    {
        $row = $this->fetchOne(
            'SELECT * FROM probability_profiles WHERE name = ?',
            [$name]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<ProbabilityProfile> */
    public function allProfiles(): array
    {
        $rows = $this->fetchAll('SELECT * FROM probability_profiles ORDER BY game_id IS NULL DESC, name ASC');

        return array_map(fn (array $row): ProbabilityProfile => $this->hydrate($row), $rows);
    }

    /**
     * Inserts or updates a profile and returns its new version number.
     *
     * The version is incremented on every save, and a snapshot is written to
     * `probability_versions` so history is append-only rather than overwritten.
     *
     * @param list<string> $affectedGames
     */
    public function saveProfile(
        ProbabilityProfile $profile,
        ?int $adminUserId,
        array $affectedGames,
        string $description,
        string $now
    ): int {
        $existing = $this->fetchOne('SELECT version FROM probability_profiles WHERE name = ?', [$profile->name]);
        $version = $existing === null ? 1 : ((int) $existing['version']) + 1;

        $this->execute(
            'INSERT INTO probability_profiles
                (name, game_id, any_win_target, net_positive_target, rtp_target, jackpot_probability,
                 bonus_trigger_probability, free_spin_trigger_probability, payout_scale, jackpot_tiers,
                 enabled, max_resample_attempts, version, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                game_id = VALUES(game_id),
                any_win_target = VALUES(any_win_target),
                net_positive_target = VALUES(net_positive_target),
                rtp_target = VALUES(rtp_target),
                jackpot_probability = VALUES(jackpot_probability),
                bonus_trigger_probability = VALUES(bonus_trigger_probability),
                free_spin_trigger_probability = VALUES(free_spin_trigger_probability),
                payout_scale = VALUES(payout_scale),
                jackpot_tiers = VALUES(jackpot_tiers),
                enabled = VALUES(enabled),
                max_resample_attempts = VALUES(max_resample_attempts),
                version = VALUES(version),
                updated_at = VALUES(updated_at)',
            [
                $profile->name,
                $profile->gameId,
                $profile->anyWinTarget,
                $profile->netPositiveTarget,
                $profile->rtpTarget,
                $profile->jackpotProbability,
                $profile->bonusTriggerProbability,
                $profile->freeSpinTriggerProbability,
                $profile->payoutScale,
                self::encodeJson($profile->jackpotTiers),
                $profile->enabled ? 1 : 0,
                $profile->maxResampleAttempts,
                $version,
                $now,
                $now,
            ]
        );

        $snapshot = $profile->toArray();
        $snapshot['version'] = $version;

        $this->execute(
            'INSERT INTO probability_versions (profile_name, version, snapshot, created_by, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$profile->name, $version, self::encodeJson($snapshot), $adminUserId, $now]
        );

        $this->recordAudit(
            $adminUserId,
            'system',
            $existing === null ? 'profile.created' : 'profile.updated',
            $profile->name,
            $affectedGames,
            $existing,
            $snapshot,
            $description,
            $version,
            $now
        );

        return $version;
    }

    public function deleteProfile(string $name): void
    {
        $this->execute('DELETE FROM probability_profiles WHERE name = ?', [$name]);
    }

    /**
     * Records a configuration change.
     *
     * @param list<string> $affectedGames
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function recordAudit(
        ?int $adminUserId,
        string $adminUsername,
        string $action,
        string $profileName,
        array $affectedGames,
        ?array $before,
        ?array $after,
        string $description,
        int $version,
        string $now
    ): void {
        $this->execute(
            'INSERT INTO probability_audit_log
                (admin_user_id, admin_username, action, profile_name, affected_games, before_state,
                 after_state, description, version, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $adminUserId,
                $adminUsername,
                $action,
                $profileName,
                self::encodeJson($affectedGames),
                $before === null ? null : self::encodeJson($before),
                $after === null ? null : self::encodeJson($after),
                $description,
                $version,
                $now,
            ]
        );
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));

        return $this->fetchAll(
            'SELECT id, admin_user_id, admin_username, action, profile_name, affected_games,
                    before_state, after_state, description, version, created_at
             FROM probability_audit_log ORDER BY id DESC LIMIT ' . $limit
        );
    }

    /** @return list<array<string, mixed>> */
    public function versions(string $profileName, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return $this->fetchAll(
            'SELECT id, profile_name, version, snapshot, created_by, created_at
             FROM probability_versions WHERE profile_name = ? ORDER BY version DESC LIMIT ' . $limit,
            [$profileName]
        );
    }

    public function recordJackpot(
        string $gameId,
        ?int $userId,
        string $tier,
        float $probability,
        float $payoutMultiplier,
        string $now
    ): void {
        $this->execute(
            'INSERT INTO jackpot_events (game_id, user_id, tier, probability, payout_multiplier, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$gameId, $userId, $tier, $probability, $payoutMultiplier, $now]
        );
    }

    /** @return list<array<string, mixed>> */
    public function jackpotHistory(string $gameId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return $this->fetchAll(
            'SELECT id, game_id, user_id, tier, probability, payout_multiplier, created_at
             FROM jackpot_events WHERE game_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$gameId]
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ProbabilityProfile
    {
        $tiers = self::decodeJson(isset($row['jackpot_tiers']) ? (string) $row['jackpot_tiers'] : null);

        /** @var array<string, array{probability:float,payout_multiplier:float}> $normalised */
        $normalised = [];
        foreach ($tiers as $tier => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $normalised[(string) $tier] = [
                'probability'       => (float) ($definition['probability'] ?? 0.0),
                'payout_multiplier' => (float) ($definition['payout_multiplier'] ?? 0.0),
            ];
        }

        return new ProbabilityProfile(
            (string) $row['name'],
            isset($row['game_id']) ? (string) $row['game_id'] : null,
            self::nullableFloat($row['any_win_target'] ?? null),
            self::nullableFloat($row['net_positive_target'] ?? null),
            self::nullableFloat($row['rtp_target'] ?? null),
            self::nullableFloat($row['jackpot_probability'] ?? null),
            self::nullableFloat($row['bonus_trigger_probability'] ?? null),
            self::nullableFloat($row['free_spin_trigger_probability'] ?? null),
            self::nullableFloat($row['payout_scale'] ?? null),
            $normalised,
            (bool) ($row['enabled'] ?? false),
            (int) ($row['max_resample_attempts'] ?? 200),
            (int) ($row['version'] ?? 0)
        );
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
