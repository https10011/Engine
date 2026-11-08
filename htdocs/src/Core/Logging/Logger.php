<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Logging;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger writing structured lines to a file or stdout.
 *
 * Context values whose key appears in the redact list are replaced before they
 * are written, so credentials cannot leak into logs through a careless
 * `logger->info('login', $request)`.
 */
final class Logger extends AbstractLogger
{
    public const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    /** @var list<string> */
    private array $redactKeys;

    /**
     * @param list<string> $redactKeys Context keys whose values are masked.
     */
    public function __construct(
        private readonly string $channel,
        private readonly string $minimumLevel = 'debug',
        private readonly ?string $filePath = null,
        array $redactKeys = []
    ) {
        $this->redactKeys = array_map('strtolower', $redactKeys);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $level = (string) $level;
        $threshold = self::LEVELS[$this->minimumLevel] ?? 100;
        $severity  = self::LEVELS[$level] ?? 200;

        if ($severity < $threshold) {
            return;
        }

        $entry = [
            'time'    => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'level'   => $level,
            'channel' => $this->channel,
            'message' => $this->interpolate((string) $message, $context),
            'context' => $this->redact($context),
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($this->channel === 'stdout') {
            fwrite(STDOUT, ($line ?: '{"level":"error","message":"log encode failure"}') . PHP_EOL);

            return;
        }

        if ($this->channel === 'null' || $this->filePath === null) {
            return;
        }

        $this->writeToFile($line ?: '{"level":"error","message":"log encode failure"}');
    }

    private function writeToFile(string $line): void
    {
        $directory = \dirname($this->filePath);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $handle = @fopen($this->filePath, 'ab');
        if ($handle === false) {
            return;
        }

        // LOCK_EX keeps concurrent PHP-FPM workers from interleaving lines.
        @flock($handle, LOCK_EX);
        @fwrite($handle, $line . PHP_EOL);
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    /**
     * Substitutes `{placeholder}` tokens from context, PSR-3 style.
     *
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof Stringable) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, $this->redactKeys, true)) {
                $result[$key] = '[REDACTED]';
                continue;
            }
            $result[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $result;
    }
}
