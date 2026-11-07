<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Roulette\Bet;

/**
 * The kinds of roulette bet.
 *
 * Grouped by inside/outside because the distinction matters for table limits
 * and for the la partage rule, not merely for presentation.
 */
enum BetType: string
{
    case Straight    = 'straight';
    case Split       = 'split';
    case Street      = 'street';
    case Corner      = 'corner';
    case SixLine     = 'six_line';
    case Trio        = 'trio';
    case Basket      = 'basket';
    case Column      = 'column';
    case Dozen       = 'dozen';
    case Red         = 'red';
    case Black       = 'black';
    case Odd         = 'odd';
    case Even        = 'even';
    case Low         = 'low';
    case High        = 'high';

    /** Payout as an exact ratio of the stake, returned *including* the stake. */
    public function payoutRatio(): \CasinoEngine\Core\Value\Ratio
    {
        // Standard odds: a straight pays 35:1, a split 17:1, and so on. The
        // engine's convention is "returned including stake", so each is +1.
        return match ($this) {
            self::Straight => \CasinoEngine\Core\Value\Ratio::of(36, 1),
            self::Split    => \CasinoEngine\Core\Value\Ratio::of(18, 1),
            self::Street   => \CasinoEngine\Core\Value\Ratio::of(12, 1),
            self::Corner   => \CasinoEngine\Core\Value\Ratio::of(9, 1),
            self::SixLine  => \CasinoEngine\Core\Value\Ratio::of(6, 1),
            self::Trio     => \CasinoEngine\Core\Value\Ratio::of(12, 1),
            self::Basket   => \CasinoEngine\Core\Value\Ratio::of(7, 1),
            self::Column   => \CasinoEngine\Core\Value\Ratio::of(3, 1),
            self::Dozen    => \CasinoEngine\Core\Value\Ratio::of(3, 1),
            self::Red,
            self::Black,
            self::Odd,
            self::Even,
            self::Low,
            self::High     => \CasinoEngine\Core\Value\Ratio::of(2, 1),
        };
    }

    public function isInside(): bool
    {
        return match ($this) {
            self::Column, self::Dozen, self::Red, self::Black,
            self::Odd, self::Even, self::Low, self::High => false,
            default => true,
        };
    }

    /** A bet on red/black/odd/even/low/high is an "even money" bet. */
    public function isEvenMoney(): bool
    {
        return match ($this) {
            self::Red, self::Black, self::Odd, self::Even, self::Low, self::High => true,
            default => false,
        };
    }

    /** Numbers a bet covers. Used for validation and for a coverage report. */
    /**
     * @param list<string> $numbers
     * @return list<string>
     */
    public function covers(array $numbers): array
    {
        return $numbers;
    }
}
