<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

use DateTimeImmutable;

/** A player's ongoing play of one game. */
interface GameSessionInterface
{
    public function id(): string;

    public function userId(): int;

    public function gameId(): string;

    public function state(): GameStateInterface;

    public function status(): SessionStatus;

    public function roundCount(): int;

    public function createdAt(): DateTimeImmutable;

    public function updatedAt(): DateTimeImmutable;

    public function expiresAt(): DateTimeImmutable;

    public function withState(GameStateInterface $state, DateTimeImmutable $updatedAt): static;

    public function withStatus(SessionStatus $status, DateTimeImmutable $updatedAt): static;

    public function withRoundCount(int $roundCount, DateTimeImmutable $updatedAt): static;

    /**
     * True once the session's expiry has passed.
     *
     * The engine, not the caller, decides what expiry means for a given
     * session, so this is a question the session answers about itself.
     */
    public function isExpiredAt(DateTimeImmutable $now): bool;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
