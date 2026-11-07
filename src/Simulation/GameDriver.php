<?php

declare(strict_types=1);

namespace CasinoEngine\Simulation;

use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;

/**
 * Drives one game module through many rounds with no database and no HTTP.
 *
 * The driver calls the module exactly as the engine's round service does —
 * `validateAction()` then `execute()` — and applies the resulting multipliers
 * itself, so a simulation measures the module's real mathematics while never
 * touching a wallet, a session row or a transaction.
 *
 * It is the second implementation of the settlement rule, and that is
 * deliberate: it lets the framework's mathematics be analysed at millions of
 * rounds per run, far faster than a database round trip per spin would allow.
 * The rule it implements is the same one the round service uses, and
 * RoundServiceIntegrationTest::testSimulationDriverAgreesWithTheRoundService
 * asserts the two agree on the same seed.
 */
final class GameDriver
{
    public function __construct(
        private readonly GameInterface $game,
        private readonly GameContext $context
    ) {
    }

    public function game(): GameInterface
    {
        return $this->game;
    }

    /**
     * Plays `$rounds` rounds from a fresh session.
     *
     * @param callable(GameStateInterface, int): GameAction $actionFactory
     *        Builds the action for round `$index` given the current state.
     * @return SimulationStatistics
     */
    public function run(int $rounds, callable $actionFactory): SimulationStatistics
    {
        $statistics = new SimulationStatistics();
        $state = $this->game->createInitialState($this->context);

        for ($index = 0; $index < $rounds; $index++) {
            $this->playRound($state, $actionFactory($state, $index), $statistics);
        }

        return $statistics;
    }

    /**
     * Plays one round, which may span several actions (a blackjack hand).
     *
     * Every action the module asks for is executed in sequence, and the whole
     * round's money is summed into a single observation. That keeps the
     * statistics comparable across games: one observation per round, whatever
     * the round is made of.
     *
     * The first action is supplied by the caller; later actions in a
     * multi-step round come from the driver's own strategy.
     */
    public function playRound(
        GameStateInterface &$state,
        GameAction $firstAction,
        SimulationStatistics $statistics,
        int $roundIndex = 0
    ): void {
        $wagered = 0;
        $returned = 0;
        $features = [];
        $action = $firstAction;
        $guard = 0;

        while (true) {
            $guard++;
            // A free-spin chain may legitimately run to the retrigger cap, so
            // the guard is generous; it exists to catch a module that loops,
            // not to bound a legal round.
            if ($guard > 5000) {
                throw new GameRuleException('A simulated round did not terminate.');
            }

            $outcome = $this->evaluate($action, $state);

            $bet = $action->bet() ?? Money::zero('PHP');
            $stake = $outcome->stakeBasis() ?? $bet;

            $wagered += $this->apply($bet, $outcome->wagerMultiplier());
            $returned += $this->apply($stake, $outcome->payoutMultiplier());

            foreach ($outcome->featureTransitions() as $transition) {
                $features[] = $transition->type . ':' . $transition->status;
            }

            $state = $outcome->nextState();

            if ($state->isComplete()) {
                break;
            }

            $next = $this->continuationAction($state, $action, $roundIndex);
            if ($next === null) {
                break;
            }

            $action = $next;
        }

        $statistics->record($wagered, $returned, $features);
    }

    /**
     * The next action a round needs, or null when the round is finished.
     *
     * Each category has its own continuation rule:
     *
     *  - a slot round continues through its free spins, each of which is a
     *    spin with no bet;
     *  - a card round continues through the player's decisions;
     *  - roulette is a single action.
     */
    private function continuationAction(
        GameStateInterface $state,
        GameAction $previous,
        int $roundIndex
    ): ?GameAction {
        return match ($this->game->metadata()->category()) {
            GameCategory::Slot => $this->slotContinuation($state),
            GameCategory::Card => $this->cardContinuation($state, $previous),
            default => null,
        };
    }

    /**
     * A slot round is one paid spin plus the free spins it triggers.
     *
     * Keeping them in one observation is what makes the reported return
     * correct: free spins wager nothing and return something, so their wins
     * belong with the spin that paid for them.
     */
    private function slotContinuation(GameStateInterface $state): ?GameAction
    {
        $data = $state->toArray();
        $remaining = (int) ($data['features']['free_spins']['remaining'] ?? 0);

        return $remaining > 0 ? GameAction::create('spin') : null;
    }

    private function cardContinuation(GameStateInterface $state, GameAction $previous): GameAction
    {
        return GameAction::create($this->blackjackDecision($state));
    }

    /**
     * A simple, deterministic blackjack policy: stand on 17 or more, otherwise
     * hit. It is intentionally basic and is reported as such; the point is a
     * reproducible policy, not optimal play.
     */
    private function blackjackDecision(GameStateInterface $state): string
    {
        $data = $state->toArray();
        $total = (int) ($data['player_total'] ?? 0);

        return $total < 17 ? 'hit' : 'stand';
    }

    private function evaluate(GameAction $action, GameStateInterface $state): \CasinoEngine\Core\Contract\GameOutcomeInterface
    {
        $this->game->validateAction($action, $state);

        return $this->game->execute($action, $state, $this->context);
    }

    /**
     * Applies a multiplier to a stake in minor units.
     *
     * Uses the same rational rounding as the engine's payout service, so a
     * simulated 3:2 on an odd stake matches what a real round would pay.
     */
    private function apply(Money $stake, Ratio $multiplier): int
    {
        return $multiplier->applyToMinorUnits($stake->minorUnits());
    }
}
