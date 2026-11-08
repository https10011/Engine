# Known Issues

> Honest list of limitations, unresolved problems and unverified claims.
> Nothing here is hidden from the final report.

---

## Open

### KI-1 — Three target titles could not be verified
**Severity:** low (scope, not correctness)
**Affected:** `Mahjong Hit`, `Sugar Bang Bang 2`, `Swerte Fiesta`
**Detail:** No provider catalogue, review database or standards source
describes a game with these exact names. They are recorded as unverified in
`RESEARCH_NOTES.md` §3.11–3.13 rather than guessed.
**Impact:** If these games are implemented later, their mechanics must be
researched from real sources first.
**Mitigation:** The slot framework is built from pluggable strategies
specifically so a new mechanic can be added without core changes.

### KI-2 — "Fortune Garuda 1000" does not match a verifiable product
**Severity:** low (scope)
**Detail:** The closest verified JILI title is **Fortune Garuda 500**
(game id 696, 3×3, 5 lines, nudge, side reels up to 500x, max win 25,000x,
RTP 97.1%). A "1000" variant was not found.
**Mitigation:** A 1000x variant would be a configuration change to a Fortune
Garuda module, which the framework supports by design.

### KI-3 — Published RTP figures are market-dependent
**Severity:** low (documentation)
**Detail:** Several titles publish RTP ranges. Simulation output in this
project is always **observed** for a stated sample size and seed, and is never
presented as a provider's RTP. See `TESTING_STRATEGY.md` and the simulation
output format.

### KI-4 — Exact proprietary mathematics were not obtained
**Severity:** informative
**Detail:** Reel strips, symbol weights, paytable values and RNG internals of
the commercial titles are proprietary. Every game mechanic in this engine is an
**independent implementation** that is behaviourally similar at best.
**Impact:** The engine cannot and does not claim mathematical equivalence with
any commercial title.

### KI-5 — Provider/database contradictions
**Severity:** low
**Detail:** Six conflicts between official provider pages and third-party
databases were found (Super Ace max win, Fortune Gems 2 provider, Fortune
Garuda 500 paylines, Sugar Rush 1000 RTP, Golden Empire name collision,
Super Ace 2 attribution). Each is recorded with a resolution rule in
`RESEARCH_NOTES.md` §4. Where the conflict is unresolved, both figures are
recorded.

---

### KI-6 — No independent security review
**Severity:** medium (assurance, not a known defect)
**Detail:** The security audit in `SECURITY_AUDIT.md` was performed by the
engine's own author. It is thorough and each control is backed by a test, but
it has not been checked by an independent party and no penetration test has
been run.
**Impact:** A defect an outside reviewer would notice could remain.
**Mitigation:** Every finding is documented, the test suite covers the wallet
and idempotency invariants under concurrency, and the threat model is narrow
(single owner, virtual credits). An external review is recommended before any
public deployment.

### KI-7 — CSRF is not implemented because it is not yet applicable
**Severity:** low (conditional)
**Detail:** The API authenticates with a bearer token that a browser does not
attach automatically, so a cross-site request cannot borrow a victim's
credentials. If cookie-based sessions are ever added, CSRF protection must be
added at the same time.
**Impact:** None today. It becomes a real gap only if the auth model changes.

---

### KI-8 — The frontend does not exist
**Severity:** high (scope — a directive deliverable)
**Affected:** Phase 24
**Detail:** `public/` contains only `index.php` (the API front controller) and
`.htaccess`. There is no lobby, no game catalog, no slot interface, no blackjack
interface, no roulette interface, no CSS and no JavaScript. Every game is
reachable only by calling the JSON API directly.
**Impact:** The directive's frontend requirements and their acceptance criteria
are unmet. This is the largest outstanding item.
**Mitigation:** Next phase. The API is complete and stable, so the frontend has
a finished contract to build against.

### KI-9 — The administrator panel is backend-only and unreachable
**Severity:** high (scope — a directive deliverable)
**Affected:** Phase 25
**Detail:** `ProbabilityProfile`, `ProbabilityConfigService`,
`WinFrequencyGovernor` and `ProbabilityRepository` exist, and the migrations
`2026_01_02_000001_create_probability_tables.sql` and
`2026_01_02_000002_add_admin_role.sql` create the storage. What does **not**
exist: administrator authentication, an admin-authorisation middleware, admin
routes, admin controllers, a simulation-verification endpoint and the entire
admin frontend. `users.role` is written by the migration and read by **no code**,
so there is currently no way for any request to be recognised as an
administrator's.
**Impact:** The directive's mandatory administrator functionality and its
additional acceptance criteria are unmet. The probability service cannot be
reached by an administrator, so the engine currently always uses its default
behaviour.
**Mitigation:** Next phase after the frontend. The service layer and its storage
are done and unit-tested; what remains is authorisation, transport and UI.

### KI-10 — Game mechanics are independent implementations, not replicas
**Severity:** informative (documentation honesty)
**Affected:** all 16 slots
**Detail:** Exact proprietary reel strips, symbol weights, paytables, bonus
mathematics and artwork were not obtained. Each slot is an independent
implementation informed by the public research recorded in `RESEARCH_NOTES.md`,
and each specification states which parts are verified, observed, estimated or
unverified.
**Impact:** The engine cannot and does not claim mathematical equivalence with
any commercial title, and it does not claim to be an exact recreation.
**Mitigation:** None needed for correctness. The claim is simply never made.

---

## Resolved

### KI-11 — Payout multipliers overflowed `INT UNSIGNED` (resolved)
**Found:** Phase 23 integration audit.
**Detail:** The slot framework stores payout multipliers as exact rationals. On
ways and cluster games the denominators legitimately exceed 4,294,967,295, so
the round's `INSERT` failed with `SQLSTATE[22003]` and the entire round rolled
back — a big win surfaced to the player as a 500 rather than a payout.
**Fix:** `database/migrations/2026_01_02_000003_widen_multiplier_columns.sql`
widens `game_rounds.payout_multiplier_num`, `game_rounds.payout_multiplier_den`,
`payouts.multiplier_num` and `payouts.multiplier_den` to `BIGINT UNSIGNED`. No
engine or game logic changed.
**Regression test:**
`tests/Integration/AllGamesIntegrationTest::testEveryGameOpensASessionAndRunsARoundThatMovesTheWallet`
now plays a real round of each of the eighteen games through `RoundService`.

---

## Not issues (deliberate scope)

* The 18 named commercial clones are **not implemented** by design. This
  delivery is the engine plus three synthetic fixtures.
* There is no frontend, no artwork, no animation and no sound by design.
* There is no real-money, payment, deposit, withdrawal or cryptocurrency code,
  and none will be added.
