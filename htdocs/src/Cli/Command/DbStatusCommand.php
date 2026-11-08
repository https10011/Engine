<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Persistence\Migration\MigrationRunner;

final class DbStatusCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'db:status';
    }

    public function description(): string
    {
        return 'Show applied migrations and detect schema drift.';
    }

    public function usage(): string
    {
        return 'db:status';
    }

    public function execute(Engine $engine, array $options): int
    {
        /** @var MigrationRunner $runner */
        $runner = $engine->container()->get(MigrationRunner::class);

        $applied = $runner->status();
        $drifted = $runner->verify();

        if ($applied === []) {
            $this->line('No migrations have been applied.');
        } else {
            foreach ($applied as $migration) {
                $this->line(sprintf('%s  %s', $migration['version'], $migration['name']));
            }
        }

        if ($drifted !== []) {
            $this->error('');
            $this->error('Drift detected — these applied migrations have changed on disk:');
            foreach ($drifted as $version) {
                $this->error('  ' . $version);
            }

            return 1;
        }

        $this->line('');
        $this->line('No drift detected.');

        return 0;
    }
}
