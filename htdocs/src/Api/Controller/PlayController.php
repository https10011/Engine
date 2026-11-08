<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Services\History\HistoryServiceInterface;
use CasinoEngine\Services\Round\RoundService;

/**
 * Submits player actions and returns settled results.
 *
 * This controller contains no game logic. It translates a JSON body into a
 * {@see GameAction}, hands it to the round service — the same service the CLI
 * and the simulation harness use — and returns the settled {@see \CasinoEngine\Core\Value\RoundResult}.
 * A bet amount is validated by the engine against the game's own limits; the
 * controller never computes a payout or a balance.
 *
 * The `Idempotency-Key` header is passed straight through, so a client that
 * retries a timed-out request gets the original result rather than a second
 * wager.
 */
final class PlayController extends AbstractController
{
    public function __construct(
        private readonly Engine $engine,
        private readonly RoundService $rounds,
        private readonly HistoryServiceInterface $history
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function action(array $parameters, Request $request, RequestContext $context): Response
    {
        $session = $this->engine->sessions()->loadForUser(
            (string) ($context->session()['id'] ?? ''),
            $context->userId()
        );

        $actionName = $request->requireString('action');
        $payload = $request->input('payload', []);
        if (!is_array($payload)) {
            throw new ValidationException('The field \'payload\' must be an object when present.');
        }

        $action = $this->buildAction($actionName, $payload, $request->input('bet'));
        $key = $this->idempotencyKey($request);

        $result = $this->rounds->execute($session, $action, $key);

        return Response::ok(['round' => $result->jsonSerialize()]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function show(array $parameters, Request $request, RequestContext $context): Response
    {
        $roundId = (int) ($parameters['roundId'] ?? 0);
        $round = $this->history->round($roundId);

        if ($round === null) {
            throw new ValidationException("No round with id $roundId exists.");
        }

        if ((int) ($round['user_id'] ?? 0) !== $context->userId()) {
            throw new \CasinoEngine\Core\Exception\AuthorizationException('That round belongs to another user.');
        }

        return Response::ok(['round' => $round]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildAction(string $name, array $payload, mixed $bet): GameAction
    {
        if ($bet === null) {
            return GameAction::create($name, $payload);
        }

        if (!is_int($bet) && !is_string($bet) && !is_float($bet)) {
            throw new ValidationException('The field \'bet\' must be an integer amount in minor units.');
        }

        $amount = (string) $bet;
        if (preg_match('/^\d+$/', $amount) !== 1) {
            throw new ValidationException('The field \'bet\' must be a non-negative integer in minor units.');
        }

        $currency = (string) $this->engine->config()->get('engine.currency', 'PHP');
        $scale = (int) $this->engine->config()->get('betting.currency_minor_units', 2);

        return GameAction::withBet($name, Money::of((int) $amount, $currency, $scale), $payload);
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('idempotency-key');
        if ($key === null || trim($key) === '') {
            return null;
        }

        $key = trim($key);
        if (strlen($key) > 191) {
            throw new ValidationException('The Idempotency-Key header must be at most 191 characters.');
        }

        return $key;
    }
}
