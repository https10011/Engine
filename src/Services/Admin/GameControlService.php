<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Admin;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\ConnectionInterface;

/**
 * Per-game operator controls that are independent of the odds profile.
 *
 * Availability is enforced by the same game catalogue and session endpoints the
 * frontend calls, so disabling a game here actually removes it from the lobby
 * and refuses new sessions. Friendly play is an operator-visible marker that a
 * game is running with non-default generosity; the concrete odds remain in the
 * probability profile so there is only one source of truth for them.
 */
final class GameControlService
{
    /** @var array<string, array{available:bool,friendly_play:bool}>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ClockInterface $clock
    ) {
    }

    public function isAvailable(string $gameId): bool
    {
        return $this->all()[$gameId]['available'] ?? true;
    }

    public function isFriendlyPlay(string $gameId): bool
    {
        return $this->all()[$gameId]['friendly_play'] ?? false;
    }

    /** @return array<string, array{available:bool,friendly_play:bool}> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];
        $statement = $this->connection->pdo()->query(
            'SELECT game_id, available, friendly_play FROM game_controls'
        );

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $this->cache[(string) $row['game_id']] = [
                'available'     => (bool) $row['available'],
                'friendly_play' => (bool) $row['friendly_play'],
            ];
        }

        return $this->cache;
    }

    public function save(string $gameId, bool $available, bool $friendlyPlay, ?int $adminUserId): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->connection->pdo()->prepare(
            'INSERT INTO game_controls (game_id, available, friendly_play, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                available = VALUES(available),
                friendly_play = VALUES(friendly_play),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)'
        )->execute([$gameId, (int) $available, (int) $friendlyPlay, $adminUserId, $now]);

        $this->cache = null;
    }

    public function invalidate(): void
    {
        $this->cache = null;
    }
}
