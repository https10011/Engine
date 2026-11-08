<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/** Persists executed rounds — the game history. */
final class RoundRepository extends AbstractRepository
{
    /**
     * @param array<string, mixed> $detail
     * @param list<array<string, mixed>> $features
     */
    public function create(
        string $sessionId,
        int $userId,
        string $gameId,
        int $roundNumber,
        string $action,
        int $betMinor,
        int $payoutMinor,
        int $netMinor,
        int $multiplierNumerator,
        int $multiplierDenominator,
        array $detail,
        array $features,
        string $now
    ): int {
        $this->execute(
            'INSERT INTO game_rounds
                (session_id, user_id, game_id, round_number, action, bet_minor, payout_minor, net_minor,
                 payout_multiplier_num, payout_multiplier_den, detail, features, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $sessionId,
                $userId,
                $gameId,
                $roundNumber,
                $action,
                $betMinor,
                $payoutMinor,
                $netMinor,
                $multiplierNumerator,
                $multiplierDenominator,
                self::encodeJson($detail),
                self::encodeJson($features),
                $now,
            ]
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $roundId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM game_rounds WHERE id = ?',
            [$roundId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForSession(string $sessionId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        return $this->fetchAll(
            'SELECT id, round_number, action, bet_minor, payout_minor, net_minor, created_at
             FROM game_rounds WHERE session_id = ? ORDER BY round_number DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$sessionId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 50, int $offset = 0, ?string $gameId = null): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        if ($gameId !== null) {
            return $this->fetchAll(
                'SELECT id, session_id, game_id, round_number, action, bet_minor, payout_minor, net_minor, created_at
                 FROM game_rounds WHERE user_id = ? AND game_id = ?
                 ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
                [$userId, $gameId]
            );
        }

        return $this->fetchAll(
            'SELECT id, session_id, game_id, round_number, action, bet_minor, payout_minor, net_minor, created_at
             FROM game_rounds WHERE user_id = ?
             ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$userId]
        );
    }

    public function nextRoundNumber(string $sessionId): int
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(MAX(round_number), 0) + 1 AS next_number FROM game_rounds WHERE session_id = ?',
            [$sessionId]
        );

        return (int) ($row['next_number'] ?? 1);
    }

    /** @return array{rounds:int,wagered:int,returned:int} */
    public function totalsForUser(int $userId, ?string $gameId = null): array
    {
        $sql = 'SELECT COUNT(*) AS rounds, COALESCE(SUM(bet_minor),0) AS wagered, COALESCE(SUM(payout_minor),0) AS returned
                FROM game_rounds WHERE user_id = ?';
        $parameters = [$userId];

        if ($gameId !== null) {
            $sql .= ' AND game_id = ?';
            $parameters[] = $gameId;
        }

        $row = $this->fetchOne($sql, $parameters) ?? [];

        return [
            'rounds'   => (int) ($row['rounds'] ?? 0),
            'wagered'  => (int) ($row['wagered'] ?? 0),
            'returned' => (int) ($row['returned'] ?? 0),
        ];
    }
}
