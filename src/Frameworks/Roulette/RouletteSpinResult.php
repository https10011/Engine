<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Roulette;

use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;

/** The result of one roulette spin and its settlement. */
final class RouletteSpinResult
{
    /**
     * @param list<array<string,mixed>> $bets
     */
    public function __construct(
        public readonly string $pocket,
        public readonly string $colour,
        public readonly Money $totalStaked,
        public readonly Money $totalPayout,
        public readonly Money $net,
        public readonly Ratio $payoutMultiplier,
        public readonly array $bets,
        public readonly bool $laPartage = false,
        public readonly bool $enPrison = false
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'winning_number'    => $this->pocket,
            'colour'            => $this->colour,
            'total_staked'      => $this->totalStaked->jsonSerialize(),
            'total_payout'      => $this->totalPayout->jsonSerialize(),
            'net'               => $this->net->jsonSerialize(),
            'payout_multiplier' => $this->payoutMultiplier->jsonSerialize(),
            'bets'              => $this->bets,
            'la_partage'        => $this->laPartage,
            'en_prison'         => $this->enPrison,
        ];
    }
}
