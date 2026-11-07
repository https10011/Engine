<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Card\Blackjack;

/**
 * The verdict for one blackjack hand.
 *
 * `multiplier` is what the engine returns to the player *including* the stake,
 * so a push is 1.0 (the stake comes back), a loss is 0.0 and a win is 2.0. A
 * natural is 2.5 at 3:2. Keeping the convention as "returned including stake"
 * matches how the engine's payout multiplier works everywhere else, so no
 * conversion is needed at the boundary.
 */
final class BlackjackOutcome
{
    public const PLAYER_BLACKJACK = 'player_blackjack';
    public const PLAYER_WIN       = 'player_win';
    public const DEALER_BLACKJACK = 'dealer_blackjack';
    public const DEALER_WIN       = 'dealer_win';
    public const PUSH             = 'push';
    public const PLAYER_BUST      = 'player_bust';
    public const DEALER_BUST      = 'dealer_bust';
    public const SURRENDER        = 'surrender';

    public function __construct(
        public readonly string $result,
        public readonly int $playerTotal,
        public readonly int $dealerTotal,
        public readonly bool $isNatural = false,
        public readonly bool $isSplitHand = false
    ) {
    }

    public function playerWins(): bool
    {
        return in_array($this->result, [
            self::PLAYER_BLACKJACK,
            self::PLAYER_WIN,
            self::DEALER_BUST,
        ], true);
    }

    public function isPush(): bool
    {
        return $this->result === self::PUSH;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'result'        => $this->result,
            'player_total'  => $this->playerTotal,
            'dealer_total'  => $this->dealerTotal,
            'is_natural'    => $this->isNatural,
            'is_split_hand' => $this->isSplitHand,
        ];
    }
}
