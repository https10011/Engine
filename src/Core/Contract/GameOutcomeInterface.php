<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

use CasinoEngine\Core\Value\Ratio;

/**
 * The module's verdict for one action, produced *before* any money moves.
 *
 * The module states what happened in game terms — how many times the bet was
 * wagered and how many times it was returned — and the engine turns that into
 * currency. A module therefore never computes an amount, and cannot invent a
 * balance.
 */
interface GameOutcomeInterface
{
    /**
     * How many times the bet is wagered by this action. `1` for a paid spin or
     * a dealt hand; `0` for a free spin or a bonus continuation.
     */
    public function wagerMultiplier(): Ratio;

    /**
     * How many times the bet is returned. `0` for a losing spin, `2` for a
     * 1:1 win, `Ratio::of(3, 2)` for a blackjack paid 3:2.
     */
    public function payoutMultiplier(): Ratio;

    /**
     * The stake this outcome's multipliers apply to.
     *
     * Most games return `null`, meaning "use the bet submitted with the
     * action". A game whose round spans several actions (blackjack: deal, then
     * hit or stand) returns the stake the round was opened with, so a
     * completing action that carries no new bet still settles against the
     * original wager. The amount comes from the module's own recorded state,
     * never from the request, so a client cannot inflate it.
     */
    public function stakeBasis(): ?\CasinoEngine\Core\Value\Money;

    /** Game-specific payload for the frontend and the history record. */
    public function detail(): GameOutcomeDetailInterface;

    public function nextState(): GameStateInterface;

    /** @return list<FeatureTransition> */
    public function featureTransitions(): array;

    /**
     * Domain events the module wants emitted after the round commits.
     *
     * @return list<array{name:string,payload:array<string,mixed>}>
     */
    public function events(): array;
}
