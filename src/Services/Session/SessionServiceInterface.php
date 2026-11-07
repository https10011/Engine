<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Session;

use CasinoEngine\Core\Contract\GameSessionInterface;
use CasinoEngine\Core\Contract\GameStateInterface;

/** Creates, loads and expires game sessions. */
interface SessionServiceInterface
{
    public function create(int $userId, string $gameId, GameStateInterface $initialState): GameSessionInterface;

    /**
     * @throws \CasinoEngine\Core\Exception\SessionNotFoundException
     */
    public function load(string $sessionId): GameSessionInterface;

    /**
     * Loads and verifies that the session belongs to the given user.
     *
     * @throws \CasinoEngine\Core\Exception\SessionNotFoundException
     * @throws \CasinoEngine\Core\Exception\AuthorizationException
     */
    public function loadForUser(string $sessionId, int $userId): GameSessionInterface;

    /**
     * Loads a session for a user with the row locked for update.
     *
     * Must be called inside a transaction. Two actions on one session then
     * serialise here, so a stale round count or state cannot be used to settle
     * a second action against an already-advanced session.
     *
     * @throws \CasinoEngine\Core\Exception\SessionNotFoundException
     * @throws \CasinoEngine\Core\Exception\AuthorizationException
     */
    public function loadForUserLocked(string $sessionId, int $userId): GameSessionInterface;

    public function persist(GameSessionInterface $session): void;

    public function close(string $sessionId): void;

    /** Marks every expired active session as expired; returns how many. */
    public function expireStale(): int;

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 20): array;
}
