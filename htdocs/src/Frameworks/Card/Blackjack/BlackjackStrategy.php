<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Blackjack;

use CasinoEngine\Frameworks\Card\Hand\Hand;

/**
 * Blackjack basic strategy.
 *
 * The optimal play for every hand against every dealer up-card, encoded as the
 * standard strategy tables. It exists because a simulation needs a *fixed,
 * documented* policy: "stand on 17" measures a game badly, and the measured
 * house edge of a table is only meaningful when the player plays correctly.
 *
 * The tables are the ordinary multi-deck basic strategy — the one published for
 * a four-to-eight deck shoe — with the soft totals and the pairs handled
 * separately because the correct play genuinely differs. Each row is a string
 * of ten letters, one per dealer up-card from 2 to ace, using H for hit, S for
 * stand, D for double and P for split. A row of letters is how the strategy is
 * printed on a card, and it reads the same way here.
 *
 * The returned action is one of `hit`, `stand`, `double` or `split`; the caller
 * decides whether a double or split is available under its own table rules and
 * the strategy falls back when it is not.
 */
final class BlackjackStrategy
{
    public const HIT = 'hit';
    public const STAND = 'stand';
    public const DOUBLE = 'double';
    public const SPLIT = 'split';

    /** Up-card order for every row: 2 3 4 5 6 7 8 9 10 A. */
    private const UP_CARDS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'A'];

    /** Hard totals, 8..21, against 2..A. */
    private const HARD = [
        8  => 'HHHHHHHHHH',
        9  => 'HDDDDHHHHH',
        10 => 'DDDDDDDDHH',
        11 => 'DDDDDDDDDD',
        12 => 'HHSSSHHHHH',
        13 => 'SSSSSHHHHH',
        14 => 'SSSSSHHHHH',
        15 => 'SSSSSHHHHH',
        16 => 'SSSSSHHHHH',
        17 => 'SSSSSSSSSS',
        18 => 'SSSSSSSSSS',
        19 => 'SSSSSSSSSS',
        20 => 'SSSSSSSSSS',
        21 => 'SSSSSSSSSS',
    ];

    /** Soft totals, 13..21, against 2..A. */
    private const SOFT = [
        13 => 'HHHDDHHHHH',
        14 => 'HHHDDHHHHH',
        15 => 'HHDDDHHHHH',
        16 => 'HHDDDHHHHH',
        17 => 'HDDDDHHHHH',
        18 => 'DDDDDSSSSS',
        19 => 'SSSSDSSSSS',
        20 => 'SSSSSSSSSS',
        21 => 'SSSSSSSSSS',
    ];

    /** Pairs, by the rank of one of the two cards, against 2..A. */
    private const PAIRS = [
        'A' => 'PPPPPPPPPP',
        '2' => 'PPPPPPHHHH',
        '3' => 'PPPPPPHHHH',
        '4' => 'HHPPPHHHHH',
        '5' => 'DDDDDDDDHH',
        '6' => 'PPPPPHHHHH',
        '7' => 'PPPPPPHHHH',
        '8' => 'PPPPPPPPPP',
        '9' => 'PPPPPSPPSS',
        '10' => 'SSSSSSSSSS',
        'J' => 'SSSSSSSSSS',
        'Q' => 'SSSSSSSSSS',
        'K' => 'SSSSSSSSSS',
    ];

    /** Dealer up-card values that read as 10. */
    private const TEN_VALUES = ['10', 'J', 'Q', 'K'];

    /**
     * The basic-strategy play for a hand.
     *
     * `$canDouble` and `$canSplit` let the caller withhold an action its table
     * forbids; the strategy then falls back to the correct alternative, which is
     * why a disallowed double returns `hit` rather than `double`.
     */
    public static function decide(
        Hand $hand,
        Hand $dealer,
        bool $canDouble = false,
        bool $canSplit = false
    ): string {
        $up = $dealer->first()?->rank->value;
        if ($up === null) {
            return self::STAND;
        }

        $index = array_search(self::upValue($up), [2, 3, 4, 5, 6, 7, 8, 9, 10, 11], true);
        if ($index === false) {
            return self::STAND;
        }

        if ($canSplit && BlackjackHandEvaluator::isPair($hand)) {
            $rank = $hand->cards()[0]->rank->value;
            $letter = self::PAIRS[$rank][$index] ?? 'S';
            if ($letter === 'P') {
                return self::SPLIT;
            }
        }

        $total = BlackjackHandEvaluator::total($hand);
        $letter = BlackjackHandEvaluator::isSoft($hand)
            ? (self::SOFT[$total][$index] ?? 'S')
            : (self::HARD[min(21, max(8, $total))][$index] ?? 'S');

        return match ($letter) {
            'D' => $canDouble ? self::DOUBLE : self::HIT,
            'H' => self::HIT,
            default => self::STAND,
        };
    }

    /** The value a dealer up-card counts as, with an ace as eleven. */
    private static function upValue(string $rank): int
    {
        if ($rank === 'A') {
            return 11;
        }

        return in_array($rank, self::TEN_VALUES, true) ? 10 : (int) $rank;
    }

    /**
     * The rows a strategy chart is printed from, in up-card order.
     *
     * Exposed so a test can assert the tables are complete and consistent —
     * every row ten letters, every letter a known action — rather than trusting
     * a string that is easy to miscount by hand.
     *
     * PHP folds a numeric-string array key to an int, so the pair table's keys
     * read back as `int|string` even though they are written as ranks.
     *
     * @return array{up_cards:list<string>,hard:array<int,string>,soft:array<int,string>,pairs:array<int|string,string>}
     */
    public static function table(): array
    {
        return [
            'up_cards' => self::UP_CARDS,
            'hard'     => self::HARD,
            'soft'     => self::SOFT,
            'pairs'    => self::PAIRS,
        ];
    }
}
