<?php

declare(strict_types=1);

namespace CasinoEngine\Persistence\Repository;

/**
 * Records rate-limit buckets for the HTTP API.
 *
 * The counter is a single row per (bucket, window start). Incrementing with an
 * atomic `INSERT ... ON DUPLICATE KEY UPDATE` means two concurrent requests
 * cannot both read the old count and both decide they are within the limit.
 * This is the shared-hosting-friendly alternative to Redis or APCu, which
 * cannot be assumed to exist.
 */
final class RateLimitRepository extends AbstractRepository
{
    /**
     * Increments a bucket and returns the new count for this window.
     *
     * @return int requests seen in the window, including this one
     */
    public function hit(string $bucket, string $windowStart, string $now): int
    {
        $this->execute(
            'INSERT INTO rate_limit_buckets (bucket, window_start, hits, updated_at)
             VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE hits = hits + 1, updated_at = VALUES(updated_at)',
            [$bucket, $windowStart, $now]
        );

        $row = $this->fetchOne(
            'SELECT hits FROM rate_limit_buckets WHERE bucket = ? AND window_start = ?',
            [$bucket, $windowStart]
        );

        return $row === null ? 1 : (int) $row['hits'];
    }

    public function purgeOlderThan(string $windowStart): int
    {
        return $this->execute('DELETE FROM rate_limit_buckets WHERE window_start < ?', [$windowStart]);
    }
}
