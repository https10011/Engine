<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

/** Convenience helpers shared by commands. */
abstract class AbstractCommand implements CommandInterface
{
    protected function option(array $options, string $key, ?string $default = null): ?string
    {
        $value = $options[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    protected function intOption(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    protected function boolOption(array $options, string $key): bool
    {
        $value = $options[$key] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    protected function line(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    protected function error(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }

    protected function json(mixed $value): void
    {
        fwrite(STDOUT, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
