<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Services\Auth\AuthServiceInterface;
use CasinoEngine\Services\Wallet\WalletServiceInterface;

/**
 * Authentication endpoints.
 *
 * `POST /api/v1/auth/login` is the only endpoint that accepts a password and
 * the only one reachable without a token. `GET /api/v1/auth/me` exists so a
 * client can confirm a token is still valid without playing a round.
 */
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly WalletServiceInterface $wallet
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function login(array $parameters, Request $request, RequestContext $context): Response
    {
        $result = $this->auth->login(
            $request->requireString('username'),
            $request->requireString('password'),
            $request->clientIp()
        );

        $balance = $this->wallet->balance((int) $result['user']['id']);

        return Response::ok([
            'user'    => $result['user'],
            'token'   => $result['token']['token'],
            'expires_at' => $result['token']['expires_at'],
            'balance' => $balance->jsonSerialize(),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function me(array $parameters, Request $request, RequestContext $context): Response
    {
        $user = $context->user();

        return Response::ok([
            'user'    => $user,
            'balance' => $this->wallet->balance((int) $user['id'])->jsonSerialize(),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function logout(array $parameters, Request $request, RequestContext $context): Response
    {
        $token = $request->bearerToken();
        if ($token !== null) {
            $this->auth->revoke($token, $request->clientIp());
        }

        return Response::noContent();
    }
}
