<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Card;

/**
 * A card rank.
 *
 * `value()` is the blackjack pip value where an ace is 1 or 11 (handled by the
 * hand evaluator) and every face card is 10. `order()` is the baccarat-style
 * ordering used when comparing ranks outside blackjack.
 */
enum Rank: string
{
    case Two   = '2';
    case Three = '3';
    case Four  = '4';
    case Five  = '5';
    case Six   = '6';
    case Seven = '7';
    case Eight = '8';
    case Nine  = '9';
    case Ten   = '10';
    case Jack  = 'J';
    case Queen = 'Q';
    case King  = 'K';
    case Ace   = 'A';

    public function value(): int
    {
        return match ($this) {
            self::Ace            => 11,
            self::Jack,
            self::Queen,
            self::King,
            self::Ten            => 10,
            default              => (int) $this->value,
        };
    }

    public function isAce(): bool
    {
        return $this === self::Ace;
    }

    public function isFace(): bool
    {
        return $this === self::Jack || $this === self::Queen || $this === self::King;
    }

    public function label(): string
    {
        return $this->value;
    }

    /** @return list<Rank> */
    public static function standard(): array
    {
        return [
            self::Two, self::Three, self::Four, self::Five, self::Six, self::Seven,
            self::Eight, self::Nine, self::Ten, self::Jack, self::Queen, self::King, self::Ace,
        ];
    }
}
