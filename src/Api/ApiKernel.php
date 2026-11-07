<?php

declare(strict_types=1);

namespace CasinoEngine\Api;

use CasinoEngine\Api\Controller\AuthController;
use CasinoEngine\Api\Controller\GameController;
use CasinoEngine\Api\Controller\PlayController;
use CasinoEngine\Api\Controller\SessionController;
use CasinoEngine\Api\Controller\WalletController;
use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Api\Middleware\AuthMiddleware;
use CasinoEngine\Api\Middleware\SessionOwnershipMiddleware;
use CasinoEngine\Api\Route\Route;
use CasinoEngine\Api\Route\Router;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Persistence\Repository\RateLimitRepository;
use CasinoEngine\Services\Auth\AuthService;
use CasinoEngine\Services\Auth\AuthServiceInterface;
use CasinoEngine\Services\History\HistoryServiceInterface;
use CasinoEngine\Services\Round\RoundService;
use CasinoEngine\Services\Session\SessionServiceInterface;
use CasinoEngine\Services\Statistics\StatisticsServiceInterface;
use CasinoEngine\Services\Wallet\WalletServiceInterface;

/**
 * Builds the API: services, middleware and the route table.
 *
 * Everything is constructed from the {@see Engine}'s container, so the API
 * invokes exactly the same services as the CLI and the simulation harness.
 * There is no second implementation of game logic behind HTTP — the round
 * service settles a request the same way it settles a `sim:run`.
 */
final class ApiKernel
{
    public function __construct(private readonly Engine $engine)
    {
    }

    public function router(): Router
    {
        $container = $this->engine->container();

        $router = new Router(
            $container->get(ClockInterface::class),
            $this->engine->config()->debug()
        );

        $auth = new AuthService(
            $container->get(\CasinoEngine\Persistence\Repository\UserRepository::class),
            $container->get(\CasinoEngine\Persistence\Repository\TokenRepository::class),
            $container->get(ClockInterface::class),
            (int) $this->engine->config()->get('security.api_token_ttl_seconds', 86400)
        );

        $router->use('auth', new AuthMiddleware($auth));
        $router->use('session.owner', new SessionOwnershipMiddleware(
            $container->get(SessionServiceInterface::class)
        ));

        $perMinute = (int) $this->engine->config()->get('security.api_rate_limit_per_minute', 120);
        if ($perMinute > 0) {
            $router->withRateLimiting($container->get(RateLimitRepository::class), $perMinute);
        }

        $this->registerAuthRoutes($router, $auth, $container);
        $this->registerGameRoutes($router, $container);
        $this->registerSessionRoutes($router, $container);
        $this->registerPlayRoutes($router, $container);
        $this->registerWalletRoutes($router, $container);

        $router->add(new Route('GET', '/api/v1/health', static fn (): Response => Response::ok([
            'status'  => 'ok',
            'engine'  => 'universal-casino-engine',
        ])));

        return $router;
    }

    public function handle(Request $request): Response
    {
        // Games are discovered once per request; discovery is idempotent.
        $this->engine->discoverGames();

        return $this->router()->dispatch($request);
    }

    /**
     * @param \CasinoEngine\Core\Container $container
     */
    private function registerAuthRoutes(Router $router, AuthService $auth, \CasinoEngine\Core\Container $container): void
    {
        $controller = new AuthController(
            $auth,
            $container->get(WalletServiceInterface::class)
        );

        $router->add(new Route('POST', '/api/v1/auth/login', $controller->login(...)));
        $router->add(new Route('GET', '/api/v1/auth/me', $controller->me(...), ['auth']));
        $router->add(new Route('POST', '/api/v1/auth/logout', $controller->logout(...), ['auth']));
    }

    private function registerGameRoutes(Router $router, \CasinoEngine\Core\Container $container): void
    {
        $controller = new GameController(
            $this->engine,
            $container->get(GameRegistryInterface::class)
        );

        $router->add(new Route('GET', '/api/v1/games', $controller->index(...)));
        $router->add(new Route('GET', '/api/v1/games/{gameId}', $controller->show(...)));
    }

    private function registerSessionRoutes(Router $router, \CasinoEngine\Core\Container $container): void
    {
        $controller = new SessionController(
            $this->engine,
            $container->get(GameRegistryInterface::class),
            $container->get(SessionServiceInterface::class)
        );

        $router->add(new Route('GET', '/api/v1/sessions', $controller->index(...), ['auth']));
        $router->add(new Route('POST', '/api/v1/sessions', $controller->create(...), ['auth']));
        $router->add(new Route('GET', '/api/v1/sessions/{sessionId}', $controller->show(...), ['auth', 'session.owner']));
        $router->add(new Route('POST', '/api/v1/sessions/{sessionId}/close', $controller->close(...), ['auth', 'session.owner']));
    }

    private function registerPlayRoutes(Router $router, \CasinoEngine\Core\Container $container): void
    {
        $controller = new PlayController(
            $this->engine,
            $container->get(RoundService::class),
            $container->get(HistoryServiceInterface::class)
        );

        $router->add(new Route(
            'POST',
            '/api/v1/sessions/{sessionId}/actions',
            $controller->action(...),
            ['auth', 'session.owner']
        ));
        $router->add(new Route('GET', '/api/v1/rounds/{roundId}', $controller->show(...), ['auth']));
    }

    private function registerWalletRoutes(Router $router, \CasinoEngine\Core\Container $container): void
    {
        $controller = new WalletController(
            $container->get(WalletServiceInterface::class),
            $container->get(HistoryServiceInterface::class),
            $container->get(StatisticsServiceInterface::class)
        );

        $router->add(new Route('GET', '/api/v1/wallet', $controller->balance(...), ['auth']));
        $router->add(new Route('GET', '/api/v1/wallet/transactions', $controller->transactions(...), ['auth']));
        $router->add(new Route('GET', '/api/v1/history/rounds', $controller->gameHistory(...), ['auth']));
        $router->add(new Route('GET', '/api/v1/statistics', $controller->statistics(...), ['auth']));
    }
}
