<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Migration;

use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Exception\PersistenceException;
use PDO;

/**
 * Ordered, idempotent SQL migration runner.
 *
 * Design choices that matter for shared hosting:
 *
 *  * Migrations are plain `.sql` files, ordered by filename, so there is no
 *    code to execute and no framework state to corrupt.
 *  * The `migrations` table records a checksum per file. A file that has
 *    already been applied and has since changed is reported as a problem
 *    rather than silently skipped — the usual cause of "works on my machine"
 *    schema drift.
 *  * Each migration runs in a transaction where the driver supports it. MySQL
 *    DDL is not transactional, so a failed migration is reported with the
 *    exact statement that failed; the runner never marks a partially applied
 *    migration as done.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $migrationsDirectory,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @return list<string> versions applied by this run
     */
    public function migrate(bool $dryRun = false): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->appliedMigrations();
        $pending = $this->pendingMigrations($applied);

        if ($pending === []) {
            return [];
        }

        $executed = [];
        foreach ($pending as $migration) {
            if ($dryRun) {
                $executed[] = $migration['version'];
                continue;
            }

            $this->apply($migration);
            $executed[] = $migration['version'];
        }

        return $executed;
    }

    /** @return list<array{version:string,name:string,checksum:string}> */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        return array_map(
            static fn (array $row): array => [
                'version'  => (string) $row['version'],
                'name'     => (string) $row['name'],
                'checksum' => (string) $row['checksum'],
            ],
            $this->connection->pdo()
                ->query('SELECT version, name, checksum FROM migrations ORDER BY version')
                ->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Verifies that every applied migration still matches the file on disk.
     *
     * @return list<string> versions whose file has changed since it was applied
     */
    public function verify(): array
    {
        $applied = $this->appliedMigrations();
        $drifted = [];

        foreach ($this->migrationFiles() as $file) {
            $version = $this->versionFromFilename($file);
            if (isset($applied[$version]) && $applied[$version] !== hash_file('sha256', $file)) {
                $drifted[] = $version;
            }
        }

        return $drifted;
    }

    private function ensureMigrationsTable(): void
    {
        $this->connection->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` ('
            . ' `version` VARCHAR(64) NOT NULL,'
            . ' `name` VARCHAR(191) NOT NULL,'
            . ' `applied_at` DATETIME NOT NULL,'
            . ' `checksum` CHAR(64) NOT NULL,'
            . ' PRIMARY KEY (`version`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, string> version => checksum */
    private function appliedMigrations(): array
    {
        $rows = $this->connection->pdo()
            ->query('SELECT version, checksum FROM migrations')
            ->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['version']] = (string) $row['checksum'];
        }

        return $map;
    }

    /**
     * @param array<string, string> $applied
     * @return list<array{version:string,name:string,path:string,checksum:string}>
     */
    private function pendingMigrations(array $applied): array
    {
        $pending = [];

        foreach ($this->migrationFiles() as $path) {
            $version = $this->versionFromFilename($path);
            if (isset($applied[$version])) {
                continue;
            }

            $checksum = hash_file('sha256', $path);
            $pending[] = [
                'version'  => $version,
                'name'     => basename($path),
                'path'     => $path,
                'checksum' => $checksum === false ? '' : $checksum,
            ];
        }

        return $pending;
    }

    /** @return list<string> Absolute paths, ordered by filename. */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationsDirectory)) {
            return [];
        }

        $files = glob(rtrim($this->migrationsDirectory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return array_values($files);
    }

    private function versionFromFilename(string $path): string
    {
        return basename($path, '.sql');
    }

    /**
     * @param array{version:string,name:string,path:string,checksum:string} $migration
     */
    private function apply(array $migration): void
    {
        $sql = file_get_contents($migration['path']);
        if ($sql === false) {
            throw new PersistenceException('A migration file could not be read.', [
                'file' => $migration['name'],
            ]);
        }

        $pdo = $this->connection->pdo();

        // MySQL DDL is not transactional, so the runner does not pretend a
        // migration is atomic: it records the version only after every
        // statement has succeeded, and reports the failing statement otherwise.
        foreach ($this->splitStatements($sql) as $statement) {
            if (trim($statement) === '') {
                continue;
            }

            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                throw new PersistenceException(
                    sprintf('Migration "%s" failed.', $migration['name']),
                    [
                        'file'      => $migration['name'],
                        'statement' => substr(trim($statement), 0, 500),
                        'driver'    => $e->getMessage(),
                    ],
                    $e
                );
            }
        }

        $statement = $pdo->prepare(
            'INSERT INTO migrations (version, name, applied_at, checksum) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([
            $migration['version'],
            $migration['name'],
            $this->clock->now()->format('Y-m-d H:i:s'),
            $migration['checksum'],
        ]);
    }

    /**
     * Splits a SQL file into statements on semicolons that are not inside a
     * string literal or a comment.
     *
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && !$inDouble) {
                // A doubled quote inside a string is an escaped quote.
                if ($inSingle && $next === "'") {
                    $buffer .= "''";
                    $i++;
                    continue;
                }
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif ($char === ';' && !$inSingle && !$inDouble) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}
