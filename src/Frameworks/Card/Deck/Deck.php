<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Deck;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Card\Rank;
use CasinoEngine\Frameworks\Card\Card\Suit;

/**
 * One or more decks of playing cards.
 *
 * A shoe is simply a deck built with more than one pack, which is the correct
 * model for blackjack: penetration and card counting both depend on the shoe
 * being a single shuffled sequence, not on independent decks drawn separately.
 *
 * Shuffling uses the injected RNG, so a test can seed it and reproduce an exact
 * deal. Cards are drawn from the end of the array, and `remaining()` reports
 * what is left so a game can decide to reshuffle.
 */
final class Deck implements \JsonSerializable
{
    /** @var list<Card> Cards in draw order; the next card is at the end. */
    private array $cards;

    private int $initialSize;

    /**
     * @param int $packs Number of standard 52-card packs
     * @param bool $withJokers Jokers are included only when a game asks
     */
    public function __construct(
        private readonly int $packs = 1,
        private readonly bool $withJokers = false
    ) {
        if ($packs < 1 || $packs > 8) {
            throw new GameRuleException('A deck must contain between 1 and 8 packs.');
        }

        $this->cards = $this->buildCards();
        $this->initialSize = count($this->cards);
    }

    /** Builds and shuffles in one step, which is what a game normally wants. */
    public static function shuffled(RngServiceInterface $rng, int $packs = 1, bool $withJokers = false): self
    {
        $deck = new self($packs, $withJokers);
        $deck->shuffle($rng);

        return $deck;
    }

    /**
     * Fisher–Yates over the injected RNG.
     *
     * The engine never uses `shuffle()` from the standard library, which is
     * seeded from a source an attacker can sometimes influence and is not
     * documented as suitable for this purpose.
     */
    public function shuffle(RngServiceInterface $rng): void
    {
        $this->cards = $rng->shuffle($this->cards);
    }

    public function draw(): Card
    {
        $card = array_pop($this->cards);
        if ($card === null) {
            throw new GameRuleException('The deck is empty.');
        }

        return $card;
    }

    /** @return list<Card> */
    public function drawMany(int $count): array
    {
        if ($count < 0) {
            throw new GameRuleException('Cannot draw a negative number of cards.');
        }
        if ($count > $this->remaining()) {
            throw new GameRuleException('The deck does not hold that many cards.');
        }

        $cards = [];
        for ($i = 0; $i < $count; $i++) {
            $cards[] = $this->draw();
        }

        return $cards;
    }

    public function remaining(): int
    {
        return count($this->cards);
    }

    public function initialSize(): int
    {
        return $this->initialSize;
    }

    /** Fraction of the shoe that has been dealt, in `[0, 1]`. */
    public function penetration(): float
    {
        if ($this->initialSize === 0) {
            return 1.0;
        }

        return 1.0 - ($this->remaining() / $this->initialSize);
    }

    /** True once the dealt fraction reaches `$threshold`. */
    public function needsReshuffle(float $threshold = 0.75): bool
    {
        return $this->penetration() >= $threshold;
    }

    public function isEmpty(): bool
    {
        return $this->cards === [];
    }

    /**
     * Puts a card back on top of the deck. Used by tests and by games that
     * return a card (some baccarat variants).
     */
    public function push(Card $card): void
    {
        $this->cards[] = $card;
    }

    /** @return list<Card> */
    public function cards(): array
    {
        return $this->cards;
    }

    /** Restores the deck to a full, unshuffled state. */
    public function reset(): void
    {
        $this->cards = $this->buildCards();
        $this->initialSize = count($this->cards);
    }

    /** @return list<Card> */
    private function buildCards(): array
    {
        $cards = [];

        for ($pack = 0; $pack < $this->packs; $pack++) {
            foreach (Suit::standard() as $suit) {
                foreach (Rank::standard() as $rank) {
                    $cards[] = new Card($suit, $rank);
                }
            }

            if ($this->withJokers) {
                // A joker has no standard rank or suit; it is modelled as a
                // spade two in the enum but flagged by the game, so games that
                // do not use jokers never see one.
                $cards[] = new Card(Suit::Spades, Rank::Two);
            }
        }

        return $cards;
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return array_map(static fn (Card $card): array => $card->jsonSerialize(), $this->cards);
    }
}
