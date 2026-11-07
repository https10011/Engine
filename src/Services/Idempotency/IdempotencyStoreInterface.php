<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Idempotency;

/**
 * Replay protection for money-moving requests.
 *
 * A client that retries after a timeout must not cause a second wager. The
 * store records the request fingerprint alongside the completed response, so a
 * retry of the same request returns the original answer, while a *different*
 * request reusing the same key is rejected as a conflict rather than silently
 * replaying the wrong result.
 */
interface IdempotencyStoreInterface
{
    /** @param array<string, mixed> $payload */
    public function fingerprint(string $gameId, string $action, array $payload, ?int $betMinor): string;

    public function claim(int $userId, string $gameId, string $key, string $fingerprint): bool;

    /** @return array<string, mixed>|null Stored response, or null if none. */
    public function storedResponse(int $userId, string $gameId, string $key): ?array;

    /** @return array<string, mixed>|null Raw row, for fingerprint comparison. */
    public function lookup(int $userId, string $gameId, string $key): ?array;

    /** @param array<string, mixed> $response */
    public function complete(int $userId, string $gameId, string $key, array $response, ?int $roundId): void;

    public function release(int $userId, string $gameId, string $key): void;

    public function purgeExpired(): int;
}
