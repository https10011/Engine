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
3. Copy `config/database.local.example.php` to `config/database.local.php` and
   fill in the host, port, database, username, password and charset supplied by
   the hosting panel. This file is Git-ignored and denied over HTTP. Database
   credentials do **not** go in `.env`.
4. Import `database/install.sql` with phpMyAdmin (Import → choose file → Go).
5. Make `storage/` writable by PHP (usually `chmod -R 775 storage`; on some
   hosts the files need to be owned by the PHP user).
6. Visit the site and register with a phone number, display name, age and
   password. The account receives exactly 300 credits once. The OTP step is a
   local demonstration (browser notification, no SMS); any six digits complete
   it.
7. Open `/admin.php` and sign in with the seeded administrator `admin` /
   `Tyroar123`. Change that password immediately.

## 3. Administrator console

`/admin.php` is separate from the player SPA. It has its own PHP session,
server-side role checks on every request, CSRF tokens on every mutation and an
audit trail. Player credentials cannot be used there.

The dashboard shows registered users, recently active players, credits in
circulation, game activity, recent registrations and rounds. User pages support
search, detail, audited credit adjustments and suspend/reactivate. The games
page controls per-game availability, odds targets, jackpot/bonus/free-spin
probabilities, payout scale and a clearly marked friendly-play preset. Saved
settings are persisted and used by the engine on the next round.

To change the initial password, use the host terminal:

```bash
php bin/console user:password --username=admin --password='a-new-long-password'
```

Without SSH, generate a bcrypt hash with
`php -r 'echo password_hash("a-new-long-password", PASSWORD_BCRYPT), PHP_EOL;'`
and update `user_credentials.password_hash` for the `admin` row in phpMyAdmin.

## 4. CLI on the host

If the host provides SSH or a terminal:

```bash
php bin/console db:status
php bin/console db:migrate
php bin/console user:create --username=player --password='a-long-password'
php bin/console user:admin --username=player
php bin/console user:password --username=admin --password='a-new-long-password'
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
* Keep `config/database.local.php` out of Git and out of web-accessible paths;
  the shipped `config/.htaccess` denies it.