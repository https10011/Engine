# Card Framework

> Reference for `CasinoEngine\Frameworks\Card`. Read this when building a card
> game. The worked example is `src/Games/Synthetic/SyntheticBlackjackGame.php`.

The card framework separates what every card game shares (a deck, a hand,
dealing) from what one game needs (how a hand is scored, what actions are legal,
how a result pays). Blackjack is the first consumer, but nothing in the card
primitives is blackjack-specific.

---

## 1. Cards, ranks and suits

```php
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Card\Rank;
use CasinoEngine\Frameworks\Card\Card\Suit;

$card = Card::of(Suit::Spades, Rank::Ace);
$card = Card::fromCode('AS');   // 'A' rank + 'S' suit
$card->code();                  // 'AS'
$card->value();                 // 11 for an ace, 10 for a face, pip value otherwise
$card->isAce();                 // bool
$card->isRed();                 // hearts/diamonds
```

`Suit` is `Spades|Hearts|Diamonds|Clubs`, with `symbol()`, `isRed()` and
`colour()`. `Rank` is `Two`…`Ace` with string values `'2'`…`'10','J','Q','K','A'`.
`Card::value()` is the *hard* pip value; treating an ace as 1 or 11 is a hand
decision, not a card property.

---

## 2. Deck

```php
use CasinoEngine\Frameworks\Card\Deck\Deck;

$deck = new Deck(6);                                  // six standard packs
$deck = Deck::shuffled($rng, 6);                      // shuffled through the RNG
$deck->shuffle($rng);                                 // reshuffle in place
$card = $deck->draw();                                // one card
$cards = $deck->drawMany(2);                          // several
$deck->remaining();                                   // cards left
$deck->penetration();                                 // fraction dealt
$deck->needsReshuffle(0.75);                          // true past the threshold
$deck->push($card);                                   // return a card (from a muck)
```

Shuffling takes an `RngServiceInterface`, never a global RNG, so a test can
reproduce a deal exactly. `needsReshuffle()` implements the cut-card behaviour a
real shoe has: past the penetration threshold, the shuffle must be redone rather
than dealing the shoe to its last card.

---

## 3. Hand

```php
use CasinoEngine\Frameworks\Card\Hand\Hand;

$hand = new Hand([$cardA, $cardB]);
$hand->add($card);
$hand->count();            // number of cards
$hand->aces();             // count of aces
$hand->hardTotal();        // sum with every ace as 1
$hand->blackjackTotal();   // best total not over 21, ace as 11 where possible
$hand->isSoft();           // a total that uses an ace as 11
$hand->removeAt(0);        // take a card (for a split)
```

`hardTotal()` and `blackjackTotal()` differ by exactly the ace handling. Keeping
both explicit is what makes soft-17 rules testable instead of guesswork.

---

## 4. Blackjack

Three classes carry the blackjack rules, deliberately outside the card
primitives.

### `BlackjackRules` — the rule set

A configuration object with defaults: 6 decks, dealer stands on soft 17, dealer
peeks, double after split allowed, resplit aces disallowed, up to 4 hands,
surrender off, insurance on. Payout ratios default to a 3:2 natural, 2:1 on a
win, 3:1 on insurance, all as exact `Ratio` values so an odd-bet 3:2 pays
`2.50` and never `2.499999`.

```php
$rules = new BlackjackRules(decks: 6, dealerHitsSoft17: false, surrenderEnabled: true);
```

`validate()` rejects an incoherent combination (a negative deck count, a
payout of zero) before play begins.

### `BlackjackHandEvaluator` — the score

Static methods over a `Hand`:

| Method | Answers |
| --- | --- |
| `total(Hand)` | The best blackjack total |
| `isBust(Hand)` | Total over 21 |
| `isSoft(Hand)` / `isHard(Hand)` | Ace counted as 11 / as 1 |
| `isNatural(Hand, bool $wasSplit)` | Two-card 21 that is not a split hand |
| `isPair(Hand)` / `isPairOfAces(Hand)` | Split eligibility |
| `dealerShouldHit(Hand, BlackjackRules)` | Dealer action under the rules |

### `BlackjackOutcome` and `BlackjackSettlement` — the result

`BlackjackSettlement::resolve()` compares the player's and dealer's totals and
returns a `BlackjackOutcome` whose `result` is one of `PLAYER_BLACKJACK`,
`PLAYER_WIN`, `DEALER_BLACKJACK`, `DEALER_WIN`, `PUSH`, `PLAYER_BUST`,
`DEALER_BUST`, `SURRENDER`.

`BlackjackSettlement::multiplier($outcome, $rules)` converts that to a payout
multiplier under the engine's "returned including stake" convention:

| Result | Multiplier |
| --- | --- |
| Loss | 0 |
| Push | 1 (the stake returns) |
| Win | 2 (1:1) |
| Natural | 5/2 (3:2) |
| Surrender | 1/2 |

A natural beats a drawn 21, which is why `isNatural` is passed in rather than
inferred from the total.

---

## 5. A turn-based game on the framework

Blackjack is multi-action: `deal`, then one or more of `hit`, `stand`, `double`.
The framework supplies the cards and the scoring; the module supplies the state
machine and the action set. Two patterns from the synthetic module are worth
copying:

* **The stake is captured once.** The `deal` action wagers; `hit`/`stand`
  carry no bet. A later action that settles the round sets the outcome's
  `stakeBasis` to the stake the round opened with, so a multi-action round
  settles against one stake instead of each action inventing one.
* **A bet on a non-wagering action is rejected.** `validateAction` throws if a
  `hit` carries a bet, exactly as the slot module rejects a bet on a free spin.
  This is what stops the engine debiting a hand twice.

---

## 6. Testing

Unit-test the primitives directly: a known hand's totals, a known shoe's draw
order under a fixed seed, each `BlackjackSettlement::resolve` branch, and the
soft-17 boundary. `CardAndBlackjackTest` does this with explicit cards.

Then integration: drive the module through the engine and assert the wallet
movement. For blackjack's bounded mathematics a simulation gives a hit
frequency and an observed RTP:

```bash
php bin/engine-simulate sim:run --game=synthetic.blackjack --rounds=100000 --seed=7 --bet=1.00
php bin/engine-simulate sim:compare --game=synthetic.blackjack --seed-a=1 --seed-b=2 --rounds=50000
```

The comparison view is there because two seeds landing at a similar observed RTP
is evidence the result is not seed-dependent, which a single run cannot show.
