<?php

declare(strict_types=1);

/**
 * Copy this file to `config/database.local.php` and fill in the credentials
 * from your hosting control panel. The copy is ignored by Git.
 *
 * On shared hosting the database name is the one shown in the panel's MySQL
 * section (often `accountname_dbname`), NOT the database server hostname.
 * The host is usually something like `sql202.example.com` and the port 3306.
 */
return [
    'database' => [
        'driver'   => 'mysql',
        'host'     => 'sql202.example.com',
        'port'     => 3306,
        'database' => 'accountname_casino',
        'username' => 'accountname_casino',
        'password' => 'replace-with-your-database-password',
        'charset'  => 'utf8mb4',
    ],
];
