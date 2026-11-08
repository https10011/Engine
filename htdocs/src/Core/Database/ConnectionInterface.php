<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Database;

use PDO;

/** Read-only view of the database connection used by repositories and services. */
interface ConnectionInterface
{
    public function pdo(): PDO;

    public function isConnected(): bool;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    public function transactionDepth(): int;
}
