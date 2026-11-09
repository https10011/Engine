# Administrator Console

`public/admin.php` (shipped as `htdocs/admin.php`) is a standalone,
server-rendered console for the private operator. It is not part of the player
SPA and is not a route inside `/api/v1`.

## 1. Access and security

| Control | Implementation |
| --- | --- |
| Credential store | bcrypt hash in `user_credentials`; plaintext is never in the repository |
| Initial account | `admin` / `Tyroar123` seeded by `2026_01_03_000003_seed_initial_admin.sql` |
| Password rotation | `php bin/console user:password --username=admin --password='…'` |
| Authorization | role and status are re-read from `users` on every request |
| Player isolation | the admin login rejects player credentials; players see only the login page |
| Session | separate `casino_admin` cookie, `HttpOnly`, `SameSite=Lax`, secure under HTTPS; id regenerated after login |
| Idle timeout | 30 minutes |
| Brute force | 5 failed attempts per 10-minute session window, plus the engine's IP rate limiter |
| CSRF | every state-changing form posts a token compared with `hash_equals`; a missing token returns HTTP 419 |
| Logout | destroys the session and expires the cookie |
| Output | all database text is escaped with `htmlspecialchars`; CSP, `nosniff`, `X-Frame-Options: DENY` |
| Audit | credit changes and status changes are written to `audit_log` |

The URL `/admin.php` is public knowledge; the security boundary is the
server-side role check, not a hidden link.

## 2. Pages

| Page | URL | What it does |
| --- | --- | --- |
| Dashboard | `admin.php?page=dashboard` | total/active/suspended users, recently active players, credits in circulation, wallets, registered games, friendly games, rounds, wagered/returned/net, recent registrations, recent rounds, per-game activity |
| Players | `admin.php?page=users&q=…` | search by phone (local or canonical), display name, internal username or id |
| Player detail | `admin.php?page=user&user=<id>` | profile, phone, age, role, status, wallet, recent ledger; audited adjustment and suspend/reactivate |
| Games | `admin.php?page=games` | global defaults plus one collapsible form per registered game |
| Audit | `admin.php?page=audit` | most recent 100 operator/security events |

## 3. Game controls

Each game form exposes the controls supported by the existing probability
architecture:

* **Any-win target** — probability of any positive payout;
* **Net-positive target** — probability the round returns more than its stake;
* **RTP target** — expected return ratio;
* **Jackpot probability** and bonus/free-spin trigger probabilities;
* **Payout scale** — multiplier applied to settled payouts;
* **Available in the lobby** — removes the game from catalogue/search and
  refuses new sessions while disabled;
* **Friendly play** — applies a more generous preset to any blank field
  (any-win 0.45, net-positive 0.38, RTP 0.98, bonus 0.18, free spin 0.16,
  payout scale 1.15) and marks the game clearly in the console.

Saved values pass `ProbabilityProfile` validation and are written through
`ProbabilityConfigService` to `probability_profiles` (with version/audit
history). Availability and the friendly marker live in `game_controls`. The
game modules read the effective profile at round time, so the next round uses
the saved setting; no frontend display is being changed in isolation.

The global form applies to every game without a per-game override. "Reset to
global defaults" deletes that override and re-enables the game.

## 4. Operational notes

* Credit adjustments use the wallet row lock and write a ledger entry with a
  unique `admin:<user>:<random>` reference. A negative adjustment that would
  overdraw is rejected.
* Suspending a player blocks login immediately. An administrator cannot suspend
  their own signed-in account through the UI.
* The dashboard's "recently active" count is the number of distinct users with
  an active game session updated in the last 15 minutes. It is a presence
  approximation, not a websocket presence list.
* All timestamps are UTC in the database and rendered by the host's PHP
  timezone configuration.
