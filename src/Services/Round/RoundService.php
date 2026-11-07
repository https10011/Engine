<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Round;

use CasinoEngine\Core\Config\Config;
use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameSessionInterface;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Event\EngineEvents;
use CasinoEngine\Core\Event\EventDispatcherInterface;
use CasinoEngine\Core\Exception\IdempotencyConflictException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Core\Runtime\GameRuntime;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\RoundResult;
use CasinoEngine\Persistence\Repository\BetRepository;
use CasinoEngine\Persistence\Repository\PayoutRepository;
use CasinoEngine\Persistence\Repository\RoundRepository;
use CasinoEngine\Services\Betting\BettingServiceInterface;
use CasinoEngine\Services\Idempotency\IdempotencyStoreInterface;
use CasinoEngine\Services\Payout\PayoutServiceInterface;
use CasinoEngine\Services\Rng\RngFactory;
use CasinoEngine\Services\Session\SessionServiceInterface;
use CasinoEngine\Services\Statistics\StatisticsServiceInterface;
use CasinoEngine\Services\Transaction\TransactionManagerInterface;
use CasinoEngine\Services\Wallet\WalletServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Settles one game action: evaluate, wager, pay, record.
 *
 * The ordering inside the transaction is what guarantees a failure can never
 * leave a half-settled round:
 *
 *  1. the session row is locked, so two actions on one session serialise;
 *  2. the module evaluates the action (randomness was drawn outside);
 *  3. the wager is debited, then the payout credited, both under the wallet
 *     row lock;
 *  4. the round, bet, payout and statistics rows are written;
 *  5. the session state is advanced.
 *
 * Any failure rolls all of it back. The wallet row lock means a concurrent
 * round either waits or sees the committed balance, so the same credits can
 * never be spent twice. The idempotency key, claimed before the transaction,
 * means a retried request returns the original answer rather than playing on.
 */
final class RoundService
{
    public function __construct(
        private readonly GameRegistryInterface $games,
        private readonly BettingServiceInterface $betting,
        private readonly SessionServiceInterface $sessions,
        private readonly WalletServiceInterface $wallets,
        private readonly PayoutServiceInterface $payouts,
        private readonly StatisticsServiceInterface $statistics,
        private readonly IdempotencyStoreInterface $idempotency,
        private readonly TransactionManagerInterface $transactions,
        private readonly RoundRepository $rounds,
        private readonly BetRepository $bets,
        private readonly PayoutRepository $payoutRecords,
        private readonly GameRuntime $runtime,
        private readonly ClockInterface $clock,
        private readonly Config $config,
        private readonly RngFactory $rngFactory,
        private readonly ?EventDispatcherInterface $events = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Executes one action against a session.
     *
     * @param string|null $idempotencyKey When supplied, the call is
     *        replay-protected: the same key with the same payload returns the
     *        original result, while the same key with a different payload is a
     *        conflict rather than a silent replay of the wrong result.
     */
    public function execute(
        GameSessionInterface $session,
        GameActionInterface $action,
        ?string $idempotencyKey = null
    ): RoundResult {
        $gameId = $session->gameId();
        $userId = $session->userId();

        $fingerprint = $idempotencyKey === null
            ? null
            : $this->idempotency->fingerprint(
                $gameId,
                $action->name(),
                $action->payload(),
                $action->bet()?->minorUnits()
            );

        if ($idempotencyKey !== null) {
            $replay = $this->resolveIdempotency($userId, $gameId, $idempotencyKey, $fingerprint);
            if ($replay !== null) {
                return $replay;
            }
        }

        try {
            $result = $this->transactions->transactional(
                fn (): RoundResult => $this->settle($session, $action, $idempotencyKey, $fingerprint)
            );
        } catch (Throwable $e) {
            if ($idempotencyKey !== null) {
                // A failed attempt releases its claim so it can be retried;
                // a completed one keeps it and stays replayable.
                $this->idempotency->release($userId, $gameId, $idempotencyKey);
            }
            throw $e;
        }

        $this->afterCommit($result);

        return $result;
    }

    private function settle(
        GameSessionInterface $session,
        GameActionInterface $action,
        ?string $idempotencyKey,
        ?string $fingerprint
    ): RoundResult {
        $gameId = $session->gameId();
        $userId = $session->userId();
        $game = $this->games->get($gameId);

        // Re-read the session under a row lock: the caller's copy may be stale
        // if another action completed in the meantime.
        $locked = $this->sessions->loadForUserLocked($session->id(), $userId);

        // The session service carries state as an opaque array; the module
        // turns it back into its own typed state before evaluation.
        $locked = $locked->withState(
            $game->hydrateState($locked->state()->toArray()),
            $locked->updatedAt()
        );

        $currency = (string) $this->config->get('engine.currency', 'PHP');
        $scale = (int) $this->config->get('betting.currency_minor_units', 2);
        $bet = $action->bet() ?? Money::zero($currency, $scale);

        // Enforce the game's own limits here, before the module runs. A module
        // could check them too, but a module that forgot would let a bet past
        // the limits it advertises in its metadata, so the engine checks.
        if ($bet->isPositive()) {
            $this->betting->validate($bet, $game->metadata());
        }

        $context = $this->buildContext($gameId);

        // 1. Evaluate. Nothing has moved yet, and a rule violation here aborts
        //    the transaction without touching the wallet.
        $outcome = $this->runtime->execute($game, $action, $locked->state(), $context);

        // A multi-action round settles against the stake it was opened with,
        // not against the action that happens to complete it (a blackjack
        // "stand" carries no bet of its own).
        $stake = $outcome->stakeBasis() ?? $bet;

        $wagered = $this->payouts->compute($bet, $outcome->wagerMultiplier());
        $payout = $this->payouts->compute($stake, $outcome->payoutMultiplier());
        $this->payouts->assertWithinMaxWin($stake, $payout, $game->metadata()->maxWinMultiplier());

        $roundNumber = $locked->roundCount() + 1;
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // The round row is written first so bets and payouts can reference it.
        $roundId = $this->rounds->create(
            $locked->id(),
            $userId,
            $gameId,
            $roundNumber,
            $action->name(),
            $wagered->minorUnits(),
            $payout->minorUnits(),
            $payout->subtract($wagered)->minorUnits(),
            $outcome->payoutMultiplier()->numerator(),
            $outcome->payoutMultiplier()->denominator(),
            $outcome->detail()->toArray(),
            array_map(static fn (FeatureTransition $t): array => $t->toArray(), $outcome->featureTransitions()),
            $now
        );

        $balanceAfter = $this->wallets->balance($userId);

        // 2. Wager.
        if ($wagered->isPositive()) {
            $balanceAfter = $this->wallets->debit(
                $userId,
                $wagered,
                'wager',
                $roundId,
                sprintf('wager:%s:%d', $locked->id(), $roundNumber)
            );
        }

        // 3. Pay. The multiplier applies to the submitted bet, not the wager,
        //    so a free spin on a 1.00 bet still pays as though 1.00 were staked.
        if ($payout->isPositive()) {
            $balanceAfter = $this->wallets->credit(
                $userId,
                $payout,
                'payout',
                $roundId,
                sprintf('payout:%s:%d', $locked->id(), $roundNumber)
            );
        }

        // 4. Record the money and the aggregate statistics.
        if ($bet->isPositive()) {
            $this->bets->create($roundId, $userId, $bet->minorUnits(), $bet->currency(), 'main', $now);
        }
        if ($payout->isPositive()) {
            $this->payoutRecords->create(
                $roundId,
                $userId,
                $payout->minorUnits(),
                $payout->currency(),
                $outcome->payoutMultiplier()->numerator(),
                $outcome->payoutMultiplier()->denominator(),
                'main',
                $now
            );
        }
        $this->statistics->recordRound($userId, $gameId, $wagered, $payout, $roundId);

        // 5. Advance the session.
        $advanced = $locked
            ->withState($outcome->nextState(), $this->clock->now())
            ->withRoundCount($roundNumber, $this->clock->now());
        $this->sessions->persist($advanced);

        $result = new RoundResult(
            $roundId,
            $locked->id(),
            $gameId,
            $roundNumber,
            $action->name(),
            $bet,
            $payout,
            $payout->subtract($wagered),
            $outcome->payoutMultiplier(),
            $balanceAfter,
            $outcome->nextState(),
            $outcome->detail(),
            $outcome->featureTransitions(),
            $outcome->events()
        );

        if ($idempotencyKey !== null && $fingerprint !== null) {
            $this->idempotency->complete($userId, $gameId, $idempotencyKey, $result->jsonSerialize(), $roundId);
        }

        return $result;
    }

    /**
     * Returns a stored result when the key was already used, or null when the
     * key is fresh and this caller may proceed.
     *
     * @throws IdempotencyConflictException when the key was reused with a
     *         different payload, or a concurrent request holds it.
     */
    private function resolveIdempotency(
        int $userId,
        string $gameId,
        string $key,
        ?string $fingerprint
    ): ?RoundResult {
        $existing = $this->idempotency->lookup($userId, $gameId, $key);

        if ($existing === null) {
            if (!$this->idempotency->claim($userId, $gameId, $key, (string) $fingerprint)) {
                throw new IdempotencyConflictException(
                    'An identical request is already being processed. Please retry shortly.'
                );
            }

            return null;
        }

        if (($existing['request_fingerprint'] ?? '') !== $fingerprint) {
            throw new IdempotencyConflictException(
                'This idempotency key was already used with a different request.'
            );
        }

        $stored = $this->idempotency->storedResponse($userId, $gameId, $key);
        if ($stored !== null) {
            return RoundResult::fromStoredResponse($stored);
        }

        // The row exists but is not completed: either a concurrent request is
        // mid-flight, or a previous attempt failed. Re-claiming disambiguates.
        if (!$this->idempotency->claim($userId, $gameId, $key, (string) $fingerprint)) {
            throw new IdempotencyConflictException(
                'An identical request is already being processed. Please retry shortly.'
            );
        }

        return null;
    }

    private function buildContext(string $gameId): GameContext
    {
        $game = $this->games->get($gameId);

        return new GameContext(
            $this->rngFactory->forEnvironment($this->config, $this->seed()),
            $game->metadata(),
            $game->defaultConfiguration(),
            $this->logger ?? new NullLogger(),
            $this->clock
        );
    }

    /** Simulation and tests may pin a seed through configuration. */
    private function seed(): ?int
    {
        $seed = $this->config->get('engine.rng_seed');

        return is_int($seed) ? $seed : null;
    }

    private function afterCommit(RoundResult $result): void
    {
        if ($this->events === null) {
            return;
        }

        foreach ($result->events as $event) {
            $payload = $event['payload'] ?? [];
            $this->events->dispatch((string) $event['name'], is_array($payload) ? $payload : []);
        }

        foreach ($result->featureTransitions as $transition) {
            $this->events->dispatch(EngineEvents::FEATURE_TRIGGERED, [
                'session_id' => $result->sessionId,
                'game_id'    => $result->gameId,
                'feature'    => $transition->type,
                'status'     => $transition->status,
            ]);
        }

        if ($result->bet->isPositive()) {
            $this->events->dispatch(EngineEvents::WAGER_PLACED, [
                'round_id' => $result->roundId,
                'amount'   => $result->bet->minorUnits(),
            ]);
        }

        if ($result->payout->isPositive()) {
            $this->events->dispatch(EngineEvents::PAYOUT_PAID, [
                'round_id' => $result->roundId,
                'amount'   => $result->payout->minorUnits(),
            ]);
        }

        $this->events->dispatch(EngineEvents::ROUND_COMPLETED, [
            'round_id' => $result->roundId,
            'game_id'  => $result->gameId,
        ]);
    }
}
