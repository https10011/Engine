<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Idempotency;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Persistence\Repository\IdempotencyRepository;

final class IdempotencyStore implements IdempotencyStoreInterface
{
    public function __construct(
        private readonly IdempotencyRepository $repository,
        private readonly ClockInterface $clock,
        private readonly Config $config
    ) {
    }

    public function fingerprint(string $gameId, string $action, array $payload, ?int $betMinor): string
    {
        ksort($payload);

        return hash('sha256', json_encode([
            'game_id' => $gameId,
            'action'  => $action,
            'bet'     => $betMinor,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }

    public function claim(int $userId, string $gameId, string $key, string $fingerprint): bool
    {
        $ttl = (int) $this->config->get('security.idempotency_ttl_seconds', 86400);

        return $this->repository->claim(
            $userId,
            $gameId,
            $this->hashKey($key),
            $fingerprint,
            $this->clock->now()->format('Y-m-d H:i:s'),
            $this->clock->now()->modify(sprintf('+%d seconds', $ttl))->format('Y-m-d H:i:s')
        );
    }

    public function storedResponse(int $userId, string $gameId, string $key): ?array
    {
        $row = $this->repository->find($userId, $gameId, $this->hashKey($key));
        if ($row === null || $row['status'] !== 'completed' || $row['response'] === null) {
            return null;
        }

        $response = is_array($row['response'])
            ? $row['response']
            : json_decode((string) $row['response'], true);

        return is_array($response) ? $response : null;
    }

    public function lookup(int $userId, string $gameId, string $key): ?array
    {
        return $this->repository->find($userId, $gameId, $this->hashKey($key));
    }

    public function complete(int $userId, string $gameId, string $key, array $response, ?int $roundId): void
    {
        $this->repository->complete($userId, $gameId, $this->hashKey($key), $response, $roundId);
    }

    public function release(int $userId, string $gameId, string $key): void
    {
        $this->repository->release($userId, $gameId, $this->hashKey($key));
    }

    public function purgeExpired(): int
    {
        return $this->repository->purgeExpired($this->clock->now()->format('Y-m-d H:i:s'));
    }

    /**
     * Stores a hash rather than the key itself.
     *
     * A client-chosen key can be long or contain arbitrary bytes; hashing keeps
     * the column fixed-width and indexed, and means a leaked database dump does
     * not reveal a client's key material.
     */
    private function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
