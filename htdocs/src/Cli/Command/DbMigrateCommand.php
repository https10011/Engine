<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Persistence\Migration\MigrationRunner;

final class DbMigrateCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'db:migrate';
    }

    public function description(): string
    {
        return 'Apply pending database migrations.';
    }

    public function usage(): string
    {
        return 'db:migrate [--dry-run]';
    }

    public function execute(Engine $engine, array $options): int
    {
        /** @var MigrationRunner $runner */
        $runner = $engine->container()->get(MigrationRunner::class);
        $applied = $runner->migrate($this->boolOption($options, 'dry-run'));

        if ($applied === []) {
            $this->line('Database is up to date.');

            return 0;
        }

        $label = $this->boolOption($options, 'dry-run') ? 'Pending' : 'Applied';
        foreach ($applied as $version) {
            $this->line(sprintf('%s: %s', $label, $version));
        }

        return 0;
    }
}
