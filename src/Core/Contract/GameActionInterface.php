<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

use CasinoEngine\Core\Value\Money;

/**
 * One instruction from a player: spin the reels, hit, stand, place a bet.
 *
 * `bet()` is non-null only for actions that move money. A free spin carries a
 * null bet and therefore never debits the wallet.
 */
interface GameActionInterface
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function payload(): array;

    public function bet(): ?Money;

    /** Stable fingerprint used to detect an idempotency-key payload mismatch. */
    public function fingerprint(): string;
}
