<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** Builds a logger from configuration. */
final class LoggerFactory
{
    /** @param list<string> $redactKeys */
    public static function create(
        string $channel,
        string $level,
        ?string $logDirectory,
        array $redactKeys = []
    ): LoggerInterface {
        return match ($channel) {
            'stdout' => new Logger('stdout', $level, null, $redactKeys),
            'null'   => new NullLogger(),
            default  => new Logger(
                'file',
                $level,
                $logDirectory === null ? null : rtrim($logDirectory, '/') . '/engine.log',
                $redactKeys
            ),
        };
    }
}
