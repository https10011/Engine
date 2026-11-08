<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Middleware;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Exception\AuthorizationException;
use CasinoEngine\Services\Session\SessionServiceInterface;

/**
 * Loads the session named in the route and proves it belongs to the caller.
 *
 * Ownership is checked here, once, rather than in every handler. A handler
 * therefore never sees a session id it is not allowed to use, and a request
 * for another user's session is rejected with 403 before any game code runs.
 * The `{sessionId}` route parameter is the only accepted source of the id.
 */
final class SessionOwnershipMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SessionServiceInterface $sessions)
    {
    }

    public function handle(Request $request, RequestContext $context, array $parameters): ?Response
    {
        $sessionId = $parameters['sessionId'] ?? '';
        if ($sessionId === '') {
            throw new AuthorizationException('A session id is required in the path.');
        }

        $session = $this->sessions->load($sessionId);
        if ($session->userId() !== $context->userId()) {
            throw new AuthorizationException('This session belongs to another user.');
        }

        $context->setSession($session->toArray());

        return null;
    }
}
