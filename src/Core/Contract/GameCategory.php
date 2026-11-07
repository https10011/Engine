<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

/**
 * Broad classification of a game. The core only uses this to route a session
 * to the right framework and to group games in listings; it carries no rules.
 */
enum GameCategory: string
{
    case Slot     = 'slot';
    case Card     = 'card';
    case Roulette = 'roulette';

    public function label(): string
    {
        return match ($this) {
            self::Slot     => 'Slot',
            self::Card     => 'Card',
            self::Roulette => 'Roulette',
        };
    }
}
