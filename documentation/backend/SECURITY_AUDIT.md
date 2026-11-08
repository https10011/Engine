# Security Audit

> Phase 12 deliverable. A systematic review of the engine's security and
> transaction-integrity surfaces, the defects it found, and the fixes. Findings
> that were intentionally left open are in [KNOWN_ISSUES.md](../project/KNOWN_ISSUES.md).

Audit date: 2026-11-07. Engine version 1.0.0.

---

## Method

Each surface below was read, not assumed. Where a claim could be tested, a
test exists in the suite (`composer test`); where it could not, the limitation
is stated instead of hidden. Static analysis runs at PHPStan level 6 and
PHP_CodeSniffer PSR-12 clean.

The threat model is deliberately narrow: a single private owner, virtual
credits only, deployed on shared hosting. There is no adversary with real money
to gain, but there is still a wallet to keep consistent and credentials to keep
secret, so the audit treats those as real.

---

## 1. Authentication

| Control | Implementation | Evidence |
| --- | --- | --- |
| Password storage | Argon2id via `PASSWORD_ARGON2ID` (`security.password_algo`) | `AuthService::createUser` |
| Password hashing | `password_hash` / `password_verify`; never a home-grown hash | `AuthService` |
| Login response | Identical message for unknown user and wrong password | `ApiTest::testLoginRejectsAnUnknownUserWithTheSameMessageAsAWrongPassword` |
| Token storage | SHA-256 hash only; the raw token is returned once and never stored | `TokenRepository`, `api_tokens.token_hash` |
| Token lookup | By hash, so a database leak does not yield usable tokens | `AuthMiddleware` |
| Token expiry | `expires_at` enforced on every request | `ApiTest::testAnExpiredTokenIsRejected` |
| Token revocation | Logout sets `revoked_at`; revoked tokens fail immediately | `ApiTest::testLogoutRevokesTheToken` |
| Garbage token | Rejected without a database hit that could leak existence | `ApiTest::testGarbageTokenIsRejected` |

**Finding A-1 (fixed):** tokens had no retention path. Expired rows
accumulated forever on shared hosting, where a housekeeping daemon is not
available. **Fix:** `php bin/console token:purge`, tested in
`RepositoryReportingTest`.

---

## 2. Authorization

Every session-scoped route is guarded by two middleware: `auth` (a valid
token) and `session.owner` (the session belongs to the caller). Ownership is a
server-side lookup, never a client-supplied user id.

| Control | Implementation |
| --- | --- |
| Session ownership | `SessionOwnershipMiddleware` compares `session.user_id` to the authenticated id |
| Wallet scoping | Wallet and history reads filter by the authenticated user id |
| No client-supplied identity | The caller's user id comes from the token, never from the body |

Evidence: `ApiTest::testWalletTransactionsAreScopedToTheCaller` asserts that a
second user's session never appears in the first user's transaction list.

---

## 3. Transaction integrity (the core of this engine)

The invariant: a wager, its result, its payout and the records that describe
them either all commit or none do, and a retried request cannot move money
twice.

| Mechanism | Where | Why it is sufficient |
| --- | --- | --- |
| One transaction per round | `RoundService::execute` wraps the whole round in `TransactionManager` | A failure anywhere rolls the whole round back |
| Row lock on the wallet | `WalletRepository::lockByUserId` uses `SELECT ... FOR UPDATE` | Two concurrent rounds on one wallet serialise |
| Optimistic version check | `wallets.version` incremented on every write; a stale write is rejected | A lost update throws rather than silently overwriting |
| Unique ledger reference | `wallet_transactions.reference` is unique | The same logical movement cannot be recorded twice |
| Idempotency key | `idempotency_keys` unique on (user, game, key hash) | A retried HTTP request returns the stored response instead of re-executing |
| Non-negative balance | `chk_wallets_balance_non_negative` CHECK constraint | The database refuses to go negative even if application logic is wrong |
| No wallet access from modules | `GameInterface::execute` returns ratios, not money | A module cannot bypass central processing by construction |

Evidence: the `tests/Transaction` suite drives a round that fails after the
debit, two concurrent rounds on one wallet, and a duplicate request, asserting
the balance each time.

**Finding T-1 (verified, no change needed):** a payout above the module's
declared maximum win must be rejected before any credit is written.
`RoundService` calls `PayoutService::assertWithinMaxWin` with the game's
`maxWinMultiplier()` on every settling round, so the ceiling is enforced
centrally and a module bug cannot overpay. Covered by
`BettingAndPayoutTest::testPayoutAboveMaxWinIsRejected` and
`WalletIntegrityTest::testPayoutCannotExceedTheGameMaximumWin`. This control
pre-dates the audit; it is recorded here because the audit verified it rather
than assumed it.

**Finding T-2 (fixed):** `IdempotencyRepository::lock` existed but no caller
used it, and its `FOR UPDATE` implied a locking strategy the service does not
rely on — the design uses insert-uniqueness, not row locks. **Fix:** removed,
so the code no longer documents a false invariant.

---

## 4. Randomness

| Requirement | Implementation |
| --- | --- |
| Authoritative randomness is cryptographically secure | `CsprngRandom` uses `random_bytes`/`random_int` |
| A seed is honoured only for tests | `RngFactory` refuses a seed when `APP_ENV=production` |
| A JS `Math.random()` never decides an outcome | Frontend is not authoritative; outcomes come from the server RNG |

Evidence: `RngTest::testFactoryIgnoresSeedInProduction`,
`testSeededGeneratorRefusesProduction`, `testSecureGeneratorIsNotDeterministic`.

The seeded generator is a xoshiro128**-style reproducible stream used only to
make simulations and tests repeatable. It is never reachable in production.

---

## 5. Input validation and SQL injection

| Control | Implementation |
| --- | --- |
| All queries parameterised | PDO prepared statements everywhere; verified by inspection of every repository |
| LIMIT/OFFSET | Every dynamic LIMIT/OFFSET is clamped to an integer in the repository before interpolation, because those cannot be bound portably |
| Request body size | `security.api_max_body_bytes` (default 64 KiB) rejects oversized bodies |
| Body shape | A non-object JSON body is rejected before it reaches a controller |
| Unknown config keys | `ConfigurationException` — a typo fails at boot, not silently |
| Error output | Never leaks SQL, file paths or stack traces | `ApiTest::testErrorEnvelopeNeverLeaksInternals` |

**Finding V-1 (fixed):** `BetRepository::listForUser` accepted an offset and
silently dropped it, so a paginated bet history repeated page one. Not an
injection issue, but a correctness bug found during the SQL review. **Fix:**
offset is now applied and clamped.

**Finding V-2 (fixed):** `AbstractRepository` parameter annotations were too
narrow, which produced ~60 spurious PHPStan errors and, more importantly,
masked the two real type errors underneath. **Fix:** annotations widened to the
values the callers actually pass; PHPStan is now clean at level 6.

---

## 6. Session and request security

| Control | Implementation |
| --- | --- |
| Session expiry | `expires_at` on `game_sessions`, checked on load |
| Session idle timeout | `SESSION_IDLE_TTL_SECONDS` |
| Duplicate request protection | Idempotency keys, described above |
| Rate limiting | `rate_limit_buckets` with an atomic increment; no Redis required |
| CSRF | Not applicable to the current API: bearer tokens are not sent automatically by a browser, unlike cookies. If cookie auth is added later, CSRF protection must be added with it. This is recorded as a condition, not a claim. |
| CORS | Not enabled. The API is same-origin by default; enabling CORS is a deployment decision |

---

## 7. Secrets and logging

| Control | Implementation |
| --- | --- |
| Secrets not in version control | `.env` gitignored; only `.env.example` is tracked |
| Environment-driven config | `Config` maps a fixed allow-list of env vars onto dot paths |
| No secret in an error | Redaction list in `logging.redact` covers password, token, authorization, secret |
| Log destination | `storage/logs`, above the web root |

**Finding L-1 (fixed):** the `audit_log` table existed in the schema but nothing
wrote to it, so security events were invisible. **Fix:** `AuthService` now
records successful and failed logins and token revocations. The context is
deliberately limited to the username — never the password or the raw token.
Tests: `ApiTest::testASuccessfulLoginIsRecordedInTheAuditTrail`,
`testAFailedLoginIsRecordedWithoutStoringThePassword`.

**Finding L-2 (fixed):** audit rows had no retention path. **Fix:**
`php bin/console audit:purge` with a configurable window.

---

## 8. Filesystem exposure

| Control | Implementation |
| --- | --- |
| Source above the web root | Only `public/index.php` is servable in the intended layout |
| Fallback for hosts that serve the project root | Root `.htaccess` rewrites into `public/` and denies everything else |
| Deny-list | `.env`, `.sql`, `.md`, `.log`, `composer.json/.lock`, `phpunit.xml`, `*.dist` are all denied by pattern |
| Directory listing | `Options -Indexes` |
| Security headers | `nosniff`, `Referrer-Policy`, `X-Frame-Options: DENY` |

---

## 9. Hosting-constraint review

The engine assumes nothing beyond PHP 8.3+, MySQL and PDO:

* No Docker, no Node, no WebSockets, no background worker, no Redis, no cloud
  service is required at runtime.
* Rate limiting and idempotency are implemented in the database specifically
  because APCu and Redis cannot be assumed to exist.
* Retention is a set of cron commands, which any shared host provides.

---

## Summary of findings

| Id | Severity | Status | One line |
| --- | --- | --- | --- |
| T-1 | high | verified | Payout above a module's declared max win is rejected centrally |
| L-1 | medium | fixed | Audit table received no writes |
| V-1 | medium | fixed | Bet history pagination dropped the offset |
| V-2 | low | fixed | Repository annotations hid two real type errors |
| A-1 | low | fixed | No token retention |
| L-2 | low | fixed | No audit retention |
| T-2 | low | fixed | Unused lock method implied a false invariant |

T-1 was verified rather than changed: the max-win ceiling already existed and
the audit confirmed it is enforced on the settling path.

No critical findings remain open. Residual limitations — chiefly that no
independent penetration test or third-party security review has been
performed — are recorded in [KNOWN_ISSUES.md](../project/KNOWN_ISSUES.md).
