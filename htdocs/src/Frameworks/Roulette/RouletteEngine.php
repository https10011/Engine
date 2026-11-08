<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Roulette;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Roulette\Bet\Bet;
use CasinoEngine\Frameworks\Roulette\Bet\BetFactory;
use CasinoEngine\Frameworks\Roulette\Settlement\RouletteSettlement;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;

/**
 * The reusable roulette spin engine.
 *
 * Holds a wheel variant, a bet factory and a settlement strategy, and knows how
 * to produce a winning pocket and settle a set of bets against it. It contains
 * no rule that is specific to one wheel: the variant supplies the pockets, the
 * factory supplies the bet geometry and the settlement supplies the payouts.
 *
 * The winning pocket is drawn uniformly from the wheel's pocket order using the
 * injected RNG, so the wheel's bias (if any) is a property of the variant's
 * order and never of the code.
 */
final class RouletteEngine
{
    public function __construct(
        private readonly RouletteVariant $variant,
        private readonly BetFactory $betFactory,
        private readonly RouletteSettlement $settlement = new RouletteSettlement()
    ) {
    }

    public function variant(): RouletteVariant
    {
        return $this->variant;
    }

    public function betFactory(): BetFactory
    {
        return $this->betFactory;
    }

    /** Spins the wheel and returns the winning pocket. */
    public function spin(RngServiceInterface $rng): string
    {
        /** @var string $pocket */
        $pocket = $rng->pick($this->variant->pockets());

        return $pocket;
    }

    /**
     * Spins and settles a set of bets in one call.
     *
     * @param list<Bet> $bets
     */
    public function play(array $bets, RngServiceInterface $rng): RouletteSpinResult
    {
        if ($bets === []) {
            throw new GameRuleException('At least one bet is required to play a roulette spin.');
        }

        return $this->settle($bets, $this->spin($rng));
    }

    /**
     * Settles a known outcome. Used when the pocket is decided elsewhere (a
     * replay, or a test asserting an exact result).
     *
     * @param list<Bet> $bets
     */
    public function settle(array $bets, string $winningPocket): RouletteSpinResult
    {
        if ($bets === []) {
            throw new GameRuleException('At least one bet is required to settle a roulette spin.');
        }

        $settlement = $this->settlement->settle($bets, $winningPocket, $this->variant);

        /** @var Money $totalStaked */
        $totalStaked = $settlement['total_staked'];
        /** @var Money $totalPayout */
        $totalPayout = $settlement['payout'];

        // The multiplier is expressed against the total staked so a caller can
        // apply it to the bet the player submitted. A total stake of zero
        // cannot occur because a Bet must have a positive stake.
        $multiplier = $totalStaked->isZero()
            ? Ratio::zero()
            : Ratio::of($totalPayout->minorUnits(), $totalStaked->minorUnits());

        return new RouletteSpinResult(
            $winningPocket,
            $this->variant->colourOf($winningPocket),
            $totalStaked,
            $totalPayout,
            $totalPayout->subtract($totalStaked),
            $multiplier,
            $settlement['wins'],
            (bool) $settlement['la_partage'],
            (bool) $settlement['en_prison']
        );
    }
}
