<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Middleware;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Exception\AuthenticationException;
use CasinoEngine\Services\Auth\AuthServiceInterface;

/**
 * Resolves the bearer token on the request to a user.
 *
 * Attaching the user to the request context rather than passing it through
 * every handler keeps controllers thin: a controller that needs a user calls
 * `$context->user()` and gets the authenticated one, or an exception. There is
 * no code path where a handler can act on behalf of a client-supplied user id,
 * because no such value is ever read.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthServiceInterface $auth)
    {
    }

    public function handle(Request $request, RequestContext $context, array $parameters): ?Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new AuthenticationException('A bearer token is required in the Authorization header.');
        }

        $context->setUser($this->auth->authenticate($token));

        return null;
    }
}
