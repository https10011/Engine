<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Betting;

use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Exception\InvalidBetException;
use CasinoEngine\Core\Value\Money;

/**
 * Bet validation.
 *
 * Every limit comes from the game's own metadata, so the engine never carries
 * a hardcoded minimum or maximum. A rejected bet produces no transaction at
 * all: validation happens before any money moves.
 */
final class BettingService implements BettingServiceInterface
{
    public function validate(Money $bet, GameMetadataInterface $metadata): void
    {
        if ($bet->isNegative() || $bet->isZero()) {
            throw new InvalidBetException('A bet must be greater than zero.');
        }

        if ($bet->currency() !== $metadata->minBet()->currency()) {
            throw new InvalidBetException('Bet currency does not match the game currency.');
        }

        if ($bet->isLessThan($metadata->minBet())) {
            throw new InvalidBetException(sprintf(
                'The minimum bet for this game is %s.',
                $metadata->minBet()->format()
            ));
        }

        if ($bet->isGreaterThan($metadata->maxBet())) {
            throw new InvalidBetException(sprintf(
                'The maximum bet for this game is %s.',
                $metadata->maxBet()->format()
            ));
        }

        $step = $metadata->betStep()->minorUnits();
        if ($step > 0 && $bet->minorUnits() % $step !== 0) {
            throw new InvalidBetException(sprintf(
                'Bets must be a multiple of %s.',
                $metadata->betStep()->format()
            ));
        }
    }

    public function normalise(Money $bet, GameMetadataInterface $metadata): Money
    {
        $step = $metadata->betStep()->minorUnits();
        if ($step <= 0) {
            return $bet;
        }

        $steps = (int) round($bet->minorUnits() / $step);
        $normalised = Money::of(
            $steps * $step,
            $bet->currency(),
            $bet->scale()
        );

        if ($normalised->isLessThan($metadata->minBet())) {
            return $metadata->minBet();
        }
        if ($normalised->isGreaterThan($metadata->maxBet())) {
            return $metadata->maxBet();
        }

        return $normalised;
    }

    public function ladder(GameMetadataInterface $metadata, int $steps = 10): array
    {
        $steps = max(1, min($steps, 100));
        $minimum = $metadata->minBet()->minorUnits();
        $maximum = $metadata->maxBet()->minorUnits();
        $ladder = [];

        for ($i = 0; $i < $steps; $i++) {
            $ratio = $steps === 1 ? 0.0 : $i / ($steps - 1);
            $amount = (int) round($minimum + ($maximum - $minimum) * $ratio);
            $ladder[$amount] = $this->normalise(
                Money::of($amount, $metadata->minBet()->currency(), $metadata->minBet()->scale()),
                $metadata
            );
        }

        return array_values($ladder);
    }
}
