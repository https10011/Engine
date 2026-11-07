<?php

declare(strict_types=1);

namespace CasinoEngine\Core;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Config\Env;

/**
 * Wires the engine up for a process.
 *
 * Configuration precedence is: real environment variables, then a `.env` file,
 * then the defaults in `config/*.php`. That order lets a shared host set
 * nothing at all and still run, while a container can override anything.
 */
final class Bootstrap
{
    public static function config(?string $rootPath = null, ?string $environment = null): Config
    {
        $root = $rootPath ?? \dirname(__DIR__, 2);

        Env::loadFile($root . '/.env');

        if ($environment !== null) {
            $_ENV['APP_ENV'] = $environment;
            putenv('APP_ENV=' . $environment);
        }

        $config = Config::fromDefaults($root . '/config', [
            'APP_NAME',
            'APP_ENV',
            'APP_DEBUG',
            'APP_URL',
            'APP_TIMEZONE',
            'APP_LOCALE',
            'ENGINE_CURRENCY',
            'LOG_LEVEL',
            'LOG_CHANNEL',
            'LOG_PATH',
            'DB_DRIVER',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'DB_CHARSET',
            'DB_COLLATION',
            'DB_ATTR_PERSISTENT',
            'DB_ATTR_EMULATE_PREPARES',
            'SESSION_TTL_SECONDS',
            'SESSION_IDLE_TTL_SECONDS',
            'GAME_DISCOVERY_NAMESPACES',
            'DEFAULT_CURRENCY_MINOR_UNITS',
            'MIN_BET_MINOR',
            'MAX_BET_MINOR',
            'API_TOKEN_TTL_SECONDS',
            'API_RATE_LIMIT_PER_MINUTE',
            'API_MAX_BODY_BYTES',
            'IDEMPOTENCY_TTL_SECONDS',
        ]);

        date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));

        return $config;
    }

    public static function engine(?string $rootPath = null, ?string $environment = null): Engine
    {
        return Engine::boot(self::config($rootPath, $environment));
    }

    /**
     * Boots an engine pointed at the test database. Used by the integration
     * suite so tests can never touch development data.
     */
    public static function testEngine(?string $rootPath = null): Engine
    {
        $config = self::config($rootPath, 'testing');

        $testDatabase = Env::get('DB_TEST_DATABASE');
        if ($testDatabase !== null && $testDatabase !== '') {
            $config = $config->with('database.database', $testDatabase);
        }

        return Engine::boot($config);
    }
}
