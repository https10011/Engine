<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/** Persists individual wagers. A round may have several (e.g. split hands). */
final class BetRepository extends AbstractRepository
{
    public function create(int $roundId, int $userId, int $amountMinor, string $currency, string $label, string $now): int
    {
        $this->execute(
            'INSERT INTO bets (round_id, user_id, amount_minor, currency, label, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$roundId, $userId, $amountMinor, $currency, $label, $now]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listForRound(int $roundId): array
    {
        return $this->fetchAll(
            'SELECT id, amount_minor, currency, label, created_at FROM bets WHERE round_id = ? ORDER BY id',
            [$roundId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        return $this->fetchAll(
            'SELECT b.id, b.round_id, b.amount_minor, b.currency, b.label, b.created_at
             FROM bets b WHERE b.user_id = ? ORDER BY b.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$userId]
        );
    }
}
