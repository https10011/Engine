<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Wallet;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Exception\InsufficientFundsException;
use CasinoEngine\Core\Exception\PersistenceException;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Transaction\TransactionManagerInterface;

/**
 * Wallet implementation backed by the `wallets` table.
 *
 * Two mechanisms keep balances correct:
 *
 *  1. `lockByUserId()` takes a row lock, so concurrent rounds on the same
 *     wallet serialise and the second one re-reads the first one's result.
 *  2. Every mutation writes a `wallet_transactions` row whose `reference` is
 *     unique, so the same logical movement cannot be recorded twice even if a
 *     caller retries.
 */
final class WalletService implements WalletServiceInterface
{
    public function __construct(
        private readonly WalletRepository $wallets,
        private readonly TransactionManagerInterface $transactions,
        private readonly ClockInterface $clock,
        private readonly Config $config
    ) {
    }

    public function balance(int $userId): Money
    {
        $row = $this->wallets->findByUserId($userId);
        if ($row === null) {
            $this->ensureWallet($userId);
            $row = $this->wallets->findByUserId($userId);
        }

        return $this->toMoney($row);
    }

    public function lockedBalance(int $userId): Money
    {
        if (!$this->transactions->isActive()) {
            throw new PersistenceException('A locked balance read requires an open transaction.');
        }

        $row = $this->wallets->lockByUserId($userId);
        if ($row === null) {
            throw new PersistenceException('Wallet not found for the requested user.', ['user_id' => $userId]);
        }

        return $this->toMoney($row);
    }

    public function debit(
        int $userId,
        Money $amount,
        string $reason,
        ?int $roundId = null,
        ?string $reference = null
    ): Money {
        if ($amount->isNegative()) {
            throw new PersistenceException('A debit amount cannot be negative.');
        }
        if ($amount->isZero()) {
            return $this->balance($userId);
        }

        $this->assertInTransaction();

        $row = $this->wallets->lockByUserId($userId);
        if ($row === null) {
            throw new PersistenceException('Wallet not found for the requested user.', ['user_id' => $userId]);
        }

        $balance = $this->toMoney($row);
        if ($balance->isLessThan($amount)) {
            throw new InsufficientFundsException(
                'Balance is too low for this wager.',
                [
                    'user_id'  => $userId,
                    'balance'  => $balance->minorUnits(),
                    'required' => $amount->minorUnits(),
                ]
            );
        }

        $newBalance = $balance->subtract($amount);
        $this->persist($row, $newBalance);

        $this->wallets->recordTransaction(
            (int) $row['id'],
            $userId,
            $roundId,
            'debit',
            $reason,
            $amount->minorUnits(),
            $newBalance->minorUnits(),
            $reference ?? $this->generateReference('debit', $userId, $roundId),
            $this->clock->now()->format('Y-m-d H:i:s')
        );

        return $newBalance;
    }

    public function credit(
        int $userId,
        Money $amount,
        string $reason,
        ?int $roundId = null,
        ?string $reference = null
    ): Money {
        if ($amount->isNegative()) {
            throw new PersistenceException('A credit amount cannot be negative.');
        }
        if ($amount->isZero()) {
            return $this->balance($userId);
        }

        $this->assertInTransaction();

        $row = $this->wallets->lockByUserId($userId);
        if ($row === null) {
            throw new PersistenceException('Wallet not found for the requested user.', ['user_id' => $userId]);
        }

        $newBalance = $this->toMoney($row)->add($amount);
        $this->persist($row, $newBalance);

        $this->wallets->recordTransaction(
            (int) $row['id'],
            $userId,
            $roundId,
            'credit',
            $reason,
            $amount->minorUnits(),
            $newBalance->minorUnits(),
            $reference ?? $this->generateReference('credit', $userId, $roundId),
            $this->clock->now()->format('Y-m-d H:i:s')
        );

        return $newBalance;
    }

    public function ensureWallet(int $userId): void
    {
        if ($this->wallets->findByUserId($userId) !== null) {
            return;
        }

        $currency = (string) $this->config->get('engine.currency', 'PHP');
        $scale    = (int) $this->config->get('betting.currency_minor_units', 2);
        $now      = $this->clock->now()->format('Y-m-d H:i:s');

        try {
            $this->wallets->createForUser($userId, $currency, $scale, 0, $now);
        } catch (\PDOException $e) {
            // Another request created it first; that is fine.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    public function canAfford(int $userId, Money $amount): bool
    {
        return !$this->balance($userId)->isLessThan($amount);
    }

    /** @param array<string, mixed> $row */
    private function toMoney(array $row): Money
    {
        return Money::of(
            (int) ($row['balance_minor'] ?? 0),
            (string) ($row['currency'] ?? 'PHP'),
            (int) ($row['scale'] ?? 2)
        );
    }

    /** @param array<string, mixed> $row */
    private function persist(array $row, Money $newBalance): void
    {
        $updated = $this->wallets->updateBalance(
            (int) $row['id'],
            (int) $row['version'],
            $newBalance->minorUnits(),
            $this->clock->now()->format('Y-m-d H:i:s')
        );

        if (!$updated) {
            // The optimistic version check caught a concurrent writer that the
            // row lock should already have excluded. Failing loudly is correct:
            // silently retrying here could double-spend.
            throw new PersistenceException(
                'The wallet was modified concurrently; the operation was aborted.',
                ['wallet_id' => $row['id']]
            );
        }
    }

    private function assertInTransaction(): void
    {
        if (!$this->transactions->isActive()) {
            throw new PersistenceException('Wallet mutations require an open transaction.');
        }
    }

    private function generateReference(string $type, int $userId, ?int $roundId): string
    {
        return sprintf('%s:%d:%s:%s', $type, $userId, $roundId ?? 'none', bin2hex(random_bytes(8)));
    }
}
