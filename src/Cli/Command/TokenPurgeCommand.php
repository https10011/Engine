<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Persistence\Repository\TokenRepository;

final class TokenPurgeCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'token:purge';
    }

    public function description(): string
    {
        return 'Delete expired API tokens. Intended for cron.';
    }

    public function usage(): string
    {
        return 'token:purge';
    }

    public function execute(Engine $engine, array $options): int
    {
        /** @var TokenRepository $tokens */
        $tokens = $engine->container()->get(TokenRepository::class);
        $deleted = $tokens->purgeExpired($engine->clock()->now()->format('Y-m-d H:i:s'));

        $this->line(sprintf('Deleted %d expired token(s).', $deleted));

        return 0;
    }
}
