# Blackjack — Game Specification

| Field | Value |
| --- | --- |
| Game id | `table.blackjack` |
| Provider (researched) | Standard public rules (Wikipedia *Blackjack*) |
| Category | Card |
| Grid | N/A — shoe of cards |
| Win model | Hand comparison against the dealer |
| Volatility | Low |
| Max win (published) | 5× (a 3:2 natural plus splits) |
| RTP | ~99.5% with basic strategy under the default ruleset (configuration) |
| Verification | **[V]** Wikipedia *Blackjack* — see `RESEARCH_NOTES.md` §5.1 |

## 1. Researched mechanics

* Card values: number cards at face value, face cards 10, ace 1 or 11. A hand
  with an ace counted as 11 is **soft**, otherwise **hard**.
* Player options on the initial two cards: **hit, stand, double down, split,
  surrender**. After more than two cards, only hit/stand.
* **Double down**: double the bet, receive exactly one more card.
* **Split**: split a pair into two hands, each receiving an additional card.
* **Surrender**: forfeit half the bet and end the hand; not allowed after
  splitting.
* Dealer draws to 17+; **H17** (hits soft 17) vs **S17** (stands).
* **Blackjack** (ace + ten-value on the first two cards) beats any non-blackjack
  21 and pays **3:2**.
* **Insurance**: offered when the dealer shows an ace, up to half the bet, pays
  **2:1** if the dealer has blackjack.
* **Push**: equal totals return the bet.
* Split aces: commonly one card each with no further hits, no double, no resplit.
* A ten on a split ace is a **soft 21**, not a blackjack.

## 2. Symbol set

A standard 52-card deck per pack; the shoe holds a configurable number of
packs. Ranks `2–10, J, Q, K, A`; suits Spades, Hearts, Diamonds, Clubs.

## 3. Paytable

Settlement multipliers (returned *including* the stake):

| Outcome | Multiplier |
| --- | --- |
| Natural blackjack (3:2) | 5/2 |
| Won hand (1:1) | 2 |
| Push | 1 |
| Surrender | 1/2 |
| Loss | 0 |
| Insurance (2:1) | 3 |

## 4. Feature composition

Built on the card framework:

1. `Deck` (multi-pack shoe) shuffled through the injected RNG.
2. `BlackjackHandEvaluator` — soft/hard totals, bust, natural-before-split.
3. `BlackjackRules` — the full configurable rule matrix.
4. `BlackjackSettlement` — outcome → payout multiplier.
5. Insurance is a separate wager settled at 3:1 (2:1 plus stake).

## 5. Configuration

`BlackjackConfiguration` exposes `BlackjackRules`:

* `decks` (default 6)
* `dealer_hits_soft_17` (default `false` — S17)
* `dealer_peeks` (default `true`)
* `double_after_split` (default `true`)
* `resplit_aces` (default `false`)
* `hit_split_aces` (default `false`)
* `max_hands` (default 4)
* `blackjack_payout` (default 3:2)
* `insurance_enabled` (default `true`)
* `surrender_enabled` (default `false`)

Unknown keys are rejected.

## 6. State

`BlackjackState` carries the shoe (serialised as the remaining cards and the
penetration), the dealer hand, the player hands, the active hand index, the
phase, the stake basis and the insurance wager. It is schema-versioned and
round-trips without loss.

## 7. Tests

* Hand evaluation: soft/hard totals, aces, bust, natural detection.
* Dealer draws to 17 and stands or hits soft 17 per configuration.
* Hit, stand, double, split, surrender and insurance transitions are legal only
  where the rules allow.
* Settlement multipliers: 3:2 natural, 1:1 win, push, 1/2 surrender, 3:1
  insurance.
* Split aces receive one card and cannot be hit when configured.
* A ten on a split ace is 21, not blackjack.
* The shoe reshuffles at the configured penetration.
* Configuration rejects an unknown key; state round-trips.

## 8. Acceptance criteria

- [ ] Registered, listed by `games:list`, initialises a session.
- [ ] A full round (deal → decisions → dealer → settle) executes through
  `RoundService` and moves the wallet correctly.
- [ ] Every rule in §1 is configurable and tested.
- [ ] Game history records the round.

## 9. Limitations

* Blackjack is a fully specified public game; the *choice* of default ruleset
  (6 decks, S17, peek, DAS, no surrender) is an engine decision recorded here.
* No card-counting assistance, no strategy hints beyond the documented
  simulation policy.
