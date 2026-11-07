# API Specification

> The HTTP/JSON integration boundary. The frontend is deferred, but this is the
> contract a future frontend will speak, and it is implemented and tested today.

The API is a thin adapter. Every route ends in the same service the CLI and the
simulation harness call; no route contains game logic, computes a payout, or
touches a wallet directly. A request that would be refused from the console is
refused here in exactly the same way.

Base path: `/api/v1`. All bodies are JSON. All timestamps are UTC ISO-8601.

---

## 1. Response envelope

Every response — success or error — has the same four-key shape:

```json
{ "ok": true,  "data": { }, "error": null, "meta": { } }
{ "ok": false, "data": null, "error": { "code": "...", "message": "...", "details": { } }, "meta": { } }
```

A client may branch on `ok` alone; the HTTP status still carries the correct
value for proxies and logs.

| Status | Used for |
| --- | --- |
| 200 | Successful read or a settled action |
| 201 | A resource created (session) |
| 204 | Logout (no body) |
| 401 | Missing or invalid token |
| 403 | Authenticated but not the owner of the resource |
| 404 | Unknown game, session or round |
| 405 | Wrong method for a known path |
| 409 | Idempotency conflict, concurrency conflict |
| 422 | Validation, rule, bet, funds failure |
| 429 | Rate limit exceeded |
| 500 | Unexpected server error (never leaks internals) |

### Error codes

| `error.code` | Status | Meaning |
| --- | --- | --- |
| `UNAUTHENTICATED` | 401 | No token, or the token is invalid, expired or revoked |
| `FORBIDDEN` | 403 | The session or round belongs to another user |
| `NOT_FOUND` | 404 | No such route |
| `METHOD_NOT_ALLOWED` | 405 | Route exists, method does not |
| `GAME_NOT_FOUND` | 404 | No registered game with that id |
| `SESSION_NOT_FOUND` | 404 | No session with that id for this user |
| `IDEMPOTENCY_CONFLICT` | 409 | The key was reused with a different payload |
| `CONCURRENCY_CONFLICT` | 409 | A concurrent write won; retry |
| `VALIDATION_ERROR` | 422 | A field is missing or malformed |
| `GAME_RULE_VIOLATION` | 422 | The action is illegal in the current state |
| `INVALID_BET` | 422 | The bet is outside the game's limits |
| `INSUFFICIENT_FUNDS` | 422 | The virtual balance is too low |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `ENGINE_ERROR` / `PERSISTENCE_ERROR` | 500 | Unexpected; the detail is logged, not returned |

---

## 2. Authentication

Bearer token. Obtain one with `POST /auth/login`; send it as
`Authorization: Bearer <token>`. Tokens are stored hashed, expire, and can be
revoked by logout.

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "username": "owner", "password": "…" }
```

```json
{
  "ok": true,
  "data": {
    "user": { "id": 1, "username": "owner", "display_name": "Owner" },
    "token": "…",
    "expires_at": "2026-11-08T10:00:00+00:00",
    "balance": { "amount": 100000, "currency": "PHP", "scale": 2, "formatted": "₱1000.00" }
  },
  "error": null,
  "meta": {}
}
```

A wrong password and an unknown username return the **same** 401 message, so the
endpoint cannot be used to enumerate accounts. A successful and a failed login
are both written to the audit trail (without the password).

| Route | Auth | Returns |
| --- | --- | --- |
| `POST /auth/login` | no | user, token, expiry, balance |
| `GET /auth/me` | yes | user, balance |
| `POST /auth/logout` | yes | 204; revokes the presented token |

---

## 3. Games

| Route | Auth | Returns |
| --- | --- | --- |
| `GET /games?category=slot` | no | every registered game's metadata |
| `GET /games/{gameId}` | no | one game's metadata, category and default configuration |

`category` is `slot`, `card` or `roulette`. Listing returns a `meta.count`.

The game list is the registry — the same source the CLI's `games:list` reads.
Adding a module places it here with no route change.

---

## 4. Sessions

| Route | Auth | Returns |
| --- | --- | --- |
| `POST /sessions` | yes | 201 and the new session |
| `GET /sessions` | yes | this user's sessions |
| `GET /sessions/{sessionId}` | yes + owner | one session |
| `POST /sessions/{sessionId}/close` | yes + owner | the closed session id and status |

```http
POST /api/v1/sessions
Authorization: Bearer <token>
Content-Type: application/json

{ "game_id": "synthetic.slot3x3" }
```

Ownership is checked server-side by middleware: a session that belongs to
another user returns `FORBIDDEN`, never another user's data.

---

## 5. Playing

The one route that settles money.

```http
POST /api/v1/sessions/{sessionId}/actions
Authorization: Bearer <token>
Idempotency-Key: 8f2c…            # optional but recommended on any retry
Content-Type: application/json

{
  "action": "spin",
  "bet": 100,                      # integer minor units (PHP 1.00 = 100)
  "payload": { }                   # optional, module-specific
}
```

`action` names and the meaning of `payload` are defined by the module, not by the
API. `bet` is omitted for an action that must not wager (a blackjack hit or a
free spin); supplying one where the module forbids it is a `GAME_RULE_VIOLATION`.

`bet` is an **integer** in minor units. A decimal, a negative number, or a
string that is not all digits is a `VALIDATION_ERROR` — the API never accepts a
client-supplied decimal amount or balance.

```json
{
  "ok": true,
  "data": {
    "round": {
      "round_id": 42,
      "session_id": "01JC…",
      "game_id": "synthetic.slot3x3",
      "round_number": 7,
      "action": "spin",
      "bet":    { "amount": 100,   "currency": "PHP", "scale": 2, "formatted": "₱1.00" },
      "payout": { "amount": 250,   "currency": "PHP", "scale": 2, "formatted": "₱2.50" },
      "net":    { "amount": 150,   "currency": "PHP", "scale": 2, "formatted": "₱1.50" },
      "payout_multiplier": { "numerator": 5, "denominator": 2, "value": 2.5 },
      "balance_after": { "amount": 100150, "currency": "PHP", "scale": 2, "formatted": "₱1001.50" },
      "state": { },
      "result": { "type": "spin", "data": { "grid": [ ], "evaluations": [ ] } },
      "features": [ ],
      "replayed": false
    }
  },
  "error": null,
  "meta": {}
}
```

### Idempotency

Send `Idempotency-Key: <string ≤ 191 chars>` on an action. The first request
settles; a retry with the same key and the same payload returns the **stored**
result with `replayed: true` and moves no money. A retry with the same key and a
**different** payload is refused with `IDEMPOTENCY_CONFLICT`. This is the
mechanism that makes a client retry after a timeout safe.

A `GET /rounds/{roundId}` returns one round's stored detail, scoped to the
caller.

---

## 6. Wallet, history and statistics

| Route | Auth | Returns |
| --- | --- | --- |
| `GET /wallet` | yes | the caller's balance |
| `GET /wallet/transactions?limit=&offset=` | yes | the caller's ledger entries |
| `GET /history/rounds?game_id=&limit=&offset=` | yes | the caller's rounds and a total |
| `GET /statistics` | yes | the caller's aggregate statistics |

Every one of these is scoped to the authenticated user by the token's user id.
There is no route that reads another user's wallet.

---

## 7. Health

```http
GET /api/v1/health   →   { "ok": true, "data": { "status": "ok", "engine": "universal-casino-engine" } }
```

Unauthenticated, and useful as an uptime probe.

---

## 8. Cross-cutting behaviour

* **Rate limiting** — a per-minute bucket keyed by the caller. Exceeding it is a
  429 with `RATE_LIMIT_EXCEEDED`. Implemented in the database, so it works on
  shared hosting with no Redis.
* **Body size** — a request body larger than `security.api_max_body_bytes`
  (default 64 KiB) is rejected before a controller runs.
* **Unknown fields** — a JSON body that is not an object is rejected. Unknown
  *fields* are ignored by the API layer but rejected by a game's configuration
  validator when they are configuration.
* **Error hygiene** — a 500 never returns a stack trace, a SQL fragment or a
  file path; the detail goes to the log and the client gets a generic message.
* **CSRF** — not applicable to bearer-token auth; recorded as a condition to
  revisit if cookie auth is ever added (see [KNOWN_ISSUES.md](KNOWN_ISSUES.md)).

---

## 9. Using it from the CLI

The API is covered by `tests/Api/ApiTest.php`, which drives the kernel directly
with real requests. To exercise it over HTTP in development:

```bash
php bin/console serve            # PHP built-in server bound to public/
curl -s -X POST http://localhost:8000/api/v1/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"username":"owner","password":"…"}'
```

Deployment and the production document-root arrangement are in
[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md).
