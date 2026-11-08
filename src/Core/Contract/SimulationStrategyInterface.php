<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;

/**
 * Lets a game drive its own rounds during simulation.
 *
 * The simulation driver has generic rules that cover a slot spin and a simple
 * blackjack policy, but a game whose round is built from a payload only it
 * understands — roulette's bet slip, a blackjack hand that first waits on an
 * insurance decision — cannot be driven by a rule the driver guessed. A module
 * implements this interface and answers for itself.
 *
 * The driver consults the interface first and falls back to its built-in rules
 * when a module does not implement it, so adding the interface changes nothing
 * for a module that ignores it.
 *
 * Implementations must be pure: the same state, bet and previous action must
 * always yield the same next action, so a seeded simulation is reproducible.
 */
interface SimulationStrategyInterface
{
    /**
     * The action that opens a round from the given state.
     *
     * A slot returns a paid spin; roulette returns a spin carrying a bet slip;
     * blackjack returns a deal.
     */
    public function simulationAction(GameStateInterface $state, Money $bet): GameAction;

    /**
     * The next action for a round that has not completed, or null when the
     * driver should stop.
     */
    public function simulationContinuation(
        GameStateInterface $state,
        GameAction $previous
    ): ?GameAction;

    /** A one-line description of the strategy, for the simulation report. */
    public function simulationPolicy(): string;
}
