<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Route;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Api\Middleware\MiddlewareInterface;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Exception\AuthenticationException;
use CasinoEngine\Core\Exception\EngineException;
use CasinoEngine\Core\Exception\RateLimitException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Persistence\Repository\RateLimitRepository;
use Throwable;

/**
 * A small, dependency-free HTTP router.
 *
 * Deliberately not a framework: it does method+path matching, resolves
 * middleware by name, and maps exceptions to the documented envelope. It has
 * no global state, so a test can dispatch a request and assert on the response
 * object without a server, sockets or output buffering.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, MiddlewareInterface> */
    private array $middleware = [];

    private ?RateLimitRepository $rateLimits = null;

    private int $rateLimitPerMinute = 0;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly bool $debug = false
    ) {
    }

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    public function use(string $name, MiddlewareInterface $middleware): void
    {
        $this->middleware[$name] = $middleware;
    }

    public function withRateLimiting(RateLimitRepository $rateLimits, int $perMinute): void
    {
        $this->rateLimits = $rateLimits;
        $this->rateLimitPerMinute = $perMinute;
    }

    /**
     * Dispatches a request and always returns a response.
     *
     * A thrown {@see EngineException} becomes the documented error envelope;
     * any other {@see Throwable} becomes a generic 500 whose body never
     * contains the exception message unless debug mode is on.
     */
    public function dispatch(Request $request): Response
    {
        try {
            return $this->handle($request);
        } catch (EngineException $e) {
            return $this->errorResponse($e);
        } catch (Throwable $e) {
            return $this->unexpectedErrorResponse($e);
        }
    }

    private function handle(Request $request): Response
    {
        $this->enforceRateLimit($request);

        if ($request->method === 'OPTIONS') {
            return Response::noContent()->withHeaders($this->corsHeaders());
        }

        $context = new RequestContext();

        $allowed = [];
        foreach ($this->routes as $route) {
            $parameters = $route->match($request->method, $request->path);
            if ($parameters === null) {
                continue;
            }

            $response = $this->runMiddleware($route, $request, $context, $parameters);
            if ($response instanceof Response) {
                return $response->withHeaders($this->corsHeaders());
            }

            return $route->invoke($parameters, $request, $context)->withHeaders($this->corsHeaders());
        }

        // Collect the methods that *would* match this path so a 405 is
        // informative without leaking which handlers exist.
        foreach ($this->routes as $route) {
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                if ($route->match($method, $request->path) !== null) {
                    $allowed[] = $route->method;
                    break;
                }
            }
        }

        if ($allowed !== []) {
            return Response::error(405, [
                'code'    => 'METHOD_NOT_ALLOWED',
                'message' => 'That method is not supported for this endpoint.',
                'details' => ['allowed' => array_values(array_unique($allowed))],
            ], ['path' => $request->path])->withHeaders($this->corsHeaders());
        }

        return Response::error(404, [
            'code'    => 'NOT_FOUND',
            'message' => 'No endpoint matches this request.',
            'details' => [],
        ], ['path' => $request->path])->withHeaders($this->corsHeaders());
    }

    /**
     * @param array<string, string> $parameters
     */
    private function runMiddleware(Route $route, Request $request, RequestContext $context, array $parameters): ?Response
    {
        foreach ($route->middleware as $name) {
            $middleware = $this->middleware[$name] ?? null;
            if ($middleware === null) {
                throw new ValidationException("Middleware '$name' is not registered.");
            }

            $result = $middleware->handle($request, $context, $parameters);
            if ($result instanceof Response) {
                return $result;
            }
        }

        return null;
    }

    private function enforceRateLimit(Request $request): void
    {
        if ($this->rateLimits === null || $this->rateLimitPerMinute <= 0) {
            return;
        }

        $now = $this->clock->now();
        $windowStart = $now->setTime((int) $now->format('H'), (int) $now->format('i'), 0);

        $bucket = 'ip:' . $request->clientIp();
        $hits = $this->rateLimits->hit($bucket, $windowStart->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'));

        if ($hits > $this->rateLimitPerMinute) {
            $retryAfter = 60 - (int) $now->format('s');
            throw new RateLimitException(
                'Too many requests. Please slow down.',
                ['retry_after' => max(1, $retryAfter)]
            );
        }
    }

    private function errorResponse(EngineException $e): Response
    {
        $status = $e->httpStatus();
        $details = [];
        if ($e instanceof RateLimitException) {
            $details = $e->context();
        }

        return Response::error($status, [
            'code'    => $e->errorCode(),
            'message' => $e->getMessage(),
            'details' => (object) $details,
        ])->withHeaders($this->corsHeaders());
    }

    private function unexpectedErrorResponse(Throwable $e): Response
    {
        $message = $this->debug
            ? $e->getMessage()
            : 'An unexpected error occurred. The request has been logged.';

        return Response::error(500, [
            'code'    => 'INTERNAL_ERROR',
            'message' => $message,
            'details' => (object) [],
        ])->withHeaders($this->corsHeaders());
    }

    /** @return array<string, string> */
    private function corsHeaders(): array
    {
        return [
            'Allow'            => 'GET, POST, DELETE, OPTIONS',
            'Cache-Control'    => 'no-store',
        ];
    }
}
