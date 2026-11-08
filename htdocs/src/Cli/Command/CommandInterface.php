<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;

interface CommandInterface
{
    public function name(): string;

    public function description(): string;

    /** Human-readable usage, e.g. `games:list [--category=slot]`. */
    public function usage(): string;

    /**
     * @param array<string, mixed> $options
     * @return int Process exit code.
     */
    public function execute(Engine $engine, array $options): int;
}
