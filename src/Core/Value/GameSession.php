<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\GameSessionInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\SessionStatus;
use DateTimeImmutable;

/** Immutable session record. */
final class GameSession implements GameSessionInterface
{
    public function __construct(
        private readonly string $id,
        private readonly int $userId,
        private readonly string $gameId,
        private readonly GameStateInterface $state,
        private readonly SessionStatus $status,
        private readonly int $roundCount,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly DateTimeImmutable $expiresAt
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function gameId(): string
    {
        return $this->gameId;
    }

    public function state(): GameStateInterface
    {
        return $this->state;
    }

    public function status(): SessionStatus
    {
        return $this->status;
    }

    public function roundCount(): int
    {
        return $this->roundCount;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function withState(GameStateInterface $state, DateTimeImmutable $updatedAt): static
    {
        return new self(
            $this->id,
            $this->userId,
            $this->gameId,
            $state,
            $this->status,
            $this->roundCount,
            $this->createdAt,
            $updatedAt,
            $this->expiresAt
        );
    }

    public function withRoundCount(int $roundCount, DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->gameId,
            $this->state,
            $this->status,
            $roundCount,
            $this->createdAt,
            $updatedAt,
            $this->expiresAt
        );
    }

    public function withStatus(SessionStatus $status, DateTimeImmutable $updatedAt): static
    {
        return new self(
            $this->id,
            $this->userId,
            $this->gameId,
            $this->state,
            $status,
            $this->roundCount,
            $this->createdAt,
            $updatedAt,
            $this->expiresAt
        );
    }

    public function withExpiry(DateTimeImmutable $expiresAt): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->gameId,
            $this->state,
            $this->status,
            $this->roundCount,
            $this->createdAt,
            $this->updatedAt,
            $expiresAt
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'user_id'     => $this->userId,
            'game_id'     => $this->gameId,
            'status'      => $this->status->value,
            'round_count' => $this->roundCount,
            'state'       => $this->state->toArray(),
            'created_at'  => $this->createdAt->format(DATE_ATOM),
            'updated_at'  => $this->updatedAt->format(DATE_ATOM),
            'expires_at'  => $this->expiresAt->format(DATE_ATOM),
        ];
    }
}
