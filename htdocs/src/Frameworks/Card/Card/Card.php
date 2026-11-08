<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Card;

/**
 * A single playing card.
 *
 * Cards are immutable values, which is what lets a deck be copied cheaply and
 * a hand be compared without defensive copying.
 */
final class Card implements \JsonSerializable
{
    public function __construct(
        public readonly Suit $suit,
        public readonly Rank $rank
    ) {
    }

    public static function of(Suit $suit, Rank $rank): self
    {
        return new self($suit, $rank);
    }

    /** Parses a compact code such as `AS` (ace of spades) or `10H`. */
    public static function fromCode(string $code): self
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^(10|[2-9AJQK])([SHDC])$/', $code, $matches) !== 1) {
            throw new \CasinoEngine\Core\Exception\ValidationException("'$code' is not a valid card code.");
        }

        $rank = match ($matches[1]) {
            'A' => Rank::Ace,
            'K' => Rank::King,
            'Q' => Rank::Queen,
            'J' => Rank::Jack,
            '10' => Rank::Ten,
            default => Rank::from($matches[1]),
        };

        $suit = match ($matches[2]) {
            'S' => Suit::Spades,
            'H' => Suit::Hearts,
            'D' => Suit::Diamonds,
            'C' => Suit::Clubs,
        };

        return new self($suit, $rank);
    }

    public function code(): string
    {
        return $this->rank->value . match ($this->suit) {
            Suit::Spades   => 'S',
            Suit::Hearts   => 'H',
            Suit::Diamonds => 'D',
            Suit::Clubs    => 'C',
        };
    }

    public function value(): int
    {
        return $this->rank->value();
    }

    public function isAce(): bool
    {
        return $this->rank->isAce();
    }

    public function isRed(): bool
    {
        return $this->suit->isRed();
    }

    public function equals(self $other): bool
    {
        return $this->suit === $other->suit && $this->rank === $other->rank;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'code'   => $this->code(),
            'rank'   => $this->rank->value,
            'suit'   => $this->suit->value,
            'symbol' => $this->suit->symbol(),
            'colour' => $this->suit->colour(),
        ];
    }
}
