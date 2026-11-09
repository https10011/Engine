<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Services\Admin\GameControlService;
use CasinoEngine\Services\Session\SessionServiceInterface;

/**
 * Game session lifecycle.
 *
 * Creating a session is where a user first commits to a game; the controller
 * resolves the game from the registry, asks the module for its initial state
 * and delegates persistence to the session service. No session is ever created
 * for a user id supplied by the client — the id comes from the token.
 */
final class SessionController extends AbstractController
{
    public function __construct(
        private readonly Engine $engine,
        private readonly GameRegistryInterface $registry,
        private readonly SessionServiceInterface $sessions,
        private readonly GameControlService $controls
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function create(array $parameters, Request $request, RequestContext $context): Response
    {
        $gameId = $request->requireString('game_id');
        $game = $this->registry->get($gameId);
        if (!$this->controls->isAvailable($gameId)) {
            throw new ValidationException('This game is currently unavailable.');
        }
        $state = $game->createInitialState($this->engine->contextFor($game));

        $session = $this->sessions->create($context->userId(), $gameId, $state);

        return Response::created(['session' => $session->toArray()]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function show(array $parameters, Request $request, RequestContext $context): Response
    {
        // Ownership was already proven by the middleware; the id in the context
        // is the authoritative one.
        return Response::ok(['session' => $context->session()]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function index(array $parameters, Request $request, RequestContext $context): Response
    {
        $limit = $this->intQuery($request, 'limit', 20, 1, 100);

        return Response::ok(
            ['sessions' => $this->sessions->listForUser($context->userId(), $limit)],
            200,
            $this->paginationMeta($limit, 0)
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    public function close(array $parameters, Request $request, RequestContext $context): Response
    {
        $sessionId = (string) ($context->session()['id'] ?? '');
        if ($sessionId === '') {
            throw new ValidationException('No session is loaded for this request.');
        }

        $this->sessions->close($sessionId);

        return Response::ok(['session_id' => $sessionId, 'status' => 'closed']);
    }
}
