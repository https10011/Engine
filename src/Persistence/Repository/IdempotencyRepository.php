<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/**
 * Stores idempotency keys and their completed responses.
 *
 * The uniqueness of `(user_id, game_id, key_hash)` is what actually prevents a
 * double debit: two concurrent requests carrying the same key race on the
 * insert, exactly one wins, and the loser reads the stored response instead of
 * playing a second round.
 */
final class IdempotencyRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function find(int $userId, string $gameId, string $keyHash): ?array
    {
        return $this->fetchOne(
            'SELECT id, request_fingerprint, status, response, round_id, expires_at
             FROM idempotency_keys WHERE user_id = ? AND game_id = ? AND key_hash = ?',
            [$userId, $gameId, $keyHash]
        );
    }

    /** @return array<string, mixed>|null */
    public function lock(int $userId, string $gameId, string $keyHash): ?array
    {
        return $this->fetchOneForUpdate(
            'SELECT id, request_fingerprint, status, response, round_id, expires_at
             FROM idempotency_keys WHERE user_id = ? AND game_id = ? AND key_hash = ?',
            [$userId, $gameId, $keyHash]
        );
    }

    public function claim(
        int $userId,
        string $gameId,
        string $keyHash,
        string $fingerprint,
        string $now,
        string $expiresAt
    ): bool {
        try {
            $this->execute(
                'INSERT INTO idempotency_keys
                    (user_id, game_id, key_hash, request_fingerprint, status, created_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$userId, $gameId, $keyHash, $fingerprint, 'pending', $now, $expiresAt]
            );

            return true;
        } catch (\PDOException $e) {
            // A duplicate key means another request already claimed this key.
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $response */
    public function complete(
        int $userId,
        string $gameId,
        string $keyHash,
        array $response,
        ?int $roundId
    ): void {
        $this->execute(
            "UPDATE idempotency_keys SET status = 'completed', response = ?, round_id = ?
             WHERE user_id = ? AND game_id = ? AND key_hash = ?",
            [self::encodeJson($response), $roundId, $userId, $gameId, $keyHash]
        );
    }

    public function fail(int $userId, string $gameId, string $keyHash): void
    {
        $this->execute(
            "UPDATE idempotency_keys SET status = 'failed'
             WHERE user_id = ? AND game_id = ? AND key_hash = ?",
            [$userId, $gameId, $keyHash]
        );
    }

    /** Removes a claim so a genuinely failed attempt can be retried. */
    public function release(int $userId, string $gameId, string $keyHash): void
    {
        $this->execute(
            'DELETE FROM idempotency_keys WHERE user_id = ? AND game_id = ? AND key_hash = ?',
            [$userId, $gameId, $keyHash]
        );
    }

    public function purgeExpired(string $now): int
    {
        return $this->execute('DELETE FROM idempotency_keys WHERE expires_at <= ?', [$now]);
    }
}
