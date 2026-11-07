# Deployment Guide

> Conventional PHP/MySQL shared hosting. No Docker, no Node.js server, no
> WebSockets, no background workers, no cloud services are required in
> production.

**Virtual credits only.** Deployment creates no payment, deposit or withdrawal
path, because none exists in the code.

---

## 1. Requirements

| Requirement | Version | Why |
| --- | --- | --- |
| PHP | 8.3 or newer | `require: php >=8.3` |
| Extensions | `pdo_mysql`, `json`, `mbstring` | database access, API payloads, text handing |
| MySQL / MariaDB | 8.0 / 10.4+ | `CHECK` constraints, JSON columns, `InnoDB` |
| Composer | 2.x | installs the autoloader and dev tools |

The engine has no runtime dependency beyond `psr/log`. PHPUnit, PHPStan and
PHP_CodeSniffer are development-only and need not be installed on the server.

---

## 2. Layout

The single most important deployment decision is that the web root is `public/`,
and everything else sits **above** it.

```
/  (the deploy root)
├── public/            <-- web root; the ONLY reachable directory
│   ├── index.php      front controller
│   └── .htaccess      rewrite + security headers
├── src/               application source          (private)
├── config/            configuration               (private)
├── database/          migrations + schema         (private)
├── storage/           logs, cache, sim exports    (private)
├── tests/             test suite                  (private)
├── vendor/            Composer dependencies       (private)
├── bin/               CLI entry points            (private)
├── composer.json
├── .env               secrets                     (private, gitignored)
└── *.md               documentation
```

If the host forces a single document root, point it at `public/` if at all
possible. When that is impossible, keep the private directories above the web
root and place only `public/`'s two files in the document root; the front
controller resolves the application root with `dirname(__DIR__)` so neither
arrangement is hardcoded.

`public/.htaccess` sends every non-file request to `index.php`, restores the
`Authorization` header that some CGI/FastCGI setups strip, denies dotfiles, and
sets `X-Content-Type-Options`, `Referrer-Policy` and `X-Frame-Options`. On nginx
the equivalent is a `try_files` fallback to `/index.php` plus a `fastcgi_param
HTTP_AUTHORIZATION` line.

---

## 3. Install

```bash
# 1. Get the code
git clone <repository> engine && cd engine

# 2. Production dependencies only (no dev tools)
composer install --no-dev --optimize-autoloader

# 3. Configuration
cp .env.example .env
# edit .env — see section 4

# 4. Create the schema
php bin/console db:migrate

# 5. Confirm
php bin/console db:status
php bin/console games:list
```

Set the file permissions the host expects — typically the web server user needs
read access to `src/` and write access **only** to `storage/`:

```bash
chmod -R 750 src config database bin
chmod -R 770 storage
```

`storage/logs`, `storage/cache` and `storage/simulation` are the only writable
paths.

---

## 4. Configuration

Configuration is read by `Config::fromDefaults()` from `config/*.php`, then
overridden by an explicit list of environment variables. **Only variables on
that list have any effect** — a typo cannot silently change behaviour.

Prefer real environment variables over `.env` in production, wherever the host
supports them. `.env` is gitignored; never commit it.

### Essential variables

| Variable | Example | Notes |
| --- | --- | --- |
| `APP_ENV` | `production` | **required**; disables debug output and refuses seeded RNG |
| `APP_DEBUG` | `false` | **must be false in production** |
| `APP_URL` | `https://example.test` | |
| `APP_TIMEZONE` | `Asia/Manila` | |
| `ENGINE_CURRENCY` | `PHP` | virtual pesos only |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | | |
| `DB_CHARSET` | `utf8mb4` | |
| `LOG_LEVEL` | `warning` or `error` | `debug` in production is noisy and can leak detail |
| `LOG_CHANNEL` | `file` | or `stdout` / `null` |
| `PASSWORD_ALGO` | `argon2id` | or `bcrypt` if the host's PHP lacks Argon2 |
| `API_TOKEN_TTL_SECONDS` | `86400` | |
| `API_RATE_LIMIT_PER_MINUTE` | `120` | |
| `IDEMPOTENCY_TTL_SECONDS` | `86400` | |

`ENGINE_VERSION_OVERRIDE` is optional and, when set, overrides the version the
health endpoint reports — useful for stamping a release. Leave it empty
otherwise.

`DB_TEST_DATABASE` and `GAME_DISCOVERY_NAMESPACES` are safe to leave at their
defaults in production. The test database is only used by the integration
suite; it must never point at the production schema.

### Optional: a non-public environment file

Some hosts forbid `.env` above the web root. Putting the file anywhere the
process can read it is fine; `Env::loadFile()` takes a path. If the host offers
per-site environment variables, use those and skip the file entirely.

---

## 5. Launch

Point the web server at `public/`. Confirm with:

```bash
curl -sS https://example.test/api/v1/health
# {"ok":true,"data":{"status":"ok","engine":"universal-casino-engine","version":"1.0.0"},"error":null,"meta":{}}
```

For local development only, the CLI has a throwaway server:

```bash
php bin/console serve --host=127.0.0.1 --port=8000
```

It refuses to start when `APP_ENV=production`, because a single-threaded
development server is not a production front end.

Create the owner account and give it virtual credits:

```bash
php bin/console user:create --username=owner --password='a-long-passphrase' --balance=1000.00
php bin/console wallet:show --username=owner
```

Then log in through the API to obtain a bearer token. See
[API_SPECIFICATION.md](API_SPECIFICATION.md) for the full flow.

---

## 6. Scheduled maintenance (optional)

None of these is required for correctness; each just keeps a table small. Wire
them to the host's cron if available, at whatever cadence you prefer:

```cron
17 3 * * *  php /path/to/engine/bin/console token:purge
27 3 * * *  php /path/to/engine/bin/console session:expire
37 3 * * *  php /path/to/engine/bin/console idempotency:purge
47 3 * * *  php /path/to/engine/bin/console audit:purge
57 3 * * *  php /path/to/engine/bin/console ratelimit:purge
```

Each reads its retention window from configuration
(`AUDIT_RETENTION_DAYS`, `RATE_LIMIT_RETENTION_DAYS`, and so on), so changing
the window needs no code edit. If the host provides no cron at all, the engine
still works — the tables simply grow.

---

## 7. Upgrades

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/console db:migrate
php bin/console db:status     # must report no drift
```

Migrations are `CREATE TABLE IF NOT EXISTS` and are additive. The runner records
each migration's checksum, and `db:status` reports **drift** if a migration file
changed after being applied — so an edited migration is caught rather than
silently skipped. Take a database backup before an upgrade as a matter of
routine.

---

## 8. Security checklist for go-live

* `APP_ENV=production` and `APP_DEBUG=false`.
* A long, unique `DB_PASSWORD`; the database user is limited to this schema.
* HTTPS enforced; the API is bearer-token based and a token over plain HTTP is
  a token disclosed.
* Web root is `public/`; confirm `https://example.test/../src/Core/Engine.php`
  and similar paths return 404/403, not source.
* `storage/logs` is not web-reachable.
* `.env` is present on disk and absent from Git.
* `composer install --no-dev` on the server — no test tooling in production.
* Cron commands (if used) run as a user that can write `storage/` and connect to
  the database.

The reasoning behind each item, and the residual risks, are in
[SECURITY_AUDIT.md](SECURITY_AUDIT.md).

---

## 9. Back-up and restore

Only MySQL state matters; there is no file-based game state.

```bash
mysqldump --single-transaction casino_engine > backup.sql
# restore
mysql casino_engine < backup.sql
```

The `wallet_transactions` ledger is the audit trail for every balance movement,
so a restored database can be reconciled against it if a balance is ever in
doubt (see [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md), section 3).

---

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `NOT_INSTALLED` JSON from the front controller | `vendor/` missing | `composer install` |
| `500` with no detail | `APP_DEBUG=false` is hiding it by design | set `APP_DEBUG=true` **temporarily**, read the log in `storage/logs`, then turn it back off |
| Health route 404s, everything else works | rewrite rules not applied | enable `mod_rewrite` / add the nginx `try_files` fallback |
| Login always 401 | `Authorization` header stripped by the SAPI | the `.htaccess` restores it; on nginx set `fastcgi_param HTTP_AUTHORIZATION` |
| `SQLSTATE[HY000] [2002]` | wrong `DB_HOST`/`DB_PORT`, or MySQL not listening | check the connection values |
| Migration "drift" reported | a migration file was edited after it was applied | do not edit applied migrations; add a new one |
| Argon2 unavailable | PHP built without Argon2 | set `PASSWORD_ALGO=bcrypt` |
| Logs grow without bound | no purge cron | see section 6 |

---

## 11. Hosting limitations, stated plainly

* **No persistent processes.** There is nothing to keep running; every request
  is independent. This is what makes the API work on shared hosting.
* **Rate limiting is table-backed, not Redis-backed.** It survives without an
  extra service, at the cost of a database write per limited request. That cost
  is bounded and has an index behind it.
* **No WebSockets.** The future frontend polls or requests; it is not pushed to.
* **Single-database.** No read replicas, no sharding — none is warranted at this
  scale, and none is assumed.

The full limitation list, including development-side gaps, is in
[KNOWN_ISSUES.md](KNOWN_ISSUES.md).
