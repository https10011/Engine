<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader and the test helpers. The integration suite
 * decides for itself whether a database is reachable; the unit, framework and
 * simulation suites never need one.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoloader not found. Run 'composer install' first.\n");
    exit(1);
}

require $autoload;

require __DIR__ . '/Support/FrozenClock.php';
require __DIR__ . '/Support/TestCase.php';
require __DIR__ . '/Support/DatabaseTestCase.php';
