<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Middleware;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;

/**
 * A named step that runs before a route handler.
 *
 * Returning a {@see Response} short-circuits the request; returning null lets
 * it continue. Middleware communicates to the handler through the request's
 * attributes, which keeps handlers free of transport concerns.
 */
interface MiddlewareInterface
{
    /**
     * @param array<string, string> $parameters
     */
    public function handle(Request $request, RequestContext $context, array $parameters): ?Response;
}
