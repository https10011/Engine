# Research Notes — Casino Game Mechanics

> **Purpose.** Record what was actually learned about the target games so that
> the engine's frameworks are designed around real mechanics rather than
> guesses. This document also records what could **not** be verified.
>
> **Evidence discipline.** Every claim below is tagged:
> * **[V]** Verified — stated by an official provider page or a primary source
>   (Wikipedia for standard games). Source linked.
> * **[O]** Observed — reported consistently by a reputable third-party
>   review; treated as a good description of *behaviour*, not of exact maths.
> * **[E]** Estimated / inferred — an assumption drawn from related titles or
>   from partial information.
> * **[U]** Unknown / unverified — no reliable public source found.
>
> **What this document is not.** It is not a claim of mathematical
> replication. Reel strips, symbol weights, exact RTP contributions and RNG
> internals of commercial games are proprietary and were not obtained. All
> engine implementations are *independent* and *behaviourally similar* at
> best. See "Research limitations" at the end.

---

## 1. Method

| Step | Tool | Result |
| --- | --- | --- |
| Official provider catalogues | `jiligames.com`, `pragmaticplay.com`, `pgsoft.com` | JILI game IDs, official feature blurbs and published max-win/volatility/ways for 8 titles |
| Official game pages | `pragmaticplay.com/en/games/<slug>/` | Official descriptions + published RTP for Sugar Rush 1000, Sweet Bonanza 1000, Gates of Olympus 1000, Mahjong Wins, Fortune of Aztec |
| Reference databases | `slotcatalog.com` (via the Internet Archive), `bigwinboard.com` | Grid, RTP, volatility, max win, hit frequency, feature breakdowns |
| Standards | Wikipedia (`Blackjack`, `Roulette`, `Slot machine`) | Authoritative rules, payouts and house edges for the table games |
| Full provider catalogues | JILI `/games` page (581 entries), Pragmatic sitemaps (679 EN slugs), PG Soft sitemap | Confirmed which titles exist at which provider, and which do **not** exist under any provider found |

**No proprietary source, protected endpoint or access-controlled resource was
accessed.** Only publicly reachable pages, the public Internet Archive, and
public standards documentation.

---

## 2. Mechanics the engine must therefore support

This is the most important section: the research is only useful if it changes
the design. The table lists a mechanic, which target titles exhibit it, and
where the engine must accommodate it.

| Mechanic | Exhibited by | Engine home |
| --- | --- | --- |
| Weighted reel strips, variable reel count | all slots | Slot framework: `ReelStrip`, `ReelSet` |
| Variable grid height per reel (expanding reels) | Golden Empire (32400 ways), Treasures of Aztec (6x5, expanding), Wild Bounty Showdown (3-4-5-5-4-3) | Slot framework: `ReelSet` with per-reel heights |
| Fixed paylines, left-to-right | Fortune Gems 2/3, Piñata Wins, most 3x3 games | Slot framework: `PaylineEvaluator` |
| Ways-to-win (adjacency product) | Super Ace (1024), Super Ace Deluxe (1024), Super Ace 2 (1024), Golden Empire (32400), Wild Bounty Showdown (3000) | Slot framework: `WaysEvaluator` |
| Cluster pays (5+/8+ adjacent) | Sugar Rush 1000 (7x7), Sweet Bonanza 1000 (6x5), Gates of Olympus 1000 (6x5, pay-anywhere) | Slot framework: `ClusterEvaluator`, `ScatterPayEvaluator` |
| Cascading / tumbling reels | Super Ace family, Golden Empire, Wild Bounty Showdown, Sugar Rush 1000, Sweet Bonanza 1000, Gates of Olympus 1000, Piñata Wins, Treasures of Aztec, Mahjong Wins | Slot framework: `CascadeEngine` |
| Cumulative cascade multiplier trail (resets per spin) | Super Ace (x1,x2,x3,x5), Super Ace Deluxe (x1..x10 base / x2..x20 FS), Mahjong Wins (x1..x5 / x10 FS), Treasures of Aztec | Slot framework: `CascadeTrailMultiplier` |
| Position-based doubling multipliers that persist through FS | Sugar Rush 1000 (x2 doubling to x1024 per position) | Slot framework: `PositionMultiplier` |
| Random multiplier symbols summed at end of tumble | Gates of Olympus 1000 (2x–1000x), Wild Bounty Showdown (doubling to x1024) | Slot framework: `SymbolMultiplier` |
| Transforming symbols: "framed symbol leaves a wild" | Super Ace (Gold Card→Joker Wild), Super Ace Deluxe (Golden→Joker, Star Card), Piñata Wins (golden frame→wild), Wild Bounty Showdown (golden frame→wild on next cascade), Mahjong Wins / Mahjong Ways (gold-plated tiles→wild), Treasures of Aztec (silver→gold→wild) | Slot framework: `WildRule` + cascade hooks (`leavesWildOnRemoval`) |
| Wild replication / expanding wilds | Super Ace (Big Joker replicates 1–4 wilds) | Slot framework: `WildRule` with replication strategy |
| Scatter-triggered free spins with retrigger | all 16 slots | Slot framework: `FreeSpinsFeature` |
| Multiplier mode switching between base and free spins | Super Ace family (doubled), Mahjong Ways (2x,4x,6x,10x), Treasures of Aztec (persistent), Wild Bounty Showdown (x8 start) | Slot framework: `MultiplierModel` chosen per phase |
| Bonus wheel / pick bonus as a distinct round | Fortune Gems 2/3 (Lucky Wheel), Fortune Garuda 500 (nudge + side reel) | Slot framework: `BonusFeature` contract |
| Side/multiplier reel beside main reels | Fortune Gems 2 (multiplier reel + wheel symbol), Fortune Garuda 500 (side reels up to 500x) | Slot framework: `AuxiliaryReel` concept |
| Bonus buy / ante bet | Gates of Olympus 1000 (100x), Sugar Rush 1000 (100x, 500x super), Wild Bounty Showdown (75x), Fortune Garuda 500 (bonus bet) | Game module configuration; not a core concept |
| Super scatter cash prizes | Mahjong Wins Super Scatter (50x/500x/5,000x/100,000x) | Slot framework: `ScatterPayEvaluator` with prize ladder |
| Rule-configurable card game | Blackjack | Card framework: `RuleConfiguration` |
| Multi-variant wheel game with different pocket sets and edge rules | Roulette (European/American/French) | Roulette framework: `Wheel`, variant config |

**Conclusion driving the architecture.** Slot games are not one mechanic but a
*combinatorial space*: {paylines, ways, clusters, scatter-pays} × {no cascade,
cascade} × {no multiplier, trail, position, symbol, sticky} × {no feature,
free spins, wheel bonus}. The framework is therefore built from small,
composable, independently testable components with pluggable strategies, not
from a single monolithic `SlotGame` class.

---

## 3. Slot titles

### 3.1 Super Ace — JILI — game id 49

* Provider: **JILI Games** **[V]** (official catalogue entry 49, published
  2020-08)
* Published attributes **[V]** (official page):
  * Max win **10,000x**, volatility **LOW-MED**, type Slot
  * Special features: Free Game, Free Spin, Multiplier
  * **Paylines: 1024 ways**
* Independent reference data **[O]** (slotcatalog, Bigwinboard):
  * Grid **5 reels × 4 rows**, 1,024 ways, RTP **97%**, max win quoted as
    **1,500x** by slotcatalog (conflicts with the provider's 10,000x — see
    "Contradictions" below)
  * Cascading ("Gonzo's Quest style") reels
  * Combo Multiplier trail **x1, x2, x3, x5**, reset at end of spin
  * "Gold Card" symbols become Joker Wilds when eliminated
  * **Big Joker** wilds replicate 1–4 wilds onto reels 2–5; **Little Joker**
    wilds only occupy the original gold-card position
  * Free spins: **3 scatters → 10 free games**, retriggerable (+5 each time);
    multiplier trail doubled: **x2, x4, x6, x10**
* Differences from related titles: the original is the template; Deluxe adds a
  Star Card and extends the trail to x10/x20.
* Engine implications: ways evaluation, cascade + trail multiplier, a wild
  rule that both substitutes and *spawns additional wilds* on elimination,
  free-spin multiplier mode switch.
* Unknown: exact reel strips, symbol weights, RTP contribution per feature,
  whether the "progressive jackpot" variant affects base RTP.

### 3.2 Super Ace Deluxe — JILI — game id 403

* Provider: **JILI Games** **[V]** (published 2024-07)
* Official page **[V]**: "4 × 5 reels, 1024 WAYS", "Accumulate multiplier
  rewards with eliminations, up to 20 times", "3 Scatters award 10 free
  games", max win **10,000X**, volatility **MED**
* Reference **[O]**: Combo trail is **x1, x2, x3, x5, x10** in the base game;
  in free spins the trail is **x2, x4, x6, x10, x20**
  * **Star Card** may activate on the 2nd–4th cascade and is guaranteed from
    the 5th; converts symbols on reels 2–4 into Golden Symbols
  * Golden Symbols → Joker Wilds when eliminated; Big Joker replicates 1–4
    wilds
  * RTP undisclosed **[U]**; slotcatalog believes ~97%
* Differences from Super Ace: longer multiplier trail, Star Card feature,
  higher max win.
* Engine implications: multiplier trail must be *configurable per phase*, and
  the "guaranteed from cascade N" rule is a cascade hook parameter, not a
  special case.
* Unknown: exact Star Card trigger probabilities.

### 3.3 Super Ace 2 — JILI — game id 542

* Provider: **JILI Games** **[V]** (published 2025-07)
* Official page **[V]**: "4x5 reels, 1024 ways to win", "The new **Stacked
  Golden Joker** enables consecutive wins", "Free game multipliers double",
  max win **10000x**, volatility **MED**
* Reference **[O]**: a slotcatalog reviewer states Super Ace 2 is a
  reskin with the same volatility but "more dead spins" and that a third-party
  studio (Royal Slot Gaming) also released a "Super Ace 2". **Treat the
  publisher attribution of any third-party "Super Ace 2" as unverified** **[U]**.
* Differences: adds **Stacked Golden Joker** (consecutive elimination from a
  stack) — a new wild behaviour.
* Engine implications: wild behaviour must be pluggable beyond simple
  substitution; "stacked" implies a multi-cell wild unit.
* Unknown: stacked-joker mechanics detail, exact RTP.

### 3.4 Fortune Gems 2 — JILI — game id 223

* Provider: **JILI Games** **[V]** (published 2023-12)
* Official page **[V]**: "3x3 board, 5 lines" style game; features "Full
  Symbol, Multiplier, Lucky Wheel"; **Paylines: 5 Lines**; max win **10000x**;
  volatility **MED**
  * Official description: "maintaining the existing payline wins in
    conjunction with the **multiplier reel on the right side**", scatter
    triggers the **Lucky Wheel**
* Reference **[O]** (slotcatalog): grid **3×3**, **5 paylines**, RTP **97%**,
  a **special reel** with multipliers **1x, 2x, 3x, 5x, 10x, 15x** and wheel
  symbols; a wheel symbol awards one spin on the **Lucky Wheel** with cash
  prizes 1x, 3x, 5x, 8x, 10x, 15x, 20x, 30x, 50x, 100x, 200x (list truncated
  in source); a **bonus bet mode** upgrades the special reel.
  * Note: slotcatalog attributes this game page to "TaDa Gaming" with release
    2023-08-25 — a **contradiction** with JILI's own catalogue. JILI's
    official entry (223, published 2023-12) is treated as authoritative for
    provider identity.
* Differences from Fortune Gems 3: 3×3 with a side multiplier reel and wheel
  bonus, versus a symbol-splitting 3×3 in the sequel.
* Engine implications: **auxiliary reel** concept, wheel bonus round,
  bet-mode configuration.
* Unknown: exact special-reel distribution, wheel prize weighting.

### 3.5 Fortune Gems 3 — JILI — game id 300

* Provider: **JILI Games** **[V]** (published 2024-04)
* Official page **[V]**: "**3x3 board, 5 connections**", "Every time you win
  points, you can get a **multiplier**", "**Symbol split**", "With
  comprehensive rewards"; **Paylines: 5 Lines**; max win **10125x**;
  volatility **HIGH-MED**
* Reference **[O]**: gameplay description in the official page is thin; the
  distinctive mechanic is **symbol splitting** on wins.
* Differences from Fortune Gems 2: different max win, different volatility,
  symbol splitting instead of a multiplier reel.
* Engine implications: a "split a symbol into multiple paying units"
  transformation hook on win evaluation.
* Unknown: split rules (how many, when), RTP **[U]**, exact paytable.

### 3.6 Golden Empire — JILI — game id 103

* Provider: **JILI Games** **[V]** (published 2023-12)
* Official page **[V]**: "**32400 megaways**", features "Free Game, Free
  Spin, Multiplier"; max win **2000x**; volatility **LOW-MED**
  * Official description: reel surface "turns into Wilds for a seamless
    cascade of wins", "eliminating a single symbol **expands the reel**",
    "the larger it is, the more times it can be eliminated, up to **4
    eliminations** without vanishing", free games accumulate an **unlimited**
    multiplier
* Reference: slotcatalog's `Golden-Empire` slug is a **different game**
  (Iconic Gaming, 5×3, 243 ways) — **name collision**, so slotcatalog data is
  not usable for this title **[U]**.
* Differences from Golden Empire 2: 2000x cap vs 25,000x; unlimited FS
  multiplier vs persistent transferred wilds.
* Engine implications: **expanding reels** (grid height changes during a
  spin), **symbol durability** (a symbol can survive up to N eliminations),
  megaways-style ways count recomputed per grid shape.
* Unknown: how 32,400 ways is derived from the reel configuration, exact
  expansion rules, RTP **[U]**.

### 3.7 Golden Empire 2 — JILI — game id 424

* Provider: **JILI Games** **[V]** (published 2025-06)
* Official page **[V]**: "Up to **32400 connections**", "**BONUS symbol**
  converts gold frame into WILD", "The WILD transferred from BONUS in free
  games can be **kept on the board**"; **Paylines: 32400 ways**; max win
  **25000x**; volatility **MED**
* Reference **[O]**: a slotcatalog commenter describes Golden Empire 2 as a
  "nerf version of the original with more deadspin" — anecdotal, low
  confidence.
* Differences from Golden Empire: persistent wilds across free games, higher
  cap.
* Engine implications: **persistent board state across spins within a
  feature** — the state store must carry grid content between rounds, not just
  counters.
* Unknown: persistence duration, RTP **[U]**.

### 3.8 Fortune Garuda 500 — JILI — game id 696

* Provider: **JILI Games** **[V]** (official page published 2026-04)
* Official page **[V]**: "**3x3 reels with 5 win lines**", "Garuda boosts
  **NUDGE** and multipliers, **up to 500x** & NUDGE Times", "Special side
  reels trigger high-multiplier hits, up to 500x"; **Paylines: 1** on the
  attribute line (contradicts the "5 win lines" description — see
  "Contradictions"); max win **25,000x**; volatility **LOW-MED**
* Reference **[O]** (slotcatalog): grid 3×3, 5 betways, RTP **97.1%**, release
  date 2026-02-18, features: Bonus Bet, "Cheats tool".
* **Naming note.** The directive names "Fortune Garuda 1000 Clone". No title
  called *Fortune Garuda 1000* was found at JILI, Pragmatic, PG Soft, or in
  the slotcatalog index. The closest verified title is **Fortune Garuda 500**
  (game 696). Treat "Fortune Garuda 1000" as **[U]** and do not invent
  mechanics for it; if implemented later, it should be built by changing the
  `Fortune Garuda 500` module's configuration (a 1000x variant), which the
  framework supports by design.
* Differences from the Fortune Gems family: nudge mechanic, side reels.
* Engine implications: **nudge mechanic** (a symbol shifts position and can
  re-trigger evaluation), side reel multipliers up to 500x.
* Unknown: nudge rules, side-reel distribution, RTP of a "1000" variant.

### 3.9 Piñata Wins — PG Soft (not JILI)

* Provider: **PG Soft** **[O]** — established via a Bigwinboard review titled
  "Pinata Wins (PG Soft) Slot Review". The title does **not** appear in JILI's
  581-entry catalogue **[V]**, so the common assumption that it is a JILI
  game is **incorrect**.
* Reference **[O]** (Bigwinboard review, April 2024 release):
  * Grid **5×3**, **20 fixed paylines**, wins left-to-right on consecutive
    reels
  * RTP **96.75%** (96.76% in the info panel), max win **5,000x**, volatility
    **Medium**, bets 0.20–100
  * Symbols: skulls, sombreros, maracas, tacos, chillies
  * **Cascading** reels
  * **Golden frames** on the three middle reels; framed symbols become
    **Wilds** when they participate in a win; some framed symbols carry
    multipliers **x2–x100**
  * Collected multipliers apply to the accumulated win of the current cascade
    sequence
  * **3 scatters → 15 free spins**, +2 per extra scatter; during free spins
    collected multipliers feed a **persistent global multiplier meter** that
    survives between spins of the feature
* Differences: unlike Sugar Rush 1000, the multiplier is collected from framed
  symbols rather than marked positions, and persists *across* free spins.
* Engine implications: multiplier source is the **winning symbol itself**
  (not the position), and a feature-scoped persistent multiplier accumulator.
* Unknown: frame appearance frequency, multiplier weighting, reel strips.

### 3.10 Wild Bounty Showdown — PG Soft

* Provider: **PG Soft** **[O]** (slotcatalog)
* Attributes **[O]**: RTP **96.75%**, volatility **High**, max win **5,000x**,
  hit frequency **29.04%**, **Betways 3000**, layout **3-4-5-5-4-3**,
  release 2022-08-30, features: Avalanche, Buy Feature
* Mechanics **[O]**:
  * "Bet Ways Win": the number of winning symbols on each adjacent reel is
    multiplied together, then multiplied by the paytable value
  * **Golden Frame** symbols can appear on **reels 3 and 4**; gold-framed
    symbols involved in a win **turn wild on the next cascade**
  * Cascading wins with a **doubling multiplier per cascade**, up to **x1,024**
  * **3 scatters → 10 free spins**, +2 per extra scatter; win multiplier
    **starts at x8** per free spin and doubles per cascade up to x1,024;
    retriggerable; bonus buy 75x
* Differences: ways product uses a *paytable multiplier of 20x–50x* for
  premium symbols; a variable-height reel layout.
* Engine implications: variable reel heights, a "bet ways" evaluator where
  the paytable is a multiplier band, gold-frame→wild on the *next* cascade
  (a deferred transformation), and a per-phase starting multiplier.
* Unknown: exact paytable bands, reel strip weights.

### 3.11 Mahjong Hit — unverified title

* **No reliable source found.** Searches of JILI's catalogue, Pragmatic Play's
  679 English slugs, PG Soft's catalogue, slotcatalog's index and Bigwinboard
  produced no game titled exactly "Mahjong Hit". Related verified titles are
  **Mahjong Ways** (PG Soft, 5×5, 2000 ways, RTP 96.95%, gold-plated tiles
  leave wilds, progressive win multipliers 1x/2x/3x/5x, 3 scatters → 10 free
  spins, FS multipliers 2x/4x/6x/10x, max win 100,000x) and **Mahjong Wins
  Super Scatter** (Pragmatic, 5×5, up to 2,000 ways, RTP 96.50%, gold tiles
  on reels 2–4 leave wilds, FS multiplier trail to 10x, **Super Scatter**
  awards 50x/500x/5,000x/100,000x).
* Status: **[U]**. Do not invent mechanics. If implemented, it should be a
  configuration variant of a Mahjong-style module (gold-tile→wild + cascade
  multiplier + scatter free spins), which the framework already supports.
* Engine implications: confirms the need for a **gold-tile→wild cascade hook**
  and a **scatter prize ladder**.

### 3.12 Sugar Bang Bang 2 — unverified title

* **No reliable source found** for a game named exactly "Sugar Bang Bang 2".
  Related verified titles: **Sugar Bang 1000** (5G Games) and
  **Sugar Bang Bang**-adjacent titles from other studios were surfaced but
  none matches the name.
* Status: **[U]**. Likely a "sweet/bang" themed cascade or cluster slot. Do
  not invent mechanics; treat as a configuration variant of the sugar-style
  cluster/cascade model if implemented.

### 3.13 Swerte Fiesta — unverified title

* **No reliable source found.** "Swerte" is Tagalog for "luck", so the title is
  plausibly a Philippine-market slot. Searches across providers and databases
  returned nothing verifiable.
* Status: **[U]**. Do not invent mechanics.

### 3.14 Sugar Rush 1000 — Pragmatic Play

* Provider: **Pragmatic Play** **[V]** (official page)
* Official page **[V]**:
  * **7×7 grid**, wins for **five or more adjacent symbols** (cluster pays)
  * RTP **96.00%** (official page); slotcatalog quotes **97.5%** for a
    variant — RTP ranges exist
  * Max win **25,000x**, volatility **High**, release 2024-03-08
  * **Tumble** feature; whenever a winning symbol explodes its spot is
    **marked**; a second explosion on the same spot adds a multiplier
    **starting at x2 and doubling up to x1,024**; the multiplier applies to
    all winning combinations formed on top of it
  * **3+ scatters → at least 10 free spins**; marked spots and multipliers
    **remain in place until the round ends** and can grow
  * Additional scatters during the bonus award extra free spins
* Reference **[O]**: free spins awarded as 10/12/15/20/30 for 3/4/5/6/7
  scatters; bonus buy 100x (regular, 96.52% RTP) and 500x (**Super Bonus**,
  96.55% RTP) with a pre-seeded multiplier grid (x16 centre, x8 ring, x4
  ring, x2 outer ring).
* Engine implications: cluster evaluation on a 7×7 grid, **position-keyed
  persistent multipliers** that survive across free spins, and a configurable
  pre-seeded multiplier layout for a bonus-buy mode.
* Unknown: exact cluster paytable, reel weights, mark persistence rules in
  detail.

### 3.15 Sweet Bonanza 1000 — Pragmatic Play

* Provider: **Pragmatic Play** **[V]** (official page)
* Official page **[V]**:
  * **6×5 grid**, "awards prizes when **eight or more** of the same fruit or
    candy symbol lands on the grid", **tumbles**
  * Max win **25,000x**
  * RTP **96.53%** (official page)
  * **At least four lollipops → bonus game with 10 free spins**
  * **Candy bomb multipliers** award random values **up to 1,000x**
  * Multipliers **remain on screen during tumbles**, are **added together**,
    and applied to the total win
  * **Five further free spins** when three or more lollipops land during the
    bonus
* Engine implications: 8+-symbol cluster rule on a 6×5 grid, bomb multipliers
  that accumulate within a tumble sequence and sum at the end, retrigger rule
  that differs from the base trigger (3+ during FS vs 4+ to enter).
* Unknown: candy bomb frequency and value weighting.

### 3.16 Gates of Olympus 1000 — Pragmatic Play

* Provider: **Pragmatic Play** **[V]** (official page)
* Official page **[V]**:
  * **6×5 grid**; "match at least eight symbols ... **Symbols pay anywhere on
    the screen**"
  * Tumble feature
  * **Multiplier symbols** on all reels, random values **2x to 1,000x**,
    values **combined at the end of a tumble sequence** and applied to the win
  * **4+ scatters → free spins, starting with 15 free games**
  * During the bonus, a multiplier symbol's value is **added to the round's
    total multiplier**, applied to any win whenever a new symbol hits
  * **3+ scatters during the bonus → +5 free games**
  * Bonus buy 100x; RTP **96.50%**; max win **15,000x**; volatility **High**
* Engine implications: pay-anywhere scatter-pay evaluation, a **round-scoped
  accumulating multiplier** (different from per-tumble summation), and
  different retrigger thresholds in base vs bonus.
* Unknown: multiplier symbol frequency and weighting.

### 3.17 Treasures of Aztec — PG Soft

* Provider: **PG Soft** **[O]** (Bigwinboard review). The directive names
  "Treasure of Aztec"; the verified title is **Treasures of Aztec** (PG Soft).
* Reference **[O]**:
  * **6×5 grid with expanding reel heights**, up to **32,400 ways to win**
  * RTP **96.71%**, max win **9,071x**, bets 0.20–20
  * Cascading wins; multiplier **starts at x1** in the base game and increases
    per cascade
  * **Wilds on the Way**: symbols land with **silver frames**; if they win
    they upgrade to **gold frames**; if those win again they become **wilds**
  * **4 scatters → 10 free spins**, extra scatters add more; in free spins the
    multiplier **starts at x2 and increases by x2 per cascade** and **never
    resets** on a dead spin
* Engine implications: multi-stage symbol transformation (silver → gold →
  wild) implemented as a stateful per-cell progression, expanding reel
  heights, and a **persistent** feature multiplier.
* Unknown: expansion rules, exact ways derivation, reel weights.

---

## 4. Contradictions and how they were resolved

| # | Conflict | Resolution |
| --- | --- | --- |
| C1 | Super Ace max win: provider page says **10,000x**, slotcatalog says **1,500x** | Record both. The provider page is authoritative for the *published* figure; the reference figure may reflect an earlier version. **Engine treats max win as configuration.** |
| C2 | Fortune Gems 2 provider: JILI's catalogue lists it; slotcatalog's page for that slug says "TaDa Gaming" | JILI's own catalogue entry (id 223, 2023-12) is treated as authoritative for provider identity. The slotcatalog page may describe a different regional build. |
| C3 | Fortune Garuda 500 paylines: the attribute field says **1**, the description says **5 win lines** | The description ("3x3 reels with 5 win lines") is treated as the gameplay; the attribute field appears mis-populated. Recorded as a conflict. |
| C4 | Sugar Rush 1000 RTP: official page **96.00%**, slotcatalog **97.5%** | RTP ranges exist per market. Engine treats RTP as a configurable parameter and never claims a single value. |
| C5 | "Golden Empire" on slotcatalog is a different game (Iconic Gaming, 243 ways) | Name collision. JILI's official page is authoritative; slotcatalog data was discarded for this title. |
| C6 | Super Ace 2 attribution: JILI's catalogue lists id 542; a reviewer attributes a "Super Ace 2" to Royal Slot Gaming | Both may exist. Only JILI's entry is verified; the third-party claim is recorded as unverified. |

---

## 5. Table games

### 5.1 Blackjack

Source: Wikipedia *Blackjack* **[V]**.

* Card values: number cards at face value; face cards 10; ace 1 or 11. A hand
  containing an ace counted as 11 is **soft**; otherwise **hard** **[V]**.
* Player options on the initial two cards: **hit, stand, double down, split,
  surrender**; after more than two cards only hit/stand **[V]**.
* **Double down**: double the bet, receive exactly one more card **[V]**.
* **Split**: split a pair into two hands, each with an additional card **[V]**.
* **Surrender**: forfeit **half** the bet and end the hand; not allowed after
  splitting; "late" surrender is not permitted against a dealer blackjack
  **[V]**.
* Dealer draws to 17+; **H17** (dealer hits soft 17) vs **S17** (stands).
  Moving from H17 to S17 reduces the house edge by about **0.2 percentage
  points** **[V]**.
* **Blackjack** (ace + ten-value on the first two cards) beats any non-blackjack
  21 and is traditionally paid **3:2**; some tables pay less (e.g. 6:5) **[V]**.
* **Insurance**: offered when the dealer shows an ace, up to half the bet, pays
  **2:1** if the dealer has blackjack; losing in the long run because the
  dealer has blackjack less than one third of the time **[V]**.
* **Push**: equal totals return the bet **[V]**.
* **Split aces**: commonly one card each with no further hits, no double, no
  resplit; resplitting aces is allowed at some tables (+~0.03% player edge),
  hitting split aces is very rare (+~0.13%) **[V]**.
* A ten on a split ace is a **soft 21**, not a blackjack **[V]**.
* House edge with basic strategy is **under 1%** of total action for good
  rulesets; more decks increase the edge **[V]**.

**Engine implications.**
* `Hand` must model soft/hard totals, blackjack-before-split distinction, and
  bust.
* `RuleConfiguration` must expose: deck count, H17/S17, blackjack payout
  ratio, double rules (any two cards, after split), split rules (max hands,
  resplit aces, hit split aces, DAS), surrender (none/late/early), insurance
  and peek/no-peek.
* Settlement multipliers needed: 1:1 win, 3:2 blackjack, 0 push, −0.5
  surrender, 2:1 insurance.
* Simulations must be able to run a documented strategy; basic strategy is
  provided for sanity checks only, not as a claim about any specific table.

**Unknown:** none material; blackjack is a fully specified public game. The
*choice* of a default ruleset is an engine decision, documented in
`FRAMEWORK_CARD.md`.

### 5.2 Roulette

Source: Wikipedia *Roulette* **[V]**.

* **European** wheel: **37 pockets** (0–36), single zero. **American**: **38
  pockets** (0, 00, 1–36), double zero. **French**: single zero, may use **La
  Partage** (half-back) or **En Prison** on even-money bets **[V]**.
* Colours: in 1–10 and 19–28 odd numbers are red and even black; in 11–18 and
  29–36 odd numbers are black and even red **[V]**.
* Payouts (to 1, plus stake back on a win): straight **35:1**, split **17:1**,
  street **11:1**, corner **8:1**, six-line **5:1**, column/dozen **2:1**,
  even-money bets **1:1** **[V]**.
* House edge: single-zero **2.70%**, double-zero **5.26%** for standard bets
  **[V]**.
* **La Partage**: on a zero, even-money bets lose only half **[V]**. **En
  Prison**: the bet is imprisoned; if the next spin would win, the wager is
  returned **[V]**. With En Prison the edge on 1:1 bets falls to
  **1.35%–1.38%** (single zero) and **2.63%** (double zero) **[V]**.
* American-only **five-number bet** (0, 00, 1, 2, 3) pays 6:1 **[V]**.

**Engine implications.**
* `Wheel` must be data-driven: an ordered pocket sequence plus a colour map,
  so variants are configuration rather than code.
* Bet types must be individually defined and validated with their legal number
  sets.
* Payout calculation must support the 35/17/11/8/5/2/1 ladder plus the
  American five-number 6:1, plus La Partage and En Prison.
* Simulations must be able to run a documented betting pattern and report the
  **observed** return, clearly separated from the theoretical edge.

**Unknown:** none material; roulette is fully specified. Table limits and chip
denominations are configuration.

---

## 6. Cross-cutting findings that changed the design

1. **"Slot game" is not a single shape.** The 16 titles span four win
   evaluation models, several multiplier models, and multiple symbol
   transformation rules. → *Pluggable evaluator and multiplier strategies.*
2. **Cascades are near-universal but not universal.** → *Cascade is an
   optional composition, not a base assumption.*
3. **Symbols change type during a spin.** Gold→wild, silver→gold→wild,
   split symbols, stacked jokers, durable symbols. → *Per-cell symbol state
   with transformation hooks, not a static grid.*
4. **Multipliers have four distinct lifetimes**: per-cascade (reset each spin),
   per-tumble (summed at end of sequence), per-feature-persistent (survives
   free spins), and position-keyed (survives and grows). → *Four multiplier
   models, all configurable.*
5. **Grid shape can change mid-spin** (expanding reels, symbol splitting). →
   *`Grid` must be immutable-per-step and replaced, not mutated in place.*
6. **Feature entry and retrigger thresholds differ** (4+ to enter, 3+ to
   retrigger in Gates of Olympus 1000). → *Trigger rules are configuration
   objects, not constants.*
7. **Provider catalogues disagree with third-party databases.** → *Treat
   published figures as configuration, record conflicts, never hardcode an
   RTP.*
8. **Some named titles do not exist under any provider found.** → *Do not
   invent mechanics; document the gap; make the framework capable of building
   them once real data appears.*

---

## 7. Research limitations (explicit)

1. **Proprietary mathematics were not obtained.** Exact reel strips, symbol
   weights, paytable values, RTP contributions per feature, hit frequencies and
   RNG internals for the commercial titles are not public. Nothing in this
   document or the engine claims to reproduce them.
2. **Published RTP is market-dependent.** Several titles have RTP ranges. The
   engine treats RTP as a configurable parameter and simulation output is
   always labelled *observed*, with sample size and seed.
3. **Reference databases contain errors and collisions.** Where a database
   disagreed with a provider page, the provider page was preferred and the
   conflict recorded (section 4).
4. **Three target titles could not be verified at all** (Mahjong Hit, Sugar
   Bang Bang 2, Swerte Fiesta) and one is named inconsistently with any
   verifiable product (Fortune Garuda 1000 vs Fortune Garuda 500). These are
   recorded as **[U]** rather than guessed.
5. **No gameplay video was analysed frame by frame.** Mechanic descriptions
   come from text sources; timing-dependent details (animation length, cascade
   pacing) are therefore out of scope and irrelevant to the backend engine.
6. **Search-engine coverage was constrained.** Several search endpoints were
   unavailable during research (rate limits, bot challenges). Where that
   limited verification, it is stated in the affected section rather than
   filled in with a guess.

---

## 8. Source list

* JILI Games official catalogue and game intro pages —
  `https://jiligames.com/games`, `https://jiligames.com/PlusIntro/<id>`
  (game ids 49, 103, 223, 300, 403, 424, 542, 696)
* Pragmatic Play official game pages —
  `https://www.pragmaticplay.com/en/games/<slug>/`
  (sugar-rush-1000, sweet-bonanza-1000, gates-of-olympus-1000,
  mahjong-wins-super-scatter, fortune-of-aztec)
* PG Soft official site — `https://www.pgsoft.com/` (catalogue confirmed;
  per-game pages are client-rendered and yielded no server-side data)
* SlotCatalog reviews retrieved through the Internet Archive —
  `https://slotcatalog.com/en/slots/<slug>`
  (Super-Ace, Super-Ace-Deluxe, Fortune-Gems-2, Golden-Empire-2,
  Wild-Bounty-Showdown, Sugar-Rush-1000, Gates-of-Olympus-1000, Mahjong-Ways-2,
  fortune-garuda-500)
* Bigwinboard reviews — `https://www.bigwinboard.com/`
  (Pinata Wins / PG Soft, Treasures of Aztec / PG Soft)
* Wikipedia — *Blackjack*, *Roulette*, *Slot machine*

---

## 9. Traceability: research → framework features

| Research finding | Implemented as | Verified by |
| --- | --- | --- |
| 1024-ways / 3000-ways / 32400-ways | `WaysEvaluator` | Phase 5 tests |
| 5-payline 3×3 games | `PaylineEvaluator` | Phase 5 tests |
| 7×7 / 6×5 cluster pays | `ClusterEvaluator` | Phase 5 tests |
| Pay-anywhere 8+ symbols | `ScatterPayEvaluator` | Phase 5 tests |
| Cascade trails x1/x2/x3/x5 and x1..x10 | `CascadeTrailMultiplier` | Phase 5 tests |
| Position multipliers doubling to x1024 | `PositionMultiplier` | Phase 5 tests |
| Random 2x–1000x symbols summed | `SymbolMultiplier` | Phase 5 tests |
| Gold frame → wild on elimination / next cascade | `WildRule` + cascade hooks | Phase 5 tests |
| Silver → gold → wild progression | per-cell symbol state | Phase 5 tests |
| Expanding reels / variable heights | `ReelSet` per-reel heights | Phase 5 tests |
| Side multiplier reel | auxiliary reel | Phase 5 tests |
| Wheel / pick bonus | `BonusFeature` contract | Phase 5 tests |
| Free spins with retrigger and per-phase multipliers | `FreeSpinsFeature` | Phase 5 tests |
| Blackjack rules matrix | `RuleConfiguration` | Phase 6 tests |
| Roulette variants, La Partage / En Prison | `Wheel` + payout calculator | Phase 7 tests |
