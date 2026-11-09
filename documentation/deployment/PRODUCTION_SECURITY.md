# Production Security

How the deployable application protects itself, what the operator must do, and
which risks remain. The detailed engine audit is in
[the backend security audit](../backend/SECURITY_AUDIT.md); this document is the
deployment-facing checklist.

## 1. What is protected, and against whom

The application holds virtual credits and account credentials. The realistic
threats are credential stuffing, account takeover, database exposure through a
misconfigured web root, and abuse of public registration. There is no payment
rail, no stored value redeemable for money, and no file upload, which removes
whole classes of risk that a real-money casino would carry.

## 2. Enforced by the application

| Control | Implementation | Evidence |
| --- | --- | --- |
| Password hashing | `password_hash()` with Argon2id; bcrypt fallback when the host lacks Argon2 | `AuthService`; production test showed `argon2id` and `bcrypt` rows |
| Token storage | 32 random bytes, hex; only SHA-256 hash stored | `api_tokens.token_hash`, `AuthService::issueToken` |
| Token transport | `Authorization: Bearer …`; never a URL or cookie | all authenticated routes |
| Login enumeration | identical 401 for unknown user and wrong password; dummy verify for timing | `AuthService::login` |
| Admin authorisation | `users.role` read from the database per request | `ApiKernel` admin guard |
| SQL injection | PDO native prepared statements in repositories; no string-built SQL from input | `src/Persistence` |
| XSS | frontend builds DOM nodes and uses `textContent`; the only `innerHTML` use is the authored icon path table | `public/assets/js/lib/dom.js`, `icons.js` |
| CSRF | authenticated mutations require an explicit bearer header, which a cross-site form cannot set; no cookie-based auth | `public/assets/js/lib/api.js` |
| Body size | 64 KiB JSON limit | `API_MAX_BODY_BYTES` |
| Rate limiting | database-backed buckets per user/IP/route | `rate_limit_buckets`, `RateLimitRepository` |
| Idempotency | client key + unique database constraint prevents double settlement | `idempotency_keys` |
| Error output | `display_errors=0` in the front controller; structured logs with redaction | `public/index.php`, `Logger` |
| Response headers | CSP, `nosniff`, `no-referrer`, `X-Frame-Options: DENY` | `index.php` and `.htaccess` |
| Private files | per-directory `.htaccess` deny + root deny rules + front-controller guard | `htdocs/` |

No PHP session is used, so session fixation, shared-host session-file
exposure and `session.cookie_*` misconfiguration do not apply. The trade-off is
that a bearer token in browser storage is readable by any script that manages
to execute in the origin — hence the strict CSP and the no-`innerHTML` rule.

## 3. Enforced by `htdocs/.htaccess`

* `Options -Indexes` — no directory listings.
* Rewrite all non-file requests to `index.php`; real assets are served.
* Restore `Authorization` for CGI/FastCGI hosts that strip it.
* Deny dotfiles (`.env`, `.htaccess`, `.htdocs-build`).
* Deny `.env`, `.sql`, `.md`, `.log`, `.ini`, `.lock`, `.json`, `.dist`, `.neon`,
  `.sh`, backup suffixes.
* Security headers, including CSP.
* Long-lived caching for CSS/JS/SVG (filenames change per release).
* `src/`, `config/`, `database/`, `vendor/`, `storage/` and `bin/` each carry
  `Require all denied` with an Apache 2.2 fallback.
* If `mod_rewrite` is unavailable, the root `.htaccess` **denies everything**
  rather than exposing a partial application.

`index.php` repeats the private-path denial for PHP's built-in server, so a
local smoke test cannot accidentally expose source or `.env`.

## 4. Operator checklist

### Before going live

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (defaults; `.env` optional).
- [ ] `config/database.local.php` exists, is readable by PHP, is **not**
      committed, and returns 403/404 over HTTP.
- [ ] Database user is dedicated to this schema and has a long unique password.
- [ ] `database/install.sql` imported into an empty database; `db:status` shows
      no drift (if CLI is available).
- [ ] `storage/` writable; `src/`, `config/`, `database/`, `vendor/` and `bin/`
      not writable by the web user.
- [ ] HTTPS enabled and the HSTS header uncommented in `.htaccess`.
- [ ] Public registration intentionally on or off
      (`ALLOW_SELF_REGISTRATION`).
- [ ] Seeded `admin` password changed with
      `php bin/console user:password --username=admin --password='…'`;
      `role` is `admin` only for the owner.
- [ ] Probe private URLs (below) and confirm 403/404.

### Probe these URLs after upload

```
/.env
/config/app.php
/config/database.local.php
/database/install.sql
/src/Core/Bootstrap.php
/vendor/autoload.php
/storage/logs/engine.log
/bin/console
/README.md
```

Every one must return 403 or 404, never source, JSON config or a file download.
Also confirm `/assets/css/app.css` is `text/css` and `/api/v1/health` is JSON.

### Ongoing

- Keep PHP and the database patched through the hosting panel.
- Back up the database, `config/database.local.php` and `.env` before upgrades.
- Review `storage/logs/engine.log` for repeated `auth.login.failed` events.
- Rotate the database password if it was ever pasted into a chat or ticket.
- If SSH is available, schedule the purge commands; they are optional.

## 5. Registration and abuse

Self-service registration is enabled by default because the assignment requires
a working sign-up flow. It grants only virtual credits (₱1,000 by default) and
has no email step. Risks and mitigations:

| Risk | Mitigation |
| --- | --- |
| Scripted mass account creation | `api_rate_limit_per_minute` per IP/route; close registration or add an invite code |
| Username squatting | validation and uniqueness only; acceptable for a simulation |
| Fake identities | no identity is claimed; there is no KYC, cash-out or real-value prize |
| Starting-credit farming | credits are not redeemable; adjust `REGISTRATION_STARTING_BALANCE_MINOR` |

For a public deployment with any reward value, add email verification or an
invite code before opening registration. That is a product decision, not a bug
in the current flow.

## 6. Error handling and logs

* `display_errors` is off in the front controller; PHP warnings never reach the
  client.
* Unexpected exceptions become a generic JSON envelope; details stay server-side.
* Logs are JSON lines with a redaction list (`password`, `token`,
  `authorization`, `secret`, `db_password`, …).
* If `storage/logs` is unwritable, logging fails silently to keep the site up.
  This is a deliberate availability trade-off; check permissions after install.

## 7. Backups and recovery

Back up the database (not just files) before every upgrade. The application is
stateless apart from MySQL and `storage/logs`; restoring the database plus
`.env` restores the service. There are no uploaded files to back up. Migrations
are additive; never edit an applied migration, because the runner will report
checksum drift.

## 8. Residual risks (not fixed here)

* No email verification, CAPTCHA or MFA.
* Bearer tokens are valid until expiry; there is no per-device revocation UI
  beyond logout revoking the presented token.
* No automated dependency vulnerability scanning in CI.
* Apache/LiteSpeed `.htaccess` behaviour was not executed in this environment;
  the rules are standard but must be confirmed on the target host.
* A shared host's PHP build and database version may differ from the tested
  PHP 8.3 / MariaDB 10.11 combination.
* The application is HTTP-only by design; TLS termination and HSTS are the
  host's responsibility.
* Virtual credits have no monetary value; do not attach real-money rewards
  without a separate legal, payment and security review.
