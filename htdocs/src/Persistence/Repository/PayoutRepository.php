<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/** Persists payouts. A round may have several (e.g. a win plus insurance). */
final class PayoutRepository extends AbstractRepository
{
    public function create(
        int $roundId,
        int $userId,
        int $amountMinor,
        string $currency,
        int $multiplierNumerator,
        int $multiplierDenominator,
        string $label,
        string $now
    ): int {
        $this->execute(
            'INSERT INTO payouts
                (round_id, user_id, amount_minor, currency, multiplier_num, multiplier_den, label, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$roundId, $userId, $amountMinor, $currency, $multiplierNumerator, $multiplierDenominator, $label, $now]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listForRound(int $roundId): array
    {
        return $this->fetchAll(
            'SELECT id, amount_minor, currency, multiplier_num, multiplier_den, label, created_at
             FROM payouts WHERE round_id = ? ORDER BY id',
            [$roundId]
        );
    }

    /** @return array{count:int,total:int,largest:int} */
    public function summaryForUser(int $userId): array
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS count, COALESCE(SUM(amount_minor),0) AS total, COALESCE(MAX(amount_minor),0) AS largest
             FROM payouts WHERE user_id = ?',
            [$userId]
        ) ?? [];

        return [
            'count'   => (int) ($row['count'] ?? 0),
            'total'   => (int) ($row['total'] ?? 0),
            'largest' => (int) ($row['largest'] ?? 0),
        ];
    }
}
