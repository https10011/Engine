<?php

declare(strict_types=1);

namespace CasinoEngine\Api;

use CasinoEngine\Api\Controller\AdminController;
use CasinoEngine\Api\Controller\AuthController;
use CasinoEngine\Api\Controller\GameController;
use CasinoEngine\Api\Controller\PlayController;
use CasinoEngine\Api\Controller\SessionController;
use CasinoEngine\Api\Controller\WalletController;
use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Api\Middleware\AdminMiddleware;
use CasinoEngine\Api\Middleware\AuthMiddleware;
use CasinoEngine\Api\Middleware\SessionOwnershipMiddleware;
use CasinoEngine\Api\Route\Route;
use CasinoEngine\Api\Route\Router;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Persistence\Repository\ProbabilityRepository;
use CasinoEngine\Persistence\Repository\RateLimitRepository;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Auth\AuthService;
use CasinoEngine\Services\Auth\AuthServiceInterface;
use CasinoEngine\Services\Probability\ProbabilityConfigServiceInterface;
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

        // One AuthService, resolved from the container, so the API and the CLI
        // share the same password algorithm and token policy.
        $auth = $container->get(AuthServiceInterface::class);
        \assert($auth instanceof AuthService);

        $router->use('auth', new AuthMiddleware($auth));
        $router->use('admin', new AdminMiddleware($container->get(UserRepository::class)));
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
        $this->registerAdminRoutes($router, $container);

        $version = (string) $this->engine->config()->get('engine.version', '1.0.0');
        $router->add(new Route('GET', '/api/v1/health', static fn (): Response => Response::ok([
            'status'  => 'ok',
            'engine'  => 'universal-casino-engine',
            'version' => $version,
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
        $security = $container->config();
        $controller = new AuthController(
            $auth,
            $container->get(WalletServiceInterface::class),
            $container->get(UserRepository::class),
            $container->get(WalletRepository::class),
            $container->get(ConnectionInterface::class),
            $container->get(ClockInterface::class),
            (int) $security->get('security.registration_starting_balance_minor', 100000),
            (string) $security->get('engine.currency', 'PHP'),
            (int) $security->get('betting.currency_minor_units', 2),
            (bool) $security->get('security.allow_self_registration', true)
        );

        $router->add(new Route('POST', '/api/v1/auth/register', $controller->register(...)));
        $router->add(new Route('POST', '/api/v1/auth/login', $controller->login(...)));
        $router->add(new Route('GET', '/api/v1/auth/me', $controller->me(...), ['auth']));
        $router->add(new Route('POST', '/api/v1/auth/logout', $controller->logout(...), ['auth']));
    }

    private function registerGameRoutes(Router $router, \CasinoEngine\Core\Container $container): void
    {
        $controller = new GameController($container->get(GameRegistryInterface::class));

        $router->add(new Route('GET', '/api/v1/games', $controller->index(...)));
        // The lobby catalogue is registered before the `{gameId}` route so a
        // request for `/api/v1/games/lobby` is not captured as a game id.
        $router->add(new Route('GET', '/api/v1/games/lobby', $controller->lobby(...)));
        $router->add(new Route('GET', '/api/v1/games/{gameId}/presentation', $controller->presentation(...)));
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

    /**
     * The administrator probability surface.
     *
     * Every route carries both `auth` and `admin`. `admin` re-reads the role
     * from the database, so a player token cannot reach these endpoints and a
     * revoked administrator loses access on their next request.
     */
    private function registerAdminRoutes(Router $router, \CasinoEngine\Core\Container $container): void
    {
        $controller = new AdminController(
            $this->engine,
            $container->get(GameRegistryInterface::class),
            $container->get(ProbabilityConfigServiceInterface::class),
            $container->get(ProbabilityRepository::class)
        );

        $guard = ['auth', 'admin'];

        $router->add(new Route('GET', '/api/v1/admin/overview', $controller->overview(...), $guard));
        $router->add(new Route('GET', '/api/v1/admin/probability/global', $controller->globalProfile(...), $guard));
        $router->add(new Route('POST', '/api/v1/admin/probability/global', $controller->saveGlobal(...), $guard));
        $router->add(new Route('GET', '/api/v1/admin/probability/games', $controller->games(...), $guard));
        $router->add(new Route('GET', '/api/v1/admin/probability/games/{gameId}', $controller->gameProfile(...), $guard));
        $router->add(new Route('POST', '/api/v1/admin/probability/games/{gameId}', $controller->saveGame(...), $guard));
        $router->add(new Route('POST', '/api/v1/admin/probability/games/{gameId}/reset', $controller->resetGame(...), $guard));
        $router->add(new Route('GET', '/api/v1/admin/probability/versions', $controller->versions(...), $guard));
        $router->add(new Route('POST', '/api/v1/admin/probability/restore', $controller->restore(...), $guard));
        $router->add(new Route('GET', '/api/v1/admin/audit', $controller->audit(...), $guard));
        $router->add(new Route('POST', '/api/v1/admin/simulate', $controller->simulate(...), $guard));
        $router->add(new Route('GET', '/api/v1/admin/jackpots', $controller->jackpots(...), $guard));
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
