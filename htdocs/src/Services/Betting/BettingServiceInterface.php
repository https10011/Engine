<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Betting;

use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Value\Money;

/** Validates wagers against a game's declared limits. */
interface BettingServiceInterface
{
    /**
     * @throws \CasinoEngine\Core\Exception\InvalidBetException
     */
    public function validate(Money $bet, GameMetadataInterface $metadata): void;

    /** Normalises an arbitrary amount to the game's step. */
    public function normalise(Money $bet, GameMetadataInterface $metadata): Money;

    /** @return list<Money> The allowed bet ladder within the declared limits. */
    public function ladder(GameMetadataInterface $metadata, int $steps = 10): array;
}
