<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Statistics;

use CasinoEngine\Core\Value\Money;

/**
 * Aggregated play statistics.
 *
 * Everything here is derived from committed round records. Nothing is taken
 * from a client, so a player cannot influence their own reported totals.
 */
interface StatisticsServiceInterface
{
    public function recordRound(
        int $userId,
        string $gameId,
        Money $wagered,
        Money $returned,
        int $roundId
    ): void;

    /** @return array{rounds:int,wagered:int,returned:int,wins:int,losses:int,rtp:?float,hit_frequency:?float} */
    public function forUser(int $userId): array;

    /** @return list<array<string, mixed>> */
    public function dailyForUser(int $userId, int $limit = 30): array;

    /**
     * Observed return-to-player for a sample. This is a measurement of a
     * sample, never a claim about a game's theoretical RTP.
     *
     * @return array{rounds:int,wagered:int,returned:int,observed_rtp:?float,standard_error:?float}
     */
    public function observedReturn(int $userId, ?string $gameId = null): array;
}
