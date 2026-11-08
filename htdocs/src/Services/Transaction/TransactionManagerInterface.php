<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Transaction;

/**
 * Coordinates database transactions.
 *
 * Every money-moving operation goes through {@see self::transactional()}. That
 * method commits on a normal return, rolls back on any throwable, and is
 * re-entrant — an inner call joins the outer transaction rather than
 * committing it early, which is the classic way a "successful" inner block
 * ends up committing a half-finished round.
 */
interface TransactionManagerInterface
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed;

    public function begin(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function isActive(): bool;

    public function depth(): int;
}
