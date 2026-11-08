<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/** Persists game sessions, including their serialised state. */
final class SessionRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function findById(string $sessionId): ?array
    {
        return $this->fetchOne(
            'SELECT id, user_id, game_id, status, round_count, state, created_at, updated_at, expires_at
             FROM game_sessions WHERE id = ?',
            [$sessionId]
        );
    }

    /** @return array<string, mixed>|null */
    public function lockById(string $sessionId): ?array
    {
        return $this->fetchOneForUpdate(
            'SELECT id, user_id, game_id, status, round_count, state, created_at, updated_at, expires_at
             FROM game_sessions WHERE id = ?',
            [$sessionId]
        );
    }

    /** @param array<string, mixed> $state */
    public function create(
        string $sessionId,
        int $userId,
        string $gameId,
        array $state,
        string $now,
        string $expiresAt
    ): void {
        $this->execute(
            'INSERT INTO game_sessions (id, user_id, game_id, status, round_count, state, created_at, updated_at, expires_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)',
            [$sessionId, $userId, $gameId, 'active', self::encodeJson($state), $now, $now, $expiresAt]
        );
    }

    /** @param array<string, mixed> $state */
    public function updateState(string $sessionId, array $state, int $roundCount, string $now, string $expiresAt): void
    {
        $this->execute(
            'UPDATE game_sessions SET state = ?, round_count = ?, updated_at = ?, expires_at = ? WHERE id = ?',
            [self::encodeJson($state), $roundCount, $now, $expiresAt, $sessionId]
        );
    }

    public function updateStatus(string $sessionId, string $status, string $now): void
    {
        $this->execute(
            'UPDATE game_sessions SET status = ?, updated_at = ? WHERE id = ?',
            [$status, $now, $sessionId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 200));

        return $this->fetchAll(
            'SELECT id, game_id, status, round_count, created_at, updated_at, expires_at
             FROM game_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT ' . $limit,
            [$userId]
        );
    }

    /** @return list<string> Session ids that have passed their expiry. */
    public function expiredSessionIds(string $now, int $limit = 500): array
    {
        $limit = max(1, min($limit, 5000));

        return array_map(
            static fn (array $row): string => (string) $row['id'],
            $this->fetchAll(
                "SELECT id FROM game_sessions WHERE status = 'active' AND expires_at <= ? LIMIT " . $limit,
                [$now]
            )
        );
    }
}
