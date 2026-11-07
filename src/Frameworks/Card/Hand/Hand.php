<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Hand;

use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Frameworks\Card\Card\Card;

/**
 * A hand of cards.
 *
 * Deliberately a plain container with no game rules. Blackjack totals live in
 * {@see \CasinoEngine\Frameworks\Card\Blackjack\BlackjackHandEvaluator}, so a
 * future poker or baccarat module reuses this class and supplies its own
 * evaluator. Placing blackjack's soft-ace logic here would make the framework
 * unusable for any game that does not share it.
 */
final class Hand implements \JsonSerializable
{
    /** @var list<Card> */
    private array $cards = [];

    /** @param list<Card> $cards */
    public function __construct(array $cards = [])
    {
        foreach ($cards as $card) {
            $this->add($card);
        }
    }

    public function add(Card $card): void
    {
        $this->cards[] = $card;
    }

    public function removeAt(int $index): Card
    {
        if (!isset($this->cards[$index])) {
            throw new GameRuleException('No card at that position in the hand.');
        }

        $card = $this->cards[$index];
        array_splice($this->cards, $index, 1);

        return $card;
    }

    /** @return list<Card> */
    public function cards(): array
    {
        return $this->cards;
    }

    public function count(): int
    {
        return count($this->cards);
    }

    public function isEmpty(): bool
    {
        return $this->cards === [];
    }

    public function first(): ?Card
    {
        return $this->cards[0] ?? null;
    }

    /** The face-up card, used by games that show one card of the dealer's hand. */
    public function second(): ?Card
    {
        return $this->cards[1] ?? null;
    }

    public function contains(Card $card): bool
    {
        foreach ($this->cards as $held) {
            if ($held->equals($card)) {
                return true;
            }
        }

        return false;
    }

    public function aces(): int
    {
        $count = 0;
        foreach ($this->cards as $card) {
            if ($card->isAce()) {
                $count++;
            }
        }

        return $count;
    }

    /** Sum of pip values with every ace counted as 11. */
    public function hardTotal(): int
    {
        $total = 0;
        foreach ($this->cards as $card) {
            $total += $card->value();
        }

        return $total;
    }

    /**
     * Best blackjack total: aces count as 11 where that does not bust.
     *
     * Provided here because it is genuinely useful to any hand-based game, but
     * the *rules* that use it (bust thresholds, soft-17 behaviour) stay in the
     * blackjack module.
     */
    public function blackjackTotal(): int
    {
        $total = 0;
        $aces = 0;

        foreach ($this->cards as $card) {
            if ($card->isAce()) {
                $aces++;
                $total += 11;
                continue;
            }
            $total += $card->value();
        }

        while ($total > 21 && $aces > 0) {
            $total -= 10;
            $aces--;
        }

        return $total;
    }

    public function isSoft(): bool
    {
        $total = $this->blackjackTotal();
        $hard = $this->hardTotal();

        return $total !== $hard && $total <= 21;
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return [
            'cards' => array_map(static fn (Card $c): array => $c->jsonSerialize(), $this->cards),
            'total' => $this->blackjackTotal(),
            'soft'  => $this->isSoft(),
        ];
    }
}
