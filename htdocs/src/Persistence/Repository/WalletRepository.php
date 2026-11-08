<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

use CasinoEngine\Core\Exception\PersistenceException;

/**
 * Wallet persistence.
 *
 * `lockByUserId()` issues `SELECT ... FOR UPDATE` and therefore must be called
 * inside an open transaction. Two concurrent rounds on the same wallet
 * serialise here: the second waits, then re-reads the balance that the first
 * committed, which is what prevents a double spend.
 */
final class WalletRepository extends AbstractRepository
{
    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        return $this->fetchOne(
            'SELECT id, user_id, currency, scale, balance_minor, version, created_at, updated_at
             FROM wallets WHERE user_id = ?',
            [$userId]
        );
    }

    /** @return array<string, mixed>|null */
    public function lockByUserId(int $userId): ?array
    {
        return $this->fetchOneForUpdate(
            'SELECT id, user_id, currency, scale, balance_minor, version, created_at, updated_at
             FROM wallets WHERE user_id = ?',
            [$userId]
        );
    }

    public function createForUser(int $userId, string $currency, int $scale, int $balanceMinor, string $now): int
    {
        $this->execute(
            'INSERT INTO wallets (user_id, currency, scale, balance_minor, version, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?)',
            [$userId, $currency, $scale, $balanceMinor, $now, $now]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Writes a new balance and bumps the version counter.
     *
     * The version check is a second line of defence: even if a caller forgot
     * the row lock, a stale write is rejected instead of silently overwriting
     * a newer balance.
     */
    public function updateBalance(int $walletId, int $expectedVersion, int $newBalanceMinor, string $now): bool
    {
        $affected = $this->execute(
            'UPDATE wallets SET balance_minor = ?, version = version + 1, updated_at = ?
             WHERE id = ? AND version = ?',
            [$newBalanceMinor, $now, $walletId, $expectedVersion]
        );

        return $affected === 1;
    }

    public function recordTransaction(
        int $walletId,
        int $userId,
        ?int $roundId,
        string $type,
        string $reason,
        int $amountMinor,
        int $balanceAfterMinor,
        string $reference,
        string $now
    ): int {
        $this->execute(
            'INSERT INTO wallet_transactions
                (wallet_id, user_id, round_id, type, reason, amount_minor, balance_after_minor, reference, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$walletId, $userId, $roundId, $type, $reason, $amountMinor, $balanceAfterMinor, $reference, $now]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function transactionsForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        return $this->fetchAll(
            'SELECT id, type, reason, amount_minor, balance_after_minor, reference, created_at
             FROM wallet_transactions WHERE user_id = ?
             ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$userId]
        );
    }

    public function transactionReferenceExists(string $reference): bool
    {
        return $this->fetchOne(
            'SELECT 1 AS present FROM wallet_transactions WHERE reference = ?',
            [$reference]
        ) !== null;
    }
}
