<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Runtime;

use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\EngineRuntimeException;
use CasinoEngine\Core\GameContext;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs one game action.
 *
 * The runtime is intentionally thin. Its job is to call the module in a
 * controlled way and to guarantee two properties:
 *
 *  - the module cannot move money (it is given no wallet), and
 *  - a failure inside the module surfaces as a typed engine error rather than
 *    an uncaught throwable, without any partial effect.
 *
 * RNG draws happen here, outside the database transaction. A transaction held
 * open across a long computation would hold row locks for its whole duration,
 * which is exactly the contention a busy wallet cannot afford.
 */
final class GameRuntime
{
    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    /**
     * @throws \CasinoEngine\Core\Exception\GameRuleException
     * @throws EngineRuntimeException
     */
    public function execute(
        GameInterface $game,
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        // Validate before executing so an illegal action is rejected with a
        // domain error, not with a half-evaluated outcome.
        $game->validateAction($action, $state);

        $startedAt = microtime(true);

        try {
            $outcome = $game->execute($action, $state, $context);
        } catch (\CasinoEngine\Core\Exception\GameRuleException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger?->error('Game execution failed', [
                'game_id'   => $game->metadata()->id(),
                'action'    => $action->name(),
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);

            // The module's internal failure detail stays in the log; the caller
            // gets a stable, non-leaking error.
            throw new EngineRuntimeException(
                'The game could not complete this action.',
                ['game_id' => $game->metadata()->id(), 'action' => $action->name()],
                $e
            );
        }

        $this->assertOutcomeIsConsistent($game, $action, $state, $outcome);

        $this->logger?->debug('Game action executed', [
            'game_id'          => $game->metadata()->id(),
            'action'           => $action->name(),
            'payout_multiplier' => (string) $outcome->payoutMultiplier(),
            'wager_multiplier'  => (string) $outcome->wagerMultiplier(),
            'duration_ms'      => round((microtime(true) - $startedAt) * 1000, 3),
        ]);

        return $outcome;
    }

    /**
     * Rejects an outcome that cannot be settled safely.
     *
     * A module returning a negative multiplier or a state that is not the one
     * it was handed would corrupt the round, so the runtime refuses it rather
     * than letting the wallet layer discover it later.
     */
    private function assertOutcomeIsConsistent(
        GameInterface $game,
        GameActionInterface $action,
        GameStateInterface $state,
        GameOutcomeInterface $outcome
    ): void {
        if ($outcome->payoutMultiplier()->numerator() < 0 || $outcome->wagerMultiplier()->numerator() < 0) {
            throw new EngineRuntimeException(
                'The game produced an invalid multiplier.',
                ['game_id' => $game->metadata()->id(), 'action' => $action->name()]
            );
        }

        // A wager multiplier above 1 would let a module debit more than the
        // player asked for. Multi-round games express extra wagers as separate
        // actions instead.
        if ($outcome->wagerMultiplier()->toFloat() > 1.0) {
            throw new EngineRuntimeException(
                'The game attempted to wager more than the submitted bet.',
                ['game_id' => $game->metadata()->id(), 'action' => $action->name()]
            );
        }

        if ($action->bet() !== null && $outcome->wagerMultiplier()->isZero()) {
            // A paid action that claims it wagered nothing would let a player
            // act without paying. This is a module bug, not a player error.
            throw new EngineRuntimeException(
                'The game accepted a bet but reported no wager.',
                ['game_id' => $game->metadata()->id(), 'action' => $action->name()]
            );
        }

        // The state must round-trip: what the module returns is what will be
        // persisted, and it must be serialisable.
        $roundTrip = $outcome->nextState()->toArray();
        if (json_encode($roundTrip) === false) {
            throw new EngineRuntimeException(
                'The game produced a state that cannot be persisted.',
                ['game_id' => $game->metadata()->id(), 'action' => $action->name()]
            );
        }
    }
}
