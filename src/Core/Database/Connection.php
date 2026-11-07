<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Database;

use CasinoEngine\Core\Exception\PersistenceException;
use PDO;
use PDOException;

/**
 * Lazy PDO wrapper.
 *
 * The connection is opened on first use so that a CLI command which only reads
 * configuration never pays for a database round trip, and so that booting the
 * engine cannot fail merely because the database is briefly unavailable.
 */
final class Connection implements ConnectionInterface
{
    private ?PDO $pdo = null;

    private int $transactionDepth = 0;

    /**
     * @param array<string, mixed> $settings Keys: driver, host, port, database,
     *        username, password, charset, collation, persistent, emulate_prepares.
     */
    public function __construct(private readonly array $settings)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    private function connect(): PDO
    {
        $driver   = (string) ($this->settings['driver'] ?? 'mysql');
        $host     = (string) ($this->settings['host'] ?? '127.0.0.1');
        $port     = (int) ($this->settings['port'] ?? 3306);
        $database = (string) ($this->settings['database'] ?? '');
        $charset  = (string) ($this->settings['charset'] ?? 'utf8mb4');

        $dsn = match ($driver) {
            'mysql' => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset),
            'sqlite' => sprintf('sqlite:%s', $database),
            default => throw new PersistenceException("Unsupported database driver '$driver'."),
        };

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_EMULATE_PREPARES   => (bool) ($this->settings['emulate_prepares'] ?? false),
        ];

        if (($this->settings['persistent'] ?? false) === true) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        if ($driver === 'sqlite') {
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        try {
            return new PDO(
                $dsn,
                (string) ($this->settings['username'] ?? ''),
                (string) ($this->settings['password'] ?? ''),
                $options
            );
        } catch (PDOException $e) {
            // The DSN can contain the host and database name; the password
            // never reaches the message, and the raw driver text stays in the
            // log context rather than the public message.
            throw new PersistenceException(
                'Could not connect to the database.',
                ['driver' => $driver, 'database' => $database, 'driver_message' => $e->getMessage()],
                $e
            );
        }
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            // Nested transactions are emulated with savepoints so that an
            // inner rollback cannot silently discard the outer unit of work.
            $this->pdo()->exec('SAVEPOINT sp_' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            throw new PersistenceException('Cannot commit: no transaction is active.');
        }

        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT sp_' . $this->transactionDepth);
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            throw new PersistenceException('Cannot roll back: no transaction is active.');
        }

        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT sp_' . $this->transactionDepth);
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    public function transactionDepth(): int
    {
        return $this->transactionDepth;
    }

    /** Resets depth tracking; used by tests that tear down a connection. */
    public function resetTransactionState(): void
    {
        $this->transactionDepth = 0;
    }
}
