<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/** Daily aggregate statistics, derived from committed rounds. */
final class StatisticsRepository extends AbstractRepository
{
    public function record(
        int $userId,
        string $gameId,
        string $day,
        int $wageredMinor,
        int $returnedMinor,
        bool $isWin,
        int $payoutMinor,
        string $now
    ): void {
        // A single upsert keeps the aggregate correct under concurrency without
        // a read-modify-write race in PHP.
        $this->execute(
            'INSERT INTO statistics_daily
                (user_id, game_id, day, rounds, wagered_minor, returned_minor, wins, losses, largest_win_minor, updated_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                rounds = rounds + 1,
                wagered_minor = wagered_minor + VALUES(wagered_minor),
                returned_minor = returned_minor + VALUES(returned_minor),
                wins = wins + VALUES(wins),
                losses = losses + VALUES(losses),
                largest_win_minor = GREATEST(largest_win_minor, VALUES(largest_win_minor)),
                updated_at = VALUES(updated_at)',
            [
                $userId,
                $gameId,
                $day,
                $wageredMinor,
                $returnedMinor,
                $isWin ? 1 : 0,
                $isWin ? 0 : 1,
                $payoutMinor,
                $now,
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public function forUserGameDay(int $userId, string $gameId, string $day): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM statistics_daily WHERE user_id = ? AND game_id = ? AND day = ?',
            [$userId, $gameId, $day]
        );
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId, int $limit = 30): array
    {
        $limit = max(1, min($limit, 365));

        return $this->fetchAll(
            'SELECT game_id, day, rounds, wagered_minor, returned_minor, wins, losses, largest_win_minor
             FROM statistics_daily WHERE user_id = ? ORDER BY day DESC, game_id LIMIT ' . $limit,
            [$userId]
        );
    }

    /** @return array{rounds:int,wagered:int,returned:int,wins:int,losses:int} */
    public function totalsForUser(int $userId): array
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(SUM(rounds),0) AS rounds, COALESCE(SUM(wagered_minor),0) AS wagered,
                    COALESCE(SUM(returned_minor),0) AS returned, COALESCE(SUM(wins),0) AS wins,
                    COALESCE(SUM(losses),0) AS losses
             FROM statistics_daily WHERE user_id = ?',
            [$userId]
        ) ?? [];

        return [
            'rounds'   => (int) ($row['rounds'] ?? 0),
            'wagered'  => (int) ($row['wagered'] ?? 0),
            'returned' => (int) ($row['returned'] ?? 0),
            'wins'     => (int) ($row['wins'] ?? 0),
            'losses'   => (int) ($row['losses'] ?? 0),
        ];
    }
}
