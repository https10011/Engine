<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\GameOutcomeDetailInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;

/** Immutable result of one action, before money is applied. */
final class GameOutcome implements GameOutcomeInterface
{
    /**
     * @param list<FeatureTransition> $featureTransitions
     * @param list<array{name:string,payload:array<string,mixed>}> $events
     */
    public function __construct(
        private readonly GameStateInterface $nextState,
        private readonly GameOutcomeDetailInterface $detail,
        private readonly Ratio $payoutMultiplier = new Ratio(0, 1),
        private readonly Ratio $wagerMultiplier = new Ratio(1, 1),
        private readonly array $featureTransitions = [],
        private readonly array $events = []
    ) {
    }

    public static function lose(GameStateInterface $nextState, GameOutcomeDetailInterface $detail): self
    {
        return new self($nextState, $detail, Ratio::zero(), Ratio::one());
    }

    /** @param list<FeatureTransition> $featureTransitions */
    public static function win(
        GameStateInterface $nextState,
        GameOutcomeDetailInterface $detail,
        Ratio $payoutMultiplier,
        array $featureTransitions = []
    ): self {
        return new self($nextState, $detail, $payoutMultiplier, Ratio::one(), $featureTransitions);
    }

    public function wagerMultiplier(): Ratio
    {
        return $this->wagerMultiplier;
    }

    public function payoutMultiplier(): Ratio
    {
        return $this->payoutMultiplier;
    }

    public function detail(): GameOutcomeDetailInterface
    {
        return $this->detail;
    }

    public function nextState(): GameStateInterface
    {
        return $this->nextState;
    }

    public function featureTransitions(): array
    {
        return $this->featureTransitions;
    }

    public function events(): array
    {
        return $this->events;
    }
}
