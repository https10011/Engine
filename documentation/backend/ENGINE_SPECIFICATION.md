# Engine Specification

> Normative description of the universal engine core's contracts and runtime
> semantics. Updated as contracts are finalised.
> Last updated: Phase 0 (contracts drafted; implementation follows in Phase 2).

---

## 1. Scope

This document specifies:

* the engine lifecycle (boot → register → session → execute → close),
* every core contract a game module implements or consumes,
* the canonical result envelope,
* state management semantics,
* the event catalogue,
* error taxonomy,
* versioning.

It does **not** describe game rules (see the framework documents) or money
movement (see `ARCHITECTURE.md` §3 and `GAME_SDK.md`).

---

## 2. Engine lifecycle

```
1. Boot            Kernel::boot()
                     ├─ load config (config/*.php + environment overrides)
                     ├─ build logger
                     ├─ build PDO connection (lazy — connects on first query)
                     ├─ build shared services
                     ├─ build GameRegistry
                     └─ run GameDiscovery over configured namespaces
2. Register        registry holds a GameInterface instance per game id
3. Initialise      Engine::initialise(gameId, userId, options) -> GameSession
                     ├─ validate the game exists and is enabled
                     ├─ resolve the module's configuration
                     └─ create a session row + initial game state
4. Execute         Engine::execute(sessionId, action, payload, idempotencyKey)
                     └─ delegates to RoundService (money) + GameRuntime (rules)
5. Close           SessionManager::close(sessionId)
                     └─ mark the session closed; retain history
```

Every step is idempotent or safely repeatable where it needs to be:
initialisation is idempotent per (user, game, client-supplied session key);
execution is idempotent per `Idempotency-Key`.

---

## 3. Core contracts

### 3.1 `GameInterface`

The single thing a game module must implement.

```php
interface GameInterface
{
    public function metadata(): GameMetadataInterface;
    public function category(): GameCategory;           // Slot | Card | Roulette
    public function defaultConfiguration(): GameConfigurationInterface;
    public function validateConfiguration(GameConfigurationInterface $c): void;
    public function createInitialState(GameContext $ctx): GameStateInterface;
    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $ctx
    ): GameOutcomeInterface;
    public function validateAction(GameActionInterface $action, GameStateInterface $state): void;
}
```

Rules:

* `metadata()` must be side-effect free and deterministic.
* `execute()` must be pure with respect to the wallet: it returns what happened;
  it never moves credits.
* `execute()` receives the RNG through `GameContext`; it must not call
  `random_int` directly.
* `execute()` may throw `GameRuleException` for illegal play; the runtime
  translates this into a safe failure without touching money.

### 3.2 `GameMetadataInterface`

| Field | Type | Notes |
| --- | --- | --- |
| `id` | string | lowercase slug, `^[a-z0-9][a-z0-9._-]{1,63}$`, unique |
| `name` | string | display name |
| `category` | `GameCategory` | slot / card / roulette |
| `version` | string | semantic version of the module |
| `provider` | string | author/studio label, e.g. `Synthetic` |
| `minBet` / `maxBet` | `Money` | inclusive limits |
| `betStep` | `Money` | allowed increment |
| `volatility` | enum | low / low-med / med / high-med / high |
| `maxWinMultiplier` | int | published cap, in times the bet |
| `rtp` | ?float | optional published RTP; never assumed |
| `enabled` | bool | registry filter |
| `tags` | string[] | free-form discovery tags |

### 3.3 `GameConfigurationInterface`

Typed, defaulted, validated configuration. Implementations expose
`toArray()`, `fromArray()`, and `validate()`. Unknown keys are rejected.

### 3.4 `GameSessionInterface`

| Field | Notes |
| --- | --- |
| `id` | opaque string (ULID/UUID) |
| `userId` | owner |
| `gameId` | registered game |
| `state` | serialised `GameStateInterface` |
| `createdAt` / `updatedAt` / `expiresAt` | timestamps |
| `status` | active / closed / expired |
| `roundCount` | number of executed rounds |

### 3.5 `GameStateInterface`

Serialisable, versioned snapshot. Must round-trip through JSON without loss.
Carries any board content a feature needs to persist across rounds (see the
Golden Empire 2 finding in `RESEARCH_NOTES.md`).

### 3.6 `GameActionInterface`

| Field | Notes |
| --- | --- |
| `name` | e.g. `spin`, `hit`, `stand`, `place_bet` |
| `payload` | validated action-specific data |
| `bet` | `Money` for money-moving actions, `null` otherwise |

### 3.7 `GameOutcomeInterface`

The module's verdict for one action, before money is applied.

| Field | Notes |
| --- | --- |
| `wagerMultiplier` | int/`Ratio`: 1 for a normal spin, 0 for a free spin |
| `payoutMultiplier` | `Ratio` — total return as a multiple of the wager |
| `detail` | game-specific payload for the frontend and history |
| `nextState` | the resulting `GameStateInterface` |
| `events` | domain events the module wants emitted |
| `featureTransitions` | e.g. `free_spins_started`, `free_spins_ended` |

Money is computed by `PayoutService` from `payoutMultiplier` × `bet`. The
module never computes currency.

### 3.8 `GameContext`

The service bag given to a module. Read-only accessors:

```php
final class GameContext
{
    public function rng(): RngServiceInterface;          // production or seeded
    public function config(): GameConfigurationInterface;
    public function logger(): LoggerInterface;
    public function clock(): ClockInterface;             // injectable for tests
    public function metadata(): GameMetadataInterface;
    public function featureFlag(string $name): bool;
}
```

`GameContext` deliberately does **not** expose the wallet, betting or payout
services. A module cannot move money even by accident.

---

## 4. Canonical result envelope

Every executed action returns exactly this shape, whatever the game type.

```json
{
  "ok": true,
  "data": {
    "sessionId": "01J...",
    "roundId": 12345,
    "roundNumber": 7,
    "gameId": "synthetic-slot",
    "action": "spin",
    "bet": { "amount": 100, "currency": "PHP", "formatted": "₱1.00" },
    "payout": { "amount": 250, "currency": "PHP", "formatted": "₱2.50" },
    "net": { "amount": 150, "currency": "PHP" },
    "payoutMultiplier": 2.5,
    "balanceAfter": { "amount": 9150, "currency": "PHP" },
    "detail": { "...": "game-specific" },
    "state": { "status": "active", "roundCount": 7 },
    "features": [ { "type": "free_spins", "status": "started", "remaining": 10 } ],
    "idempotentReplay": false
  },
  "error": null,
  "meta": { "engineVersion": "1.0.0", "timestamp": "2026-11-07T12:00:00Z" }
}
```

Error form:

```json
{
  "ok": false,
  "data": null,
  "error": { "code": "INSUFFICIENT_FUNDS", "message": "Balance is too low for this bet." },
  "meta": { "engineVersion": "1.0.0", "timestamp": "..." }
}
```

`error.message` is always safe for a client. Internal detail goes to the log.

---

## 5. State management

* State is serialised JSON stored on the session row.
* `StateStore` contract: `load(sessionId)`, `save(sessionId, state)`,
  `delete(sessionId)`.
* Two implementations: `InMemoryStateStore` (tests, simulation) and
  `DatabaseStateStore` (production).
* State writes happen inside the round transaction, so a failed round leaves
  the previous state intact.
* State carries a `schemaVersion`; the runtime rejects a state whose version it
  cannot migrate.

---

## 6. Event catalogue

| Event | Payload | When |
| --- | --- | --- |
| `GameRegistered` | gameId, category, version | during boot/discovery |
| `SessionCreated` | sessionId, userId, gameId | after session commit |
| `SessionClosed` | sessionId, reason | on close/expire |
| `ActionExecuted` | sessionId, roundId, action | after a successful round |
| `WagerPlaced` | roundId, amount, balanceAfter | after debit commit |
| `PayoutPaid` | roundId, amount, balanceAfter | after credit commit |
| `FeatureTriggered` | roundId, feature, detail | when a feature starts |
| `RoundCompleted` | roundId, net, multiplier | after commit |
| `EngineError` | code, context | on any handled failure |

Semantics:

* Events are dispatched **after** the transaction commits.
* A listener throwing is logged and swallowed; it never rolls back money.
* Listener order is registration order; priorities are supported.

---

## 7. Error taxonomy

```
Throwable
└── CasinoEngine\Core\Exception\EngineException        (base, safe message)
    ├── ConfigurationException
    ├── ValidationException        -> 422
    ├── GameNotFoundException      -> 404
    ├── SessionNotFoundException   -> 404
    ├── AuthorizationException     -> 403
    ├── AuthenticationException    -> 401
    ├── InsufficientFundsException -> 422
    ├── InvalidBetException        -> 422
    ├── GameRuleException          -> 422 (illegal action for the current state)
    ├── IdempotencyConflictException -> 409
    ├── ConcurrencyException       -> 409
    ├── PersistenceException       -> 500
    └── EngineRuntimeException     -> 500
```

Each carries a stable `code` string, a safe `publicMessage`, and an optional
`context` array that is logged but never serialised to clients.

---

## 8. Versioning

* `Engine::VERSION` — engine semantic version, surfaced in every response
  `meta` block.
* `GameMetadataInterface::version()` — per-module version.
* `GameStateInterface::schemaVersion()` — state migration boundary.
* Database `migrations` table records applied migration versions.
* API version is in the path (`/api/v1/...`).

Compatibility policy: the engine refuses to load a module whose declared SDK
contract version is newer than the engine's; it logs and skips a module whose
contract version is older than the engine's minimum supported version.

---

## 9. Core purity invariant

`src/Core` must contain **no** game-specific knowledge. Concretely, no file
under `src/Core` may contain the name of any target title, any slot symbol, any
paytable value, or any rule such as "dealer stands on soft 17".

This is enforced by an automated test (`CorePurityTest`) that greps
`src/Core/**` for a denylist of tokens. The denylist is data, kept in the test,
and the test fails the build if any token appears in core source.

---

## 10. Extension points

| Want to… | Do this |
| --- | --- |
| Add a game | Implement `GameInterface`, place it in a discovered namespace |
| Add a win-evaluation model | Implement `WinEvaluatorInterface` in the slot framework |
| Add a multiplier model | Implement `MultiplierModelInterface` |
| Add a bonus round | Implement `BonusFeatureInterface` |
| Add a card rule | Extend `RuleConfiguration` (data, not code) |
| Add a roulette variant | Add a `Wheel` definition (data, not code) |
| Add a service | Define a contract in `Core/Contracts/Service`, implement in `Services` |
| Add an API endpoint | Add a thin controller that calls an existing service method |
| Add a CLI command | Add a command class implementing `CommandInterface` |
