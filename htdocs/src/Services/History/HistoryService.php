<?php

declare(strict_types=1);

namespace CasinoEngine\Services\History;

use CasinoEngine\Persistence\Repository\BetRepository;
use CasinoEngine\Persistence\Repository\RoundRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;

final class HistoryService implements HistoryServiceInterface
{
    public function __construct(
        private readonly RoundRepository $rounds,
        private readonly BetRepository $bets,
        private readonly WalletRepository $wallets
    ) {
    }

    public function gameHistory(int $userId, int $limit = 50, int $offset = 0, ?string $gameId = null): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        return [
            'items'  => $this->normaliseRounds($this->rounds->listForUser($userId, $limit, $offset, $gameId)),
            'total'  => $this->rounds->totalsForUser($userId, $gameId)['rounds'],
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    public function sessionHistory(string $sessionId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        return [
            'items'  => $this->normaliseRounds($this->rounds->listForSession($sessionId, $limit, $offset)),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    public function betHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        return [
            'items'  => $this->bets->listForUser($userId, $limit, $offset),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    public function walletHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        return [
            'items'  => $this->wallets->transactionsForUser($userId, $limit, $offset),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    public function round(int $roundId): ?array
    {
        return $this->rounds->findById($roundId);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function normaliseRounds(array $items): array
    {
        return array_map(
            static fn (array $row): array => [
                'round_id'     => (int) ($row['id'] ?? 0),
                'session_id'   => $row['session_id'] ?? null,
                'game_id'      => $row['game_id'] ?? null,
                'round_number' => (int) ($row['round_number'] ?? 0),
                'action'       => $row['action'] ?? null,
                'bet'          => (int) ($row['bet_minor'] ?? 0),
                'payout'       => (int) ($row['payout_minor'] ?? 0),
                'net'          => (int) ($row['net_minor'] ?? 0),
                'created_at'   => $row['created_at'] ?? null,
            ],
            $items
        );
    }
}
