<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

use CasinoEngine\Core\Value\Money;

/**
 * Descriptive facts about a game module. Read by the registry, the API game
 * listing and the simulation harness. Must be deterministic and side-effect
 * free: it is called during discovery, before any session exists.
 */
interface GameMetadataInterface
{
    /** Lowercase slug matching `^[a-z0-9][a-z0-9._-]{1,63}$`. */
    public function id(): string;

    public function name(): string;

    public function category(): GameCategory;

    /** Semantic version of the module itself, e.g. `1.0.0`. */
    public function version(): string;

    /** SDK contract version the module was written against. */
    public function sdkVersion(): string;

    public function provider(): string;

    public function minBet(): Money;

    public function maxBet(): Money;

    public function betStep(): Money;

    public function volatility(): Volatility;

    /** Published maximum win expressed as a multiple of the bet. */
    public function maxWinMultiplier(): int;

    /** Published RTP if one is known, otherwise null. Never assumed. */
    public function rtp(): ?float;

    public function isEnabled(): bool;

    /** @return list<string> */
    public function tags(): array;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
