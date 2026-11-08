<?php

declare(strict_types=1);

/**
 * Engine configuration.
 *
 * Values here are the defaults. Anything can be overridden by an environment
 * variable of the same UPPER_SNAKE_CASE name, which is how production is
 * configured without editing files.
 */

return [
    'app' => [
        'name'     => 'Universal Casino Game Engine',
        'env'      => 'development',
        'debug'    => true,
        'url'      => 'http://localhost:8000',
        'timezone' => 'Asia/Manila',
        'locale'   => 'en',
        'root'     => \dirname(__DIR__),
    ],

    'engine' => [
        'version'  => '1.0.0',
        'currency' => 'PHP',
        // Minimum SDK contract version this engine accepts from a module.
        'min_sdk_version' => '1.0.0',
    ],

    'logging' => [
        'level'   => 'debug',
        'channel' => 'file',
        'path'    => 'storage/logs',
        'redact'  => [
            'password',
            'password_confirmation',
            'token',
            'api_token',
            'authorization',
            'secret',
            'db_password',
        ],
    ],

    'database' => [
        'driver'            => 'mysql',
        'host'              => '127.0.0.1',
        'port'              => 3306,
        'database'          => 'casino_engine',
        'username'          => 'engine',
        'password'          => '',
        'charset'           => 'utf8mb4',
        'collation'         => 'utf8mb4_unicode_ci',
        'persistent'        => false,
        'emulate_prepares'  => false,
    ],

    'session' => [
        'ttl_seconds'      => 3600,
        'idle_ttl_seconds' => 1800,
    ],

    'games' => [
        // Namespaces scanned by GameDiscovery for GameInterface implementors.
        'discovery_namespaces' => [
            'CasinoEngine\\Games',
        ],
        // Explicitly registered game ids. Empty means "everything discovered".
        'enabled_ids' => [],
    ],

    'betting' => [
        'currency_minor_units' => 2,
        'min_bet_minor'        => 100,
        'max_bet_minor'        => 1000000,
    ],

    'security' => [
        'password_algo'             => \PASSWORD_ARGON2ID,
        'api_token_ttl_seconds'     => 86400,
        'api_rate_limit_per_minute' => 120,
        'api_max_body_bytes'        => 65536,
        'idempotency_ttl_seconds'   => 86400,
        // Self-service player registration. Additive to the API; an operator
        // can close it and keep provisioning accounts with `user:create`.
        'allow_self_registration'   => true,
        // Virtual credits granted to a new account (minor units; 100000 = PHP 1,000.00).
        'registration_starting_balance_minor' => 100000,
        // Retention windows for the `*:purge` cron commands. Kept here so an
        // operator tunes them by environment variable, not by editing SQL.
        'audit_retention_days'      => 90,
        'rate_limit_retention_days' => 7,
    ],
];
