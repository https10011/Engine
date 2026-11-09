<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Config;

use CasinoEngine\Core\Exception\ConfigurationException;

/**
 * Immutable nested configuration with dot-path access.
 *
 * Environment overrides are applied by {@see self::fromDefaults()} using an
 * explicit key map so that a typo in an environment variable name can never
 * silently change behaviour.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items;

    /** @param array<string, mixed> $items */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Builds configuration from the `config/*.php` files and applies
     * environment overrides.
     *
     * @param list<string> $envKeys Environment variable names to consider.
     */
    public static function fromDefaults(string $configPath, array $envKeys = []): self
    {
        $items = [];
        foreach (glob(rtrim($configPath, '/') . '/*.php') ?: [] as $file) {
            /** @var mixed $value */
            $value = require $file;
            if (!is_array($value)) {
                throw new ConfigurationException("Config file '$file' must return an array.");
            }

            // Each file returns its own named sections (for example
            // `['app' => [...], 'database' => [...]]`), so the sections are
            // merged at the top level. Nesting them under the file's basename
            // would turn `database.host` into `app.database.host` and every
            // dot-path lookup would miss.
            foreach ($value as $section => $sectionValue) {
                if (isset($items[$section]) && is_array($items[$section]) && is_array($sectionValue)) {
                    $items[$section] = array_replace_recursive($items[$section], $sectionValue);
                    continue;
                }
                $items[$section] = $sectionValue;
            }
        }

        $config = new self($items);

        foreach ($envKeys as $envKey) {
            $raw = Env::get($envKey);
            if ($raw === null || $raw === '') {
                continue;
            }
            $config = $config->withEnvironmentOverride($envKey, $raw);
        }

        return $config;
    }

    /**
     * Maps a known environment variable onto its dot path. The map is
     * deliberately explicit: only listed variables can influence config.
     */
    private function withEnvironmentOverride(string $envKey, string $raw): self
    {
        $map = [
            'APP_NAME'                    => 'app.name',
            'APP_ENV'                     => 'app.env',
            'APP_DEBUG'                   => 'app.debug',
            'APP_URL'                     => 'app.url',
            'APP_TIMEZONE'                => 'app.timezone',
            'APP_LOCALE'                  => 'app.locale',
            'ENGINE_CURRENCY'             => 'engine.currency',
            'LOG_LEVEL'                   => 'logging.level',
            'LOG_CHANNEL'                 => 'logging.channel',
            'LOG_PATH'                    => 'logging.path',
            'DB_DRIVER'                   => 'database.driver',
            'DB_HOST'                     => 'database.host',
            'DB_PORT'                     => 'database.port',
            'DB_DATABASE'                 => 'database.database',
            'DB_USERNAME'                 => 'database.username',
            'DB_PASSWORD'                 => 'database.password',
            'DB_CHARSET'                  => 'database.charset',
            'DB_COLLATION'                => 'database.collation',
            'DB_ATTR_PERSISTENT'          => 'database.persistent',
            'DB_ATTR_EMULATE_PREPARES'    => 'database.emulate_prepares',
            'SESSION_TTL_SECONDS'         => 'session.ttl_seconds',
            'SESSION_IDLE_TTL_SECONDS'    => 'session.idle_ttl_seconds',
            'GAME_DISCOVERY_NAMESPACES'   => 'games.discovery_namespaces',
            'DEFAULT_CURRENCY_MINOR_UNITS' => 'betting.currency_minor_units',
            'MIN_BET_MINOR'               => 'betting.min_bet_minor',
            'MAX_BET_MINOR'               => 'betting.max_bet_minor',
            'API_TOKEN_TTL_SECONDS'       => 'security.api_token_ttl_seconds',
            'API_RATE_LIMIT_PER_MINUTE'   => 'security.api_rate_limit_per_minute',
            'API_MAX_BODY_BYTES'          => 'security.api_max_body_bytes',
            'IDEMPOTENCY_TTL_SECONDS'     => 'security.idempotency_ttl_seconds',
            'AUDIT_RETENTION_DAYS'        => 'security.audit_retention_days',
            'RATE_LIMIT_RETENTION_DAYS'   => 'security.rate_limit_retention_days',
            'PASSWORD_ALGO'               => 'security.password_algo',
            'ALLOW_SELF_REGISTRATION'     => 'security.allow_self_registration',
            'REGISTRATION_STARTING_BALANCE_MINOR' => 'security.registration_starting_balance_minor',
            'ENGINE_VERSION_OVERRIDE'     => 'engine.version',
        ];

        $path = $map[$envKey] ?? null;
        if ($path === null) {
            return $this;
        }

        $current = $this->get($path);
        $coerced = match (true) {
            is_bool($current)  => Env::bool($envKey, (bool) $current),
            is_int($current)   => Env::int($envKey, (int) $current),
            is_float($current) => Env::float($envKey, (float) $current),
            is_array($current) => Env::list($envKey, $current),
            default            => $raw,
        };

        return $this->with($path, $coerced);
    }

    /** Reads a dot path, e.g. `database.host`. */
    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $cursor   = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /** Reads a dot path and fails loudly when it is absent. */
    public function require(string $path): mixed
    {
        if (!$this->has($path)) {
            throw new ConfigurationException("Required configuration key '$path' is missing.");
        }

        return $this->get($path);
    }

    public function has(string $path): bool
    {
        $segments = explode('.', $path);
        $cursor   = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }

        return true;
    }

    /** Returns a copy with one dot path replaced. */
    public function with(string $path, mixed $value): self
    {
        $copy = $this->items;
        $segments = explode('.', $path);
        $cursor = &$copy;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                break;
            }
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        unset($cursor);

        return new self($copy);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    public function environment(): string
    {
        return (string) $this->get('app.env', 'production');
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function isTesting(): bool
    {
        return $this->environment() === 'testing';
    }

    public function debug(): bool
    {
        return (bool) $this->get('app.debug', false);
    }

    public function rootPath(): string
    {
        return (string) $this->get('app.root', \dirname(__DIR__, 3));
    }

    /** Resolves a storage-relative path to an absolute one. */
    public function storagePath(string $relative = ''): string
    {
        $base = rtrim($this->rootPath(), '/') . '/storage';

        return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
    }

    public function logPath(): string
    {
        $configured = (string) $this->get('logging.path', 'storage/logs');

        return str_starts_with($configured, '/')
            ? $configured
            : rtrim($this->rootPath(), '/') . '/' . ltrim($configured, '/');
    }
}
