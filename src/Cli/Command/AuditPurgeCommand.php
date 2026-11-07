<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Engine;
use CasinoEngine\Persistence\Repository\AuditRepository;

final class AuditPurgeCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'audit:purge';
    }

    public function description(): string
    {
        return 'Delete audit rows older than the retention window. Intended for cron.';
    }

    public function usage(): string
    {
        return 'audit:purge';
    }

    public function execute(Engine $engine, array $options): int
    {
        $days = max(1, (int) $engine->config()->get('security.audit_retention_days', 90));

        /** @var ClockInterface $clock */
        $clock = $engine->container()->get(ClockInterface::class);
        $cutoff = $clock->now()->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');

        /** @var AuditRepository $audit */
        $audit = $engine->container()->get(AuditRepository::class);
        $deleted = $audit->purgeOlderThan($cutoff);

        $this->line(sprintf('Deleted %d audit row(s) older than %s.', $deleted, $cutoff));

        return 0;
    }
}
