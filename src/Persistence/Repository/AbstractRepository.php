<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

use CasinoEngine\Core\Database\ConnectionInterface;
use PDO;

/**
 * Shared helpers for repositories.
 *
 * Repositories are the only classes in the engine that write SQL. Every query
 * goes through a prepared statement, so a value can never be concatenated into
 * SQL — which is what makes SQL injection structurally impossible here rather
 * than a matter of remembering to escape.
 */
abstract class AbstractRepository
{
    public function __construct(protected readonly ConnectionInterface $connection)
    {
    }

    protected function pdo(): PDO
    {
        return $this->connection->pdo();
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $parameters = []): ?array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /** @param array<string, mixed> $parameters */
    protected function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    protected function fetchOneForUpdate(string $sql, array $parameters = []): ?array
    {
        return $this->fetchOne($sql . ' FOR UPDATE', $parameters);
    }

    /** @param mixed $value */
    protected static function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    protected static function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
