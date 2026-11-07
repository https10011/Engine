# Database Schema

> MySQL 8.0+, InnoDB, `utf8mb4`. The schema is created by the migrations in
> `database/migrations/`; never edit a live table by hand.

---

## 1. Migrations

| Version | File | Creates |
| --- | --- | --- |
| `2026_01_01_000001` | `create_engine_tables.sql` | every core table below |
| `2026_01_01_000002` | `create_rate_limit_buckets.sql` | `rate_limit_buckets` |

```bash
php bin/console db:migrate            # apply everything pending
php bin/console db:migrate --dry-run  # show what would run
php bin/console db:status             # applied list + drift check
```

The runner (`Persistence\Migration\MigrationRunner`) records each applied
migration and its SHA-256 checksum in the `migrations` table. `db:status`
re-reads the files and reports **drift** if an applied migration has changed,
so an edit after deployment is caught instead of silently ignored. Migrations
are `CREATE TABLE IF NOT EXISTS`, which makes re-running safe; a future
`ALTER`-style migration will be additive for the same reason.

---

## 2. Tables

### Identity

**`users`** — one row per person. The project is private; the CLI creates the
owner account rather than exposing public registration.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED PK | |
| `username` | VARCHAR(64) UNIQUE | |
| `display_name` | VARCHAR(128) | |
| `status` | ENUM(active, suspended, closed) | suspended blocks login |
| `created_at`, `updated_at` | DATETIME | |

**`user_credentials`** — password hash, 1:1 with a user, so a user without one
(the CLI default) simply has no row.

| Column | Type | Notes |
| --- | --- | --- |
| `user_id` | PK, FK → users ON DELETE CASCADE | |
| `password_hash` | VARCHAR(255) | Argon2id or bcrypt |
| `algo` | VARCHAR(32) | matches `security.password_algo` |
| `updated_at` | DATETIME | |

**`api_tokens`** — bearer tokens, stored only as a SHA-256 hash. The plaintext
exists solely in the login response.

| Column | Type | Notes |
| --- | --- | --- |
| `token_hash` | CHAR(64) UNIQUE | SHA-256 of the presented token |
| `expires_at` | DATETIME | |
| `revoked_at` | DATETIME NULL | logout sets this |

### Virtual wallet — **there is no money here, only virtual credits**

**`wallets`** — the single source of truth for a balance.

| Column | Type | Notes |
| --- | --- | --- |
| `user_id` | UNIQUE, FK → users CASCADE | one wallet per user |
| `currency` | CHAR(3) | `PHP` |
| `scale` | TINYINT UNSIGNED | minor units per major, 2 |
| `balance_minor` | BIGINT | integer minor units; never a float |
| `version` | INT UNSIGNED | bumped on every change |
| **CHECK** | `balance_minor >= 0` | the database itself refuses an overdraft |

The `CHECK` constraint is the last line of defence: even a bug that bypassed
every service could not drive a balance negative.

**`wallet_transactions`** — the ledger. Every balance change writes exactly one
row, in the same transaction that updates `wallets`.

| Column | Notes |
| --- | --- |
| `type` | `credit` / `debit` |
| `reason` | `wager`, `payout`, `adjustment`, … |
| `amount_minor` | positive magnitude |
| `balance_after_minor` | the balance this movement produced |
| `reference` | UNIQUE — the anti-double-count key |
| `round_id` | nullable FK → game_rounds ON DELETE SET NULL |

The unique `reference` is what makes a duplicate movement impossible: two
attempts to record the same logical change collide on the index, and the second
fails rather than crediting twice.

### Games, sessions and history

**`games`** — a mirror of the PHP registry, holding operator-controlled
enablement and JSON configuration overrides. The authoritative game list is
still the code registry; this table never invents a game.

| Column | Notes |
| --- | --- |
| `game_id` | VARCHAR(64) UNIQUE, e.g. `synthetic.slot3x3` |
| `category` | ENUM(slot, card, roulette) |
| `enabled` | TINYINT(1) |
| `config` | JSON, nullable |
| index | `(category, enabled)` |

**`game_sessions`** — one row per player session against one game.

| Column | Notes |
| --- | --- |
| `id` | CHAR(26) — a ULID, sortable and opaque |
| `status` | ENUM(active, closed, expired) |
| `state` | JSON — the module's opaque state snapshot |
| `round_count` | INT UNSIGNED |
| `expires_at` | DATETIME |
| index | `(user_id, game_id)`, `(status, expires_at)` |

The session state is JSON because it belongs to the module. The engine stores
and returns it, and a module's state class validates its own schema version on
hydrate, so a stale snapshot is rejected rather than misread.

**`game_rounds`** — one row per executed action. This *is* the game history.

| Column | Notes |
| --- | --- |
| `round_number` | UNIQUE with `session_id` — a gap-free sequence per session |
| `action` | `spin`, `deal`, `hit`, … |
| `bet_minor`, `payout_minor`, `net_minor` | integer minor units |
| `payout_multiplier_num` / `_den` | the ratio kept as integers, never a float |
| `detail` | JSON — the module's result payload |
| `features` | JSON — the feature transitions |
| indices | `(user_id, created_at)`, `(game_id, created_at)` |

The unique `(session_id, round_number)` is deliberate: two concurrent actions
on one session race to claim the same round number, and only one can win,
which stops a double-spin.

**`bets`** and **`payouts`** — separate tables, because one round can carry
more than one wager (a blackjack split) or more than one payout (insurance plus
the main hand). Both carry a `label` (`main`, `split_1`, …) and cascade-delete
with their round.

### Idempotency and audit

**`idempotency_keys`**

| Column | Notes |
| --- | --- |
| `key_hash` | SHA-256 of the client's `Idempotency-Key` |
| `request_fingerprint` | SHA-256 of the request body |
| `status` | ENUM(pending, completed, failed) |
| `response` | JSON — the stored body for a replay |
| UNIQUE | `(user_id, game_id, key_hash)` |

The unique key is the concurrency mechanism. Two concurrent identical requests
both try to insert `pending`; one wins, the other blocks or reads the winner's
row. A retry with the same key but a different fingerprint gets
`IDEMPOTENCY_CONFLICT` instead of a fresh charge. The `pending` state exists so
a request that dies mid-transaction leaves a row that a later retry can
complete or reclaim, rather than silently double-charging.

**`statistics_daily`** — derived aggregates, one row per
`(user_id, game_id, day)`: rounds, wagered, returned, wins, losses, largest
win. Never authored by a client; always recomputed from committed rounds.

**`audit_log`** — security-relevant events (login success/failure, token
issue/revoke, session close). `context` is JSON; `ip_address` is VARCHAR(45) to
hold IPv6. `ON DELETE SET NULL` so the trail survives a user deletion.

**`rate_limit_buckets`** — the shared-hosting rate limiter. One row per
`(bucket, window_start)` with an atomic `hits` counter incremented via
`ON DUPLICATE KEY UPDATE`, so concurrent increments are safe without Redis.
Rows are disposable; `ratelimit:purge` keeps the table small.

---

## 3. Consistency rules

1. **A balance only changes inside a transaction holding a row lock on the
   wallet** (`SELECT … FOR UPDATE`). Read-modify-write without the lock is a
   bug.
2. **Every balance change writes one ledger row** with a unique `reference`.
3. **A round is written before its bets and payouts**, because both reference
   it.
4. **Money is integer minor units everywhere.** No `FLOAT`, no `DECIMAL` used
   as a wallet balance.
5. **Ratios are two integer columns**, never a float, so a payout multiplier
   round-trips exactly.
6. **Referential integrity is enforced by foreign keys**, not by convention.
   Deleting a user cascades their wallet, sessions and rounds; deleting a round
   nulls a ledger row's `round_id` but keeps the ledger entry.

The transaction and locking behaviour is verified by
`tests/Transaction/WalletIntegrityTest.php`; see
[TESTING_STRATEGY.md](TESTING_STRATEGY.md).

---

## 4. Retention

| Data | Purge command | Default window |
| --- | --- | --- |
| Audit log | `audit:purge` | `security.audit_retention_days` (90) |
| Rate-limit buckets | `ratelimit:purge` | `security.rate_limit_retention_days` (7) |
| Expired tokens | `token:purge` | token TTL |
| Expired idempotency keys | `idempotency:purge` | `security.idempotency_ttl_seconds` |

All four are meant to be wired to cron; none is required for correctness. Each
reads its window from configuration, which is overridable by environment
variable (see [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)).
