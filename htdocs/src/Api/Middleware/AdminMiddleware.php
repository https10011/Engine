<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Middleware;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Exception\AuthorizationException;
use CasinoEngine\Persistence\Repository\UserRepository;

/**
 * Requires the authenticated user to hold the administrator role.
 *
 * The role is re-read from the database on every request rather than trusted
 * from the token or from anything the client sends. A token is evidence of
 * *identity*; it is not evidence of *authority*, and the two are deliberately
 * separate so revoking the administrator role takes effect immediately instead
 * of when the token expires.
 *
 * This runs after {@see AuthMiddleware}, so the context already holds a user.
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function handle(Request $request, RequestContext $context, array $parameters): ?Response
    {
        if (!$context->isAuthenticated()) {
            throw new AuthorizationException('This endpoint requires an authenticated administrator.');
        }

        if (!$this->users->isAdmin($context->userId())) {
            // The same message for "not signed in" and "signed in but not an
            // administrator": the distinction is not the caller's business.
            throw new AuthorizationException('This endpoint requires an authenticated administrator.');
        }

        return null;
    }
}
