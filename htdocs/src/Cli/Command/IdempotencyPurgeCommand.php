<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Services\Idempotency\IdempotencyStoreInterface;

final class IdempotencyPurgeCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'idempotency:purge';
    }

    public function description(): string
    {
        return 'Delete expired idempotency keys. Intended for cron.';
    }

    public function usage(): string
    {
        return 'idempotency:purge';
    }

    public function execute(Engine $engine, array $options): int
    {
        /** @var IdempotencyStoreInterface $store */
        $store = $engine->container()->get(IdempotencyStoreInterface::class);
        $this->line(sprintf('Purged %d expired key(s).', $store->purgeExpired()));

        return 0;
    }
}
