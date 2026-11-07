<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Session;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Contract\GameSessionInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Contract\SessionStatus;
use CasinoEngine\Core\Exception\AuthorizationException;
use CasinoEngine\Core\Exception\SessionNotFoundException;
use CasinoEngine\Core\Event\EngineEvents;
use CasinoEngine\Core\Event\EventDispatcherInterface;
use CasinoEngine\Core\Support\IdGenerator;
use CasinoEngine\Core\Value\GameSession;
use CasinoEngine\Persistence\Repository\SessionRepository;

final class SessionService implements SessionServiceInterface
{
    /**
     * Every DATETIME column is written and read as UTC.
     *
     * Without a single convention a session written by a UTC clock would be
     * read back in the application's display timezone and appear to have
     * expired hours ago, so the instant stored in the row and the instant the
     * clock returns must share one zone.
     */

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly ClockInterface $clock,
        private readonly Config $config,
        private readonly ?EventDispatcherInterface $events = null
    ) {
    }

    public function create(int $userId, string $gameId, GameStateInterface $initialState): GameSessionInterface
    {
        $now = $this->clock->now();
        $ttl = (int) $this->config->get('session.ttl_seconds', 3600);
        $expiresAt = $now->modify(sprintf('+%d seconds', $ttl));

        $session = new GameSession(
            IdGenerator::ulid(),
            $userId,
            $gameId,
            $initialState,
            SessionStatus::Active,
            0,
            $now,
            $now,
            $expiresAt
        );

        $this->sessions->create(
            $session->id(),
            $userId,
            $gameId,
            $initialState->toArray(),
            $now->format('Y-m-d H:i:s'),
            $expiresAt->format('Y-m-d H:i:s')
        );

        $this->events?->dispatch(EngineEvents::SESSION_CREATED, [
            'session_id' => $session->id(),
            'user_id'    => $userId,
            'game_id'    => $gameId,
        ]);

        return $session;
    }

    public function load(string $sessionId): GameSessionInterface
    {
        $row = $this->sessions->findById($sessionId);
        if ($row === null) {
            throw new SessionNotFoundException('The game session could not be found.');
        }

        return $this->hydrate($row);
    }

    public function loadForUser(string $sessionId, int $userId): GameSessionInterface
    {
        $session = $this->load($sessionId);

        if ($session->userId() !== $userId) {
            // Deliberately the same message as "not found": telling a caller
            // that someone else's session exists is an information leak.
            throw new AuthorizationException('The game session could not be found.');
        }

        if ($session->isExpiredAt($this->clock->now()) && $session->status() === SessionStatus::Active) {
            $this->close($sessionId);
            throw new SessionNotFoundException('The game session has expired.');
        }

        return $session;
    }

    public function loadForUserLocked(string $sessionId, int $userId): GameSessionInterface
    {
        $row = $this->sessions->lockById($sessionId);
        if ($row === null) {
            throw new SessionNotFoundException('The game session could not be found.');
        }

        $session = $this->hydrate($row);

        if ($session->userId() !== $userId) {
            throw new AuthorizationException('The game session could not be found.');
        }

        if (!$session->status()->isPlayable()) {
            throw new SessionNotFoundException('The game session is no longer active.');
        }

        if ($session->isExpiredAt($this->clock->now())) {
            throw new SessionNotFoundException('The game session has expired.');
        }

        return $session;
    }

    public function persist(GameSessionInterface $session): void
    {
        $this->sessions->updateState(
            $session->id(),
            $session->state()->toArray(),
            $session->roundCount(),
            $session->updatedAt()->format('Y-m-d H:i:s'),
            $session->expiresAt()->format('Y-m-d H:i:s')
        );
    }

    public function close(string $sessionId): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->sessions->updateStatus($sessionId, SessionStatus::Closed->value, $now);

        $this->events?->dispatch(EngineEvents::SESSION_CLOSED, [
            'session_id' => $sessionId,
            'reason'     => 'closed',
        ]);
    }

    public function expireStale(): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $ids = $this->sessions->expiredSessionIds($now);

        foreach ($ids as $id) {
            $this->sessions->updateStatus($id, SessionStatus::Expired->value, $now);
            $this->events?->dispatch(EngineEvents::SESSION_CLOSED, [
                'session_id' => $id,
                'reason'     => 'expired',
            ]);
        }

        return count($ids);
    }

    public function listForUser(int $userId, int $limit = 20): array
    {
        return $this->sessions->listForUser($userId, $limit);
    }

    /**
     * Parses a DATETIME column.
     *
     * Every DATETIME is written and read as UTC. Without one convention a
     * session written by the UTC clock would be read back in the application's
     * display timezone and appear to have expired hours ago, so the instant
     * stored in the row and the instant the clock returns must share a zone.
     */
    private static function dbTime(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    /**
     * Rebuilds a session from a database row.
     *
     * The concrete state class is supplied by the game module at load time, so
     * the session service stores an opaque array and never needs to know what a
     * reel or a hand is.
     *
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): GameSessionInterface
    {
        $rawState = $row['state'] ?? '{}';
        if (is_string($rawState)) {
            $decoded = json_decode($rawState, true);
            $rawState = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rawState)) {
            $rawState = [];
        }

        $state = new \CasinoEngine\Core\Value\RawGameState($rawState);

        return new GameSession(
            (string) $row['id'],
            (int) $row['user_id'],
            (string) $row['game_id'],
            $state,
            SessionStatus::from((string) $row['status']),
            (int) $row['round_count'],
            self::dbTime((string) $row['created_at']),
            self::dbTime((string) $row['updated_at']),
            self::dbTime((string) $row['expires_at'])
        );
    }
}
