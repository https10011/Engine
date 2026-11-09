<?php

declare(strict_types=1);

/**
 * Database settings.
 *
 * This file is committed and intentionally contains no hosting credentials.
 * `Config::fromDefaults()` loads every `config/*.php` file and merges its
 * top-level sections, so `['database' => [...]]` here overrides the database
 * block in `app.php`.
 *
 * Put real credentials in `config/database.local.php`, which is gitignored.
 * Start from `config/database.local.example.php`. The local file may return
 * either of these equivalent shapes:
 *
 *   return ['database' => ['host' => '...', ...]];
 *   return ['host' => '...', ...];
 *
 * Environment variables (`DB_HOST`, `DB_DATABASE`, ...) still take precedence
 * over both files, which allows a host to supply secrets without a file.
 */

$defaults = [
    'driver'           => 'mysql',
    'host'             => '127.0.0.1',
    'port'             => 3306,
    'database'         => 'casino_engine',
    'username'         => 'engine',
    'password'         => '',
    'charset'          => 'utf8mb4',
    'collation'        => 'utf8mb4_unicode_ci',
    'persistent'       => false,
    'emulate_prepares' => false,
];

$localFile = __DIR__ . '/database.local.php';
$local = [];

if (is_file($localFile)) {
    /** @var mixed $loaded */
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = isset($loaded['database']) && is_array($loaded['database'])
            ? $loaded['database']
            : $loaded;
    }
}

return [
    'database' => array_replace($defaults, array_intersect_key($local, $defaults)),
];
