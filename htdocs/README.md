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