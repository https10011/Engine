<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

use CasinoEngine\Core\Exception\PersistenceException;
use PDO;

/** Reads and writes user identity records. */
final class UserRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $userId): ?array
    {
        return $this->fetchOne(
            'SELECT id, username, phone, display_name, age, status, role, created_at, updated_at
             FROM users WHERE id = ?',
            [$userId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->fetchOne(
            'SELECT id, username, phone, display_name, age, status, role, created_at, updated_at
             FROM users WHERE username = ?',
            [$username]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByPhone(string $phone): ?array
    {
        return $this->fetchOne(
            'SELECT id, username, phone, display_name, age, status, role, created_at, updated_at
             FROM users WHERE phone = ?',
            [$phone]
        );
    }

    /** @return array<string, mixed>|null Credential row including the hash. */
    public function findCredentials(int $userId): ?array
    {
        return $this->fetchOne(
            'SELECT user_id, password_hash, algo FROM user_credentials WHERE user_id = ?',
            [$userId]
        );
    }

    public function create(string $username, string $displayName, string $now): int
    {
        $this->execute(
            'INSERT INTO users (username, display_name, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)',
            [$username, $displayName, 'active', $now, $now]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /** Creates a player account identified by phone rather than a public username. */
    public function createPlayer(
        string $internalUsername,
        string $phone,
        string $displayName,
        int $age,
        string $now
    ): int {
        $this->execute(
            'INSERT INTO users (username, phone, display_name, age, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$internalUsername, $phone, $displayName, $age, 'active', $now, $now]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateStatus(int $userId, string $status, string $now): void
    {
        $this->execute(
            'UPDATE users SET status = ?, updated_at = ? WHERE id = ?',
            [$status, $now, $userId]
        );
    }

    public function setCredentials(int $userId, string $hash, string $algo, string $now): void
    {
        $this->execute(
            'INSERT INTO user_credentials (user_id, password_hash, algo, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), algo = VALUES(algo), updated_at = VALUES(updated_at)',
            [$userId, $hash, $algo, $now]
        );
    }

    public function exists(int $userId): bool
    {
        return $this->fetchOne('SELECT 1 AS present FROM users WHERE id = ?', [$userId]) !== null;
    }

    /** Whether the account holds the administrator role, read from the database. */
    public function isAdmin(int $userId): bool
    {
        $row = $this->fetchOne("SELECT 1 AS present FROM users WHERE id = ? AND role = 'admin'", [$userId]);

        return $row !== null;
    }

    /**
     * Grants or revokes the administrator role.
     *
     * The role lives in the database rather than in a token claim, so revoking
     * it takes effect on the next request instead of when a token expires.
     */
    public function setRole(int $userId, string $role, string $now): void
    {
        $this->execute(
            'UPDATE users SET role = ?, updated_at = ? WHERE id = ?',
            [$role, $now, $userId]
        );
    }
}
