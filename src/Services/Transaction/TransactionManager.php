<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Transaction;

use CasinoEngine\Core\Database\ConnectionInterface;
use Throwable;

final class TransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function transactional(callable $operation): mixed
    {
        $this->begin();

        try {
            $result = $operation();
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }

        $this->commit();

        return $result;
    }

    public function begin(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function isActive(): bool
    {
        return $this->connection->inTransaction();
    }

    public function depth(): int
    {
        return $this->connection->transactionDepth();
    }
}
