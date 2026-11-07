<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Config;

/**
 * Reads process environment variables with type coercion.
 *
 * Production is expected to supply real environment variables. Development
 * loads a `.env` file via {@see self::loadFile()}. Neither path is required:
 * missing keys fall back to the defaults declared in `config/*.php`.
 */
final class Env
{
    /** @var array<string, string> Values loaded from a `.env` file. */
    private static array $fileValues = [];

    /** Reads a raw value, preferring the real environment over a `.env` file. */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return self::$fileValues[$key] ?? $default;
        }

        return (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on'   => true,
            '0', 'false', 'no', 'off'  => false,
            default                    => $default,
        };
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key);

        return $value === null || !is_numeric($value) ? $default : (float) $value;
    }

    /**
     * Reads a comma-separated list. Surrounding quotes are stripped so that
     * `FOO="a, b"` behaves the same as `FOO=a, b`.
     *
     * @return list<string>
     */
    public static function list(string $key, array $default = []): array
    {
        $value = self::get($key);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $parts = array_map(
            static fn (string $item): string => trim($item, " \t\n\r\0\x0B\"'"),
            explode(',', $value)
        );

        return array_values(array_filter($parts, static fn (string $i): bool => $i !== ''));
    }

    /**
     * Loads a `.env` file. Existing real environment variables always win, so
     * a container can override any file value without editing it.
     */
    public static function loadFile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $name  = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if ($name === '') {
                continue;
            }

            // Strip an unquoted trailing comment, then surrounding quotes.
            $quoted = false;
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quoted = true;
            } elseif ($value !== '') {
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = rtrim(substr($value, 0, $hash));
                }
            }

            $value = trim($value, "\"'");

            // Inside quotes, `\\` and `\"` are escapes. Without this a value
            // such as "CasinoEngine\\Games" would keep both backslashes and
            // every namespace lookup would miss.
            if ($quoted) {
                $value = str_replace(['\\\\', '\\"', "\\'"], ['\\', '"', "'"], $value);
            }

            self::$fileValues[$name] = $value;

            if (!isset($_ENV[$name]) && getenv($name) === false) {
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }

        return true;
    }

    /** Clears loaded file values. Used by tests to isolate configuration. */
    public static function reset(): void
    {
        self::$fileValues = [];
    }
}
