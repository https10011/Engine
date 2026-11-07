# Architecture

> Status: living document. Updated at the end of each phase.
> Last updated: Phase 0.

---

## 1. Goals that shape the architecture

1. **One engine, many games.** Adding game #19 must not require editing core
   code.
2. **Money is authoritative and server-side.** The engine decides outcomes and
   moves credits; nothing else may.
3. **Different games need different execution models.** Slots, blackjack and
   roulette get separate frameworks rather than a forced common denominator.
4. **Ordinary shared hosting must be able to run it.** No Docker, no Node
   server, no background workers, no WebSockets, no cloud services.
5. **Virtual credits only.** There is no payment path to secure because none
   exists; the wallet moves simulated PHP credits.

---

## 2. Layering

The system is a modular monolith with strict, one-directional dependencies.
A layer may depend on the layers below it, never above it.

```
        ┌──────────────────────────────────────────────┐
  L5    │  Entry points                                │
        │  public/index.php (HTTP)   bin/console (CLI) │
        └───────────────────────┬──────────────────────┘
                                │
        ┌───────────────────────▼──────────────────────┐
  L4    │  Adapters: Api\* (controllers, middleware)   │
        │            Cli\* (commands, simulation)      │
        │  Transport only. No rules, no money.         │
        └───────────────────────┬──────────────────────┘
                                │
        ┌───────────────────────▼──────────────────────┐
  L3    │  Game modules: Games\Slots\*, Games\Cards\*, │
        │                Games\Roulette\*, Games\Synthetic\* │
        │  Own rules, config, paytables, evaluation.   │
        │  Consume the SDK. Never touch the wallet.    │
        └───────────┬───────────────────────┬──────────┘
                    │ uses                  │ uses
        ┌───────────▼──────────┐  ┌─────────▼────────────┐
  L2    │  Frameworks          │  │  Shared services      │
        │  Slot / Card /       │  │  RNG, Wallet, Betting,│
        │  Roulette mechanics  │  │  Payout, Session,     │
        │  No money movement.  │  │  Statistics, History, │
        └───────────┬──────────┘  │  Transactions,        │
                    │             │  Idempotency, Round   │
                    │             └─────────┬─────────────┘
                    │                       │
        ┌───────────▼───────────────────────▼──────────┐
  L1    │  Universal engine core                       │
        │  Engine, GameRegistry, GameDiscovery,        │
        │  ModuleLoader, GameRuntime, SessionManager,  │
        │  StateStore, EventDispatcher, Config, Logger,│
        │  ResultNormaliser, exceptions, Money         │
        │  Game-agnostic. Knows no title or symbol.    │
        └───────────────────────┬──────────────────────┘
                                │
        ┌───────────────────────▼──────────────────────┐
  L0    │  Persistence: PDO connection, repositories,  │
        │  migration runner. Prepared statements only. │
        └──────────────────────────────────────────────┘
```

### Why this shape

* **L1 knows nothing about games.** Enforced by a test that greps `src/Core`
  for game-specific tokens. This is the single most important invariant in the
  project.
* **L2 frameworks know mechanics but not money.** A slot evaluator returns
  "these positions won, this multiplier applies"; it never sees a balance.
* **L2 services know money but not mechanics.** `RoundService` does not know
  what a reel is; it asks the module for an outcome.
* **L3 modules are where game rules live.** A module is a small class
  implementing `GameInterface` plus a `SlotGameDefinition` (or card rules, or
  a roulette variant).
* **L4 is thin.** A controller parses, authorises, delegates, serialises.
  Nothing else.

---

## 3. The authoritative round flow

Every money-moving action, whether from HTTP or CLI, goes through exactly one
path. This is the heart of the architecture.

```
Client / CLI
   │  play(action, payload, idempotencyKey)
   ▼
RoundService::play()
   │
   ├─ 1. Resolve game + session; authorise session↔user binding
   ├─ 2. Idempotency lookup (user, game, key)
   │        └─ hit → return the STORED result, do not re-execute
   ├─ 3. TransactionManager::transactional(function () {
   │        a. Lock wallet row               (SELECT ... FOR UPDATE)
   │        b. Validate bet (min/max/step, balance)
   │        c. Debit wager                    → wallet_transactions
   │        d. module->execute(action, state, context)   ← RNG lives here
   │        e. Validate the returned outcome shape
   │        f. Credit payout if any            → wallet_transactions
   │        g. Persist round / bet / payout records
   │        h. Store idempotency key → canonical result
   │        i. Update aggregate statistics
   │      })
   ├─ 4. Emit events (post-commit, so listeners see committed state)
   └─ 5. Return the canonical result envelope
```

Failure behaviour at each point:

| Failure point | Effect |
| --- | --- |
| Before step 3 (auth, validation) | Nothing touched |
| Inside `a`–`c` (wallet lock, bet validation, debit) | Rollback; balance unchanged |
| Between `c` and `d` (module throws) | Rollback; wager returned; no round recorded |
| Inside `f` (payout) | Rollback; wager and payout both undone |
| After `g`, during `h`/`i` | Rollback; nothing recorded |
| After commit, during event dispatch | State is committed; listener failures are logged, never rolled back |
| Client retries the same request | Idempotency hit; no second debit |

Concurrency: two concurrent requests for the same wallet serialise on the
`wallets` row lock. The loser waits, then re-validates balance inside its own
transaction.

---

## 4. Core components

| Component | Responsibility |
| --- | --- |
| `Kernel` / `Application` | Wires config, logger, DB, services, registry; the composition root |
| `GameRegistry` | Registration, lookup, duplicate protection, metadata listing |
| `GameDiscovery` | Finds `GameInterface` implementations in configured namespaces |
| `ModuleLoader` | Instantiates modules via factories with injected services |
| `Engine` | Facade: boot, initialise game, create session, execute action |
| `GameRuntime` | Executes one action against a loaded game state |
| `SessionManager` | Session create/load/touch/expire/close |
| `StateStore` | Persists game state between actions (memory + DB) |
| `EventDispatcher` | Ordered, typed domain events |
| `ResultNormaliser` | Canonical result envelope for every game type |
| `GameContext` | Service bag handed to modules |
| `Config` | Nested, env-overridable configuration |
| `Logger` | PSR-3-compatible, redacting, file/stdout sinks |

Detailed semantics live in [ENGINE_SPECIFICATION.md](ENGINE_SPECIFICATION.md).

---

## 5. Service contracts and invariants

| Service | Invariant |
| --- | --- |
| `RngService` | Production uses `random_int` (CSPRNG). Seeded RNG is only constructible when `app.env != production`. |
| `WalletService` | All balance changes go through `debit`/`credit`, which require an open transaction and write a `wallet_transactions` row. Balances are `Money` (integer minor units). |
| `BettingService` | A bet is validated before it is accepted; rejected bets produce no transaction. |
| `PayoutService` | Payout multipliers come from the module's evaluated outcome, never from client input. |
| `SessionService` | A session is bound to exactly one user and one game. |
| `StatisticsService` | Aggregates are derived from committed records, never from client claims. |
| `HistoryService` | Read-only projections over rounds/bets/payouts. |
| `TransactionManager` | Nesting is guarded; `transactional()` rolls back on any throwable. |
| `IdempotencyStore` | Unique `(user_id, game_id, key)`; stores the canonical result JSON. |

---

## 6. Framework boundaries

### Slot framework
* Input: reel sets, symbol weights, paytable, feature config.
* Output: a grid, winning combinations, cascade sequence, multiplier
  application, feature transitions.
* Never: balances, users, HTTP.

### Card framework
* Input: deck count, shuffle source (RNG), rule configuration.
* Output: hands, legal actions, phase transitions, settlement multipliers.
* Never: balances, users, HTTP.

### Roulette framework
* Input: wheel variant, bet list.
* Output: winning pocket, per-bet win/lose/push, payout multipliers.
* Never: balances, users, HTTP.

Modules translate framework output into the canonical result envelope and ask
`RoundService` to settle it.

---

## 7. Persistence boundaries

* Repositories are the only classes that write SQL.
* Services depend on repository interfaces, not on PDO.
* Migrations are ordered, idempotent files; the runner records applied
  migrations in a `migrations` table.
* Money columns are `BIGINT` minor units, never `FLOAT`/`DOUBLE`.
* Every wallet mutation happens inside a transaction that holds a row lock.

---

## 8. Configuration and secrets

* Configuration is loaded from `config/*.php` and overridden by environment
  variables (`.env` in development, real environment variables in production).
* `.env` is gitignored; `.env.example` documents every key with safe
  placeholders.
* Nothing sensitive is logged; the logger redacts known secret keys.
* `APP_ENV=production` disables the seeded RNG and enables stricter error
  handling.

---

## 9. Deployment shape

```
public/            ← the ONLY directory exposed by the web server
  index.php        ← front controller
  .htaccess        ← rewrite to front controller
src/ config/ database/ tests/ bin/ docs/   ← outside the webroot
storage/logs/ storage/cache/               ← writable at runtime
```

Shared-hosting consequences, documented in
[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md):

* No persistent processes: everything is request-scoped.
* No WebSockets: the frontend polls or posts.
* No Docker: plain PHP files plus a MySQL database.
* Where the host forbids a private source directory, a fallback layout with
  `.htaccess` denials is documented.

---

## 10. Architectural decisions (ADR log)

| # | Decision | Rationale | Consequence |
| --- | --- | --- | --- |
| ADR-1 | Modular monolith, no microservices | Directive requirement; shared hosting target | Single deploy unit; clear internal boundaries instead of network boundaries |
| ADR-2 | Three separate frameworks | Slot/card/roulette share almost no execution model; forcing one would produce a god-object | Some duplication in lifecycle glue, accepted |
| ADR-3 | Integer minor units for money | Floats corrupt balances | All amounts are `Money`; formatting only at the edge |
| ADR-4 | `random_int` CSPRNG behind an interface | Authoritative outcomes must be unpredictable; tests need determinism | Two implementations, one guarded by environment |
| ADR-5 | Idempotency at the round level, not per endpoint | Retries are a client behaviour, not an endpoint behaviour | One place to reason about replay |
| ADR-6 | Events dispatched after commit | Listeners must not see uncommitted state | Listener errors are logged, not fatal |
| ADR-7 | Canonical result envelope for all game types | Frontend and API need one shape | Modules map their detail into a typed `detail` object |
| ADR-8 | Discovery by configuration, not hardcoded lists | Adding a game must not edit core | Registration list is configurable and testable |
| ADR-9 | No ORM | Shared hosting, explicitness, index control | More SQL written by hand, all prepared |
| ADR-10 | Controllers/commands contain no rules | Single source of truth for gameplay | Any new transport must reuse `RoundService` |

---

## 11. Open questions / future work

* Should state stores cache hot sessions in-process? (Deferred; shared hosting
  favours the DB.)
* Multi-currency support exists in `Money` but only PHP credits are used.
* Progressive jackpot contributions are out of scope but the schema leaves a
  `game_configurations` JSON column that could carry them.
