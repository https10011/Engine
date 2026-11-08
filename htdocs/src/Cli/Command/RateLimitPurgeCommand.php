<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Engine;
use CasinoEngine\Persistence\Repository\RateLimitRepository;

final class RateLimitPurgeCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'ratelimit:purge';
    }

    public function description(): string
    {
        return 'Delete rate-limit windows older than the retention period. Intended for cron.';
    }

    public function usage(): string
    {
        return 'ratelimit:purge';
    }

    public function execute(Engine $engine, array $options): int
    {
        $days = max(1, (int) $engine->config()->get('security.rate_limit_retention_days', 7));

        /** @var ClockInterface $clock */
        $clock = $engine->container()->get(ClockInterface::class);
        $cutoff = $clock->now()->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');

        /** @var RateLimitRepository $buckets */
        $buckets = $engine->container()->get(RateLimitRepository::class);
        $deleted = $buckets->purgeOlderThan($cutoff);

        $this->line(sprintf('Deleted %d rate-limit bucket(s) older than %s.', $deleted, $cutoff));

        return 0;
    }
}
