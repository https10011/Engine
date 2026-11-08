<?php

declare(strict_types=1);

/**
 * Build the self-contained shared-hosting bundle in `htdocs/`.
 *
 * The repository keeps source, tests, tools and docs at the top level. Shared
 * hosting wants one public directory that contains the application and nothing
 * that is not needed at runtime. This script assembles exactly that:
 *
 *   - the production front controller and SPA shell
 *   - the frontend assets that are actually referenced
 *   - `src/`, `config/`, migrations, CLI console and a `--no-dev` Composer
 *     autoloader (game discovery reads Composer's PSR-4 map)
 *   - a single importable `database/install.sql` with the migration ledger
 *   - deny rules for every private directory
 *   - `.env.example` with production-safe defaults
 *
 * It does not copy tests, tools, docs, Git history, `AH77_SOURCE`, screenshots
 * or development dependencies.
 *
 * Usage:
 *   PATH="$HOME/bin:$PATH" php tools/build_htdocs.php
 *
 * `htdocs/` is generated; edit the source tree and rebuild. The script refuses
 * to delete a directory it did not create (looks for `.htdocs-build`).
 */

$root = dirname(__DIR__);
$target = $root . '/htdocs';

function fail(string $message): never
{
    fwrite(STDERR, "build_htdocs: {$message}\n");
    exit(1);
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

function copyTree(string $from, string $to): void
{
    if (!is_dir($to) && !mkdir($to, 0775, true) && !is_dir($to)) {
        fail("cannot create {$to}");
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $destination = $to . '/' . $items->getSubPathname();
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                fail("cannot create {$destination}");
            }
        } else {
            if (!copy($item->getPathname(), $destination)) {
                fail("cannot copy {$item->getPathname()}");
            }
            chmod($destination, $item->getPerms() & 0777);
        }
    }
}

function put(string $path, string $contents): void
{
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
        fail('cannot create ' . dirname($path));
    }
    if (file_put_contents($path, $contents) === false) {
        fail("cannot write {$path}");
    }
}

// Keep a configured local .env across rebuilds; it is gitignored and is the one
// file an operator edits in place on the server.
$savedEnv = is_file($target . '/.env') ? file_get_contents($target . '/.env') : null;

if (is_dir($target)) {
    if (!is_file($target . '/.htdocs-build')) {
        fail('htdocs/ exists but was not produced by this script; refusing to delete it.');
    }
    removeTree($target);
}

mkdir($target, 0775, true);

// ---------------------------------------------------------------- application
copyTree($root . '/src', $target . '/src');
copyTree($root . '/config', $target . '/config');
copyTree($root . '/database/migrations', $target . '/database/migrations');
copyTree($root . '/public/assets', $target . '/assets');

foreach (['index.php', 'app.html'] as $file) {
    if (!copy($root . '/public/' . $file, $target . '/' . $file)) {
        fail("cannot copy public/{$file}");
    }
}

if (!is_dir($target . '/bin') && !mkdir($target . '/bin', 0775, true)) {
    fail('cannot create htdocs/bin');
}
if (!copy($root . '/bin/console', $target . '/bin/console')) {
    fail('cannot copy bin/console');
}
chmod($target . '/bin/console', 0755);

// ------------------------------------------------------------------- storage
mkdir($target . '/storage/logs', 0775, true);
mkdir($target . '/storage/cache', 0775, true);
put($target . '/storage/logs/.gitkeep', '');
put($target . '/storage/cache/.gitkeep', '');

// ------------------------------------------------------- runtime autoloader
$composer = getenv('COMPOSER_BINARY') ?: trim((string) shell_exec('command -v composer 2>/dev/null'));
if ($composer === '' || !is_file($composer)) {
    fail('Composer 2 is required to build the production autoloader; set COMPOSER_BINARY.');
}

$staging = sys_get_temp_dir() . '/aurum-htdocs-vendor-' . bin2hex(random_bytes(6));
mkdir($staging, 0775, true);
copy($root . '/composer.json', $staging . '/composer.json');
copy($root . '/composer.lock', $staging . '/composer.lock');
copyTree($root . '/src', $staging . '/src');

$command = escapeshellarg($composer)
    . ' install --no-dev --no-scripts --no-interaction --optimize-autoloader'
    . ' --working-dir=' . escapeshellarg($staging) . ' 2>&1';
exec($command, $output, $status);
if ($status !== 0) {
    removeTree($staging);
    fail("Composer failed:\n" . implode("\n", $output));
}
if (!is_file($staging . '/vendor/autoload.php')) {
    removeTree($staging);
    fail('Composer did not produce vendor/autoload.php.');
}
copyTree($staging . '/vendor', $target . '/vendor');
removeTree($staging);

// ------------------------------------------------------------- .env.example
put($target . '/.env.example', <<<'ENV'
# ---------------------------------------------------------------------------
# AURUM / Universal Casino Game Engine — shared-hosting configuration.
#
# Copy this file to `.env` (same directory) and fill in the database values
# supplied by the hosting control panel. Never commit `.env`.
#
# VIRTUAL CREDITS ONLY. There is no payment, deposit, withdrawal or crypto
# configuration anywhere in this application.
# ---------------------------------------------------------------------------

APP_NAME="AURUM Casino Engine"
APP_ENV=production
APP_DEBUG=false
# Public URL of the site, e.g. https://example.com (no trailing slash).
APP_URL=https://example.com
APP_TIMEZONE=Asia/Manila
APP_LOCALE=en

ENGINE_CURRENCY=PHP
ENGINE_VERSION_OVERRIDE=

LOG_LEVEL=warning
LOG_CHANNEL=file
LOG_PATH=storage/logs

# Database supplied by the host. On cPanel/Plesk this is usually localhost,
# a prefixed database name and a prefixed user name.
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_ATTR_PERSISTENT=false
DB_ATTR_EMULATE_PREPARES=false

SESSION_TTL_SECONDS=3600
SESSION_IDLE_TTL_SECONDS=1800

GAME_DISCOVERY_NAMESPACES="CasinoEngine\\Games"

DEFAULT_CURRENCY_MINOR_UNITS=2
MIN_BET_MINOR=100
MAX_BET_MINOR=1000000

# argon2id is preferred; the engine falls back to bcrypt automatically when
# the host's PHP build has no Argon2 support.
PASSWORD_ALGO=argon2id
API_TOKEN_TTL_SECONDS=86400
API_RATE_LIMIT_PER_MINUTE=120
API_MAX_BODY_BYTES=65536
IDEMPOTENCY_TTL_SECONDS=86400
AUDIT_RETENTION_DAYS=90
RATE_LIMIT_RETENTION_DAYS=7

# Virtual-credit self-registration. Set to false to close public sign-ups and
# create accounts only with `php bin/console user:create`.
ALLOW_SELF_REGISTRATION=true
REGISTRATION_STARTING_BALANCE_MINOR=100000
ENV);

// --------------------------------------------------------------- .htaccess
put($target . '/.htaccess', <<<'HTACCESS'
# AURUM production front controller (Apache / LiteSpeed shared hosting).
#
# The document root must be this directory. Source, configuration, migrations,
# vendor code and logs live below it and are denied by their own .htaccess
# files; the rewrite sends application routes to index.php while letting real
# static files through.

Options -Indexes
DirectoryIndex index.php

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Some CGI/FastCGI hosts strip Authorization; carry it through so bearer
    # tokens still reach the API.
    RewriteCond %{HTTP:Authorization} ^(.*)
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]

    # Existing files (CSS, JS, SVG) are served by the web server. Everything
    # else is an application route handled by the front controller.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>

<IfModule !mod_rewrite.c>
    # Without rewriting the SPA routes cannot work; fail closed rather than
    # exposing a directory listing or a partial application.
    Require all denied
</IfModule>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "no-referrer"
    Header always set X-Frame-Options "DENY"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header always set Content-Security-Policy "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'none'"
    # Enable after HTTPS is confirmed:
    # Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Dotfiles (.env, .htaccess, build marker) are never served.
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Backups, schema dumps and documentation must not be downloadable.
<FilesMatch "\.(env|sql|md|log|ini|lock|json|dist|neon|sh|bak|old|orig|save|swp)$">
    Require all denied
</FilesMatch>

# Long-lived, immutable assets; the filenames do not change between builds.
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 30 days"
    ExpiresByType application/javascript "access plus 30 days"
    ExpiresByType image/svg+xml "access plus 30 days"
</IfModule>

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml application/json
</IfModule>
HTACCESS);

// Deny HTTP access to every directory that contains code or data. Apache 2.4
// uses Require; the fallback keeps Apache 2.2 hosts closed too.
$deny = <<<'HTACCESS'
# Private application directory: never served over HTTP.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
HTACCESS;

foreach (['src', 'config', 'database', 'vendor', 'storage', 'bin'] as $private) {
    put($target . '/' . $private . '/.htaccess', $deny);
}

// ------------------------------------------------------------ install.sql
$migrationFiles = glob($root . '/database/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);
if ($migrationFiles === []) {
    fail('no migration files found');
}

$sql = <<<'SQL'
-- ---------------------------------------------------------------------------
-- AURUM / Universal Casino Game Engine — complete schema for a fresh database.
--
-- Import this ONE file into the database created by your hosting control panel
-- (phpMyAdmin → Import, or `mysql dbname < install.sql`). It creates every
-- table and records the migrations as applied, so `db:migrate` will report the
-- database as up to date instead of trying to apply them again.
--
-- Import into an EMPTY database, once. It is not a migration for an existing
-- installation; use `php bin/console db:migrate` to upgrade one of those.
--
-- It creates no user account and no demo data. Register the first player in the
-- browser, or use: php bin/console user:create --username=... --password=...
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

SQL;

$ledger = [];
foreach ($migrationFiles as $file) {
    $name = basename($file);
    $version = basename($file, '.sql');
    $checksum = hash_file('sha256', $file);
    if ($checksum === false) {
        fail("cannot hash {$name}");
    }
    $ledger[] = sprintf(
        "INSERT IGNORE INTO `migrations` (`version`, `name`, `applied_at`, `checksum`)"
        . " VALUES ('%s', '%s', UTC_TIMESTAMP(), '%s');",
        $version,
        $name,
        $checksum
    );

    $sql .= "\n-- ---------------------------------------------------------------------------\n";
    $sql .= "-- Migration: {$name}\n";
    $sql .= "-- ---------------------------------------------------------------------------\n\n";
    $contents = file_get_contents($file);
    if ($contents === false) {
        fail("cannot read {$name}");
    }
    $sql .= trim($contents) . "\n";
}

$sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n\n";
$sql .= "-- Record what this file applied, so the runtime migration runner agrees.\n";
$sql .= implode("\n", $ledger) . "\n";

put($target . '/database/install.sql', $sql);

// -------------------------------------------------------------- htdocs README
put($target . '/README.md', <<<'README'
# AURUM — deployable application

This directory is the complete production application. Upload **its contents**
(not the enclosing repository) to the hosting account's public web directory,
configure the database, import `database/install.sql`, and open the site.

The application must be at the **domain root** (or its own subdomain): the SPA
uses root-relative routes such as `/slots/...` and `/api/v1`.

## 1. Requirements

* PHP 8.3+ with `pdo_mysql`, `json`, `mbstring`; `openssl` recommended.
  Argon2id is optional — bcrypt is used automatically when unavailable.
* MySQL 8.0+ or MariaDB 10.6+ (InnoDB, `utf8mb4`).
* Apache with `mod_rewrite` (or LiteSpeed, which honours `.htaccess`).
  Nginx needs the equivalent `try_files $uri $uri/ /index.php?$query_string;`.
* No Node.js, Python, Docker, Composer, Redis, CDN, external API, WebSocket or
  background worker is required at runtime.

## 2. Install

1. Create a MySQL database and user in the hosting panel.
2. Upload the contents of this directory to `public_html/` (or the domain root).
3. Copy `.env.example` to `.env` and enter `DB_HOST`, `DB_DATABASE`,
   `DB_USERNAME`, `DB_PASSWORD`, and the public `APP_URL`.
4. Import `database/install.sql` with phpMyAdmin (Import → choose file → Go).
5. Make `storage/` writable by PHP (usually `chmod -R 775 storage`; on some
   hosts the files need to be owned by the PHP user).
6. Visit the site and register the first player.

The database credentials live only in `.env`; it is denied over HTTP and must
never be committed.

## 3. First administrator (optional)

Register a normal account in the browser, then either run:

```bash
php bin/console user:admin --username=your_username
```

or, without SSH, run this once in phpMyAdmin:

```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```

The Admin entry then appears in the account drawer. Administrator rights are
resolved server-side; no client value can grant them.

## 4. CLI on the host

If the host provides SSH or a terminal:

```bash
php bin/console db:status
php bin/console db:migrate
php bin/console user:create --username=player --password='a-long-password'
php bin/console user:admin --username=player
```

Cron jobs (optional) purge expired rows:

```bash
php bin/console token:purge
php bin/console session:expire
php bin/console idempotency:purge
php bin/console rate-limit:purge
php bin/console audit:purge
```

## 5. Security notes

* Set `APP_ENV=production` and `APP_DEBUG=false` (already the defaults here).
* Serve over HTTPS and uncomment the HSTS header in `.htaccess`.
* `.htaccess` denies `.env`, SQL, Markdown, logs, configuration, source,
  migrations, vendor code and `bin/`.
* There is no upload feature, no payment path and no PHP session cookie; API
  authentication uses random bearer tokens stored only as SHA-256 hashes.
* Close public registration with `ALLOW_SELF_REGISTRATION=false` if accounts
  should be invite-only.
README);

if (is_string($savedEnv)) {
    put($target . '/.env', $savedEnv);
    chmod($target . '/.env', 0600);
}

put($target . '/.htdocs-build', json_encode([
    'generated_at' => gmdate('c'),
    'generator'    => 'tools/build_htdocs.php',
    'note'         => 'Generated production bundle. Edit the source tree and rebuild; do not edit these files directly.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$fileCount = 0;
$bytes = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $item) {
    if ($item->isFile()) {
        $fileCount++;
        $bytes += $item->getSize();
    }
}

printf("Built htdocs/: %d files, %.2f MB\n", $fileCount, $bytes / 1048576);
