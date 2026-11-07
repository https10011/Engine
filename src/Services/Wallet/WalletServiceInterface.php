<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Wallet;

use CasinoEngine\Core\Value\Money;

/**
 * Virtual credit wallet.
 *
 * This is the only component allowed to change a balance. A game module has no
 * reference to it, so "a module cannot move money" is enforced by what the
 * module is handed, not by a convention someone can forget.
 */
interface WalletServiceInterface
{
    public function balance(int $userId): Money;

    /**
     * Removes credits. Must be called inside a transaction; the wallet row is
     * locked for the duration so two concurrent rounds cannot both spend the
     * same credits.
     *
     * @throws \CasinoEngine\Core\Exception\InsufficientFundsException
     */
    public function debit(int $userId, Money $amount, string $reason, ?int $roundId = null, ?string $reference = null): Money;

    public function credit(int $userId, Money $amount, string $reason, ?int $roundId = null, ?string $reference = null): Money;

    /** Reads the balance with the row locked. Requires an open transaction. */
    public function lockedBalance(int $userId): Money;

    public function ensureWallet(int $userId): void;

    public function canAfford(int $userId, Money $amount): bool;
}
