<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/**
 * Reads and writes API tokens.
 *
 * Only the SHA-256 of a token is ever stored, so a leaked database dump does
 * not hand an attacker a working credential. Lookup is by hash, which is what
 * makes the comparison safe: the presented token is hashed and the *hash* is
 * matched, so no comparison of the secret itself is ever performed.
 */
final class TokenRepository extends AbstractRepository
{
    public function create(int $userId, string $tokenHash, string $expiresAt, string $now): int
    {
        $this->execute(
            'INSERT INTO api_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$userId, $tokenHash, $expiresAt, $now]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Finds a live token by its hash.
     *
     * "Live" means not revoked and not expired as of `$now`; the comparison is
     * done in SQL so an expired row never leaves the database.
     *
     * @return array<string, mixed>|null
     */
    public function findLiveByHash(string $tokenHash, string $now): ?array
    {
        return $this->fetchOne(
            'SELECT id, user_id, expires_at
               FROM api_tokens
              WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > ?',
            [$tokenHash, $now]
        );
    }

    public function revokeByHash(string $tokenHash, string $now): int
    {
        return $this->execute(
            'UPDATE api_tokens SET revoked_at = ? WHERE token_hash = ? AND revoked_at IS NULL',
            [$now, $tokenHash]
        );
    }

    public function revokeAllForUser(int $userId, string $now): int
    {
        return $this->execute(
            'UPDATE api_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL',
            [$now, $userId]
        );
    }

    public function purgeExpired(string $now): int
    {
        return $this->execute('DELETE FROM api_tokens WHERE expires_at <= ?', [$now]);
    }
}
