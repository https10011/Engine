<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Card;

/**
 * A card suit.
 *
 * Colour is derived rather than stored, so a game that uses a non-standard
 * colouring (some Asian decks colour diamonds red and clubs black differently)
 * overrides one method instead of duplicating the suit list.
 */
enum Suit: string
{
    case Spades   = 'spades';
    case Hearts   = 'hearts';
    case Diamonds = 'diamonds';
    case Clubs    = 'clubs';

    public function symbol(): string
    {
        return match ($this) {
            self::Spades   => '♠',
            self::Hearts   => '♥',
            self::Diamonds => '♦',
            self::Clubs    => '♣',
        };
    }

    public function isRed(): bool
    {
        return $this === self::Hearts || $this === self::Diamonds;
    }

    public function colour(): string
    {
        return $this->isRed() ? 'red' : 'black';
    }

    /** @return list<Suit> */
    public static function standard(): array
    {
        return [self::Spades, self::Hearts, self::Diamonds, self::Clubs];
    }
}
