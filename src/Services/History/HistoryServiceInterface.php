<?php

declare(strict_types=1);

namespace CasinoEngine\Services\History;

/**
 * Read-only projections over recorded play.
 *
 * Kept separate from statistics: statistics aggregate, history lists. A
 * frontend needs both and neither should have to guess at the other's shape.
 */
interface HistoryServiceInterface
{
    /** @return array{items:list<array<string,mixed>>,total:int,limit:int,offset:int} */
    public function gameHistory(int $userId, int $limit = 50, int $offset = 0, ?string $gameId = null): array;

    /** @return array{items:list<array<string,mixed>>,limit:int,offset:int} */
    public function sessionHistory(string $sessionId, int $limit = 50, int $offset = 0): array;

    /** @return array{items:list<array<string,mixed>>,limit:int,offset:int} */
    public function betHistory(int $userId, int $limit = 50, int $offset = 0): array;

    /** @return array{items:list<array<string,mixed>>,limit:int,offset:int} */
    public function walletHistory(int $userId, int $limit = 50, int $offset = 0): array;

    /** @return array<string, mixed>|null */
    public function round(int $roundId): ?array;
}
