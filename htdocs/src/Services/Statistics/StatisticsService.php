<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Statistics;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Persistence\Repository\RoundRepository;
use CasinoEngine\Persistence\Repository\StatisticsRepository;

final class StatisticsService implements StatisticsServiceInterface
{
    public function __construct(
        private readonly StatisticsRepository $statistics,
        private readonly RoundRepository $rounds,
        private readonly ClockInterface $clock
    ) {
    }

    public function recordRound(
        int $userId,
        string $gameId,
        Money $wagered,
        Money $returned,
        int $roundId
    ): void {
        $day = $this->clock->now()->format('Y-m-d');
        $isWin = $returned->isGreaterThan($wagered);

        $this->statistics->record(
            $userId,
            $gameId,
            $day,
            $wagered->minorUnits(),
            $returned->minorUnits(),
            $isWin,
            $returned->minorUnits(),
            $this->clock->now()->format('Y-m-d H:i:s')
        );
    }

    public function forUser(int $userId): array
    {
        $totals = $this->statistics->totalsForUser($userId);

        return [
            'rounds'        => $totals['rounds'],
            'wagered'       => $totals['wagered'],
            'returned'      => $totals['returned'],
            'wins'          => $totals['wins'],
            'losses'        => $totals['losses'],
            'rtp'           => $this->safeRatio($totals['returned'], $totals['wagered']),
            'hit_frequency' => $this->safeRatio($totals['wins'], $totals['rounds']),
        ];
    }

    public function dailyForUser(int $userId, int $limit = 30): array
    {
        return $this->statistics->forUser($userId, $limit);
    }

    public function observedReturn(int $userId, ?string $gameId = null): array
    {
        $totals = $this->rounds->totalsForUser($userId, $gameId);

        return [
            'rounds'         => $totals['rounds'],
            'wagered'        => $totals['wagered'],
            'returned'       => $totals['returned'],
            'observed_rtp'   => $this->safeRatio($totals['returned'], $totals['wagered']),
            // A proper interval needs per-round variance, which the simulation
            // harness computes; the request-scoped service leaves it null.
            'standard_error' => null,
        ];
    }

    private function safeRatio(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return $numerator / $denominator;
    }
}
