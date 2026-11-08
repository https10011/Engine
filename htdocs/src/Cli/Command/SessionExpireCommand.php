<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;

final class SessionExpireCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'session:expire';
    }

    public function description(): string
    {
        return 'Mark expired sessions as expired. Intended for cron.';
    }

    public function usage(): string
    {
        return 'session:expire';
    }

    public function execute(Engine $engine, array $options): int
    {
        $count = $engine->sessions()->expireStale();
        $this->line(sprintf('Expired %d session(s).', $count));

        return 0;
    }
}
