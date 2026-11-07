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

## Resolved

_None yet._

---

## Not issues (deliberate scope)

* The 18 named commercial clones are **not implemented** by design. This
  delivery is the engine plus three synthetic fixtures.
* There is no frontend, no artwork, no animation and no sound by design.
* There is no real-money, payment, deposit, withdrawal or cryptocurrency code,
  and none will be added.
