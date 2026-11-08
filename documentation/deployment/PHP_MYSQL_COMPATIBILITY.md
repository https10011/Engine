# PHP / MySQL Compatibility Assessment

What the application actually needs to run on ordinary PHP/MySQL shared
hosting, and what was verified versus assumed.

## 1. Runtime stack

| Layer | Requirement | Evidence |
| --- | --- | --- |
| PHP | **8.3+** | `composer.json` `"php": ">=8.3"`; typed properties, backed enums, `readonly`, `never`, first-class callable syntax, `str_contains` |
| Database | **MySQL 8.0+** or **MariaDB 10.6+** | JSON columns, `CHECK` constraints, `utf8mb4`, InnoDB, `ON DUPLICATE KEY UPDATE` |
| Web server | Apache 2.4 + `mod_rewrite`, LiteSpeed, or Nginx with `try_files` | `htdocs/.htaccess` |
| Runtime packages | `psr/log` only | `composer.json` `require` |
| Frontend | modern browser with ES modules, `<dialog>`, CSS custom properties | no transpiler, no polyfill, no CDN |

Verified locally on **PHP 8.3.32** and **MariaDB 10.11** (the same version
family used by cPanel hosts). PHP 8.4 is not part of the test matrix; nothing
in the code is known to break on it, but it was not run here.

## 2. PHP extensions

| Extension | Needed? | Used for |
| --- | --- | --- |
| `pdo` | **required** | database abstraction |
| `pdo_mysql` | **required in production** | MySQL driver; `composer.json` only declares `ext-pdo` |
| `json` | **required** | API payloads, JSON columns |
| `mbstring` | **required** | `mb_strlen` in validation |
| `openssl` | recommended | `random_bytes()` entropy source on some builds |
| `argon2` (`PASSWORD_ARGON2ID`) | optional | preferred password hash; automatic bcrypt fallback |
| `fileinfo` | not used | no upload feature |
| `curl` | not used | no outbound HTTP |
| `gd`/`imagick` | not used | artwork is static SVG |
| `zip` | not used | no archive feature |

Check with `php -m`. `pdo_mysql` is the extension most likely to be missing
from a minimal PHP build. Argon2 is the second; it is now optional.

### Password algorithm portability

`PASSWORD_ARGON2ID` is not defined on every PHP build. The original code used
the constant both as a config default and in a class-constant map, which would
have made the application **fatal on such a host**. This audit changed it:

* `config/app.php` now stores the name `'argon2id'`;
* `AuthService` resolves the algorithm at runtime;
* if `argon2id` is requested but unavailable, it uses bcrypt and stores
  `algo = 'bcrypt'` with the hash.

Verified by running the production tree with `PASSWORD_ALGO=bcrypt`: a new
account was created and logged in, and the database showed `bcrypt`; a normal
`argon2id` account continued to log in.

## 3. Database compatibility

Connection: PDO MySQL, `utf8mb4`, `utf8mb4_unicode_ci`, native prepared
statements (`DB_ATTR_EMULATE_PREPARES=false`), exceptions on error, no
persistent connections by default.

### Features used

| Feature | MySQL 8.0 | MariaDB 10.6+ | Notes |
| --- | --- | --- | --- |
| InnoDB foreign keys | yes | yes | all relations declared |
| `JSON` columns | yes | yes (alias for LONGTEXT + validation) | round `detail`, session state, probability snapshots |
| `CHECK` constraints | enforced | enforced | wallet balance non-negative |
| `ENUM` columns | yes | yes | status, category, role, type |
| `ON DUPLICATE KEY UPDATE` | yes | yes | wallets, statistics, rate limits, credentials |
| `UTF8MB4` | yes | yes | display names can contain emoji |
| Transactions + `SELECT … FOR UPDATE` | yes | yes | wallet settlement |
| `UTC_TIMESTAMP()` | yes | yes | all timestamps UTC |
| `BIGINT UNSIGNED` multipliers | yes | yes | widened by migration 5 |

No stored procedures, triggers, events, views, `WITH RECURSIVE`, spatial types,
window functions, MySQL-specific collations beyond `utf8mb4_unicode_ci`, or
`FULLTEXT` indexes are used. MySQL 5.7 would parse most of the schema but
`CHECK` is not enforced and it is **not supported**; use 8.0+.

### Schema size and import

`database/install.sql` creates **19 tables** and inserts the five migration
ledger rows. It is intended for a fresh database and imports cleanly through
phpMyAdmin. The same schema is applied by `php bin/console db:migrate` on a
host with a terminal.

### Privileges

The database user needs `SELECT, INSERT, UPDATE, DELETE` for normal operation,
plus `CREATE, ALTER, INDEX, REFERENCES` for migrations. A cPanel-generated user
with all privileges on its own database is sufficient. The application never
runs `CREATE DATABASE`, `DROP DATABASE`, `GRANT`, `FILE` or `SUPER`.

## 4. Session and state model

There is **no PHP session** (`session_start()` is never called) and no session
cookie. Authentication is a random bearer token stored in `api_tokens` as a
SHA-256 hash with an expiry. This avoids shared-host session-path problems and
works across multiple PHP workers. The trade-off is that the frontend must keep
the token in `sessionStorage` (or `localStorage` only with "keep me signed in").

Rate limiting is database-backed (`rate_limit_buckets`) rather than APCu/Redis,
so it works when the host isolates or disables shared memory.

## 5. Filesystem

| Path | Access | Notes |
| --- | --- | --- |
| `storage/logs/` | write | engine.json-lines log; created if missing (0775) |
| `storage/cache/` | write | reserved; nothing critical depends on it |
| everywhere else | read | no runtime writes |

There is **no upload feature**: no `uploads/` directory, no `move_uploaded_file`,
no user-controlled file writes. The absence is deliberate and audited.

Logging is best-effort: if `storage/logs` is not writable, the logger silently
skips the line rather than failing a request. That keeps the site up but hides
errors, so check permissions during installation.

## 6. Request/response model

* Each HTTP request boots the engine from `index.php`, opens PDO lazily and
  executes synchronously. No long-running process, queue or scheduler is
  needed.
* The SPA shell is returned for every non-`/api/` route; static files are
  served by the web server.
* API bodies are limited to 64 KiB (`API_MAX_BODY_BYTES`); PHP's
  `post_max_size`/`upload_max_filesize` are irrelevant because only JSON is
  accepted and no files are uploaded.
* Timeouts: normal shared-host PHP limits are fine; no request waits on an
  external service.

## 7. Hosting limitations to expect

| Limitation | Impact |
| --- | --- |
| Application must be at a domain root/subdomain | SPA routes and asset URLs are root-relative |
| Apache must allow `.htaccess` (`AllowOverride`) | otherwise routes 404 or everything 403s by design |
| Some hosts strip `Authorization` | the `.htaccess` rewrite restores it; Nginx needs `fastcgi_param HTTP_AUTHORIZATION` |
| PHP below 8.3 | will not run; choose 8.3+ in the panel |
| Missing `pdo_mysql` | cannot connect; enable the extension |
| No Argon2 | bcrypt fallback, slower/weaker but functional |
| No SSH/cron | install via phpMyAdmin; admin via SQL; maintenance purges optional |
| OPcache with `validate_timestamps=0` | upload changes may need a PHP restart/opcache reset |
| Very low `max_execution_time` | a normal request is milliseconds; only a huge admin simulation could exceed it |

## 8. Development vs production dependencies

| Dependency | Development | Build time | Production runtime |
| --- | --- | --- | --- |
| PHP 8.3+ | yes | — | yes |
| MySQL/MariaDB | yes | — | yes |
| Composer 2 | yes | **yes** (build `htdocs/vendor`) | no |
| PHPUnit / PHPStan / PHPCS | yes | no | no |
| Node.js | browser verification scripts only | no | no |
| Playwright + Chromium | verification only | no | no |
| `psr/log` | yes | bundled | yes |
| External APIs/CDNs | no | no | no |

## 9. Test evidence

* `composer check`: 277 tests / 22,791 assertions, PHPStan clean, lint clean.
* `htdocs/` imported into a fresh MariaDB 10.11 database: 19 tables, 5
  migrations, `db:status` reports no drift.
* Production entry point served HTML, CSS and JSON with correct MIME types.
* Registration, login, bearer authentication and both password algorithms
  worked against the production tree.
* Mobile and desktop Chromium runs against `htdocs/` completed with zero
  console errors and five real settled rounds.

Not verified: a real Apache/LiteSpeed server, a specific hosting provider's PHP
build, MySQL 8.0 (only MariaDB was available), HTTPS, and email (none is sent).
