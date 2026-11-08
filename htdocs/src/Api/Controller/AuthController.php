<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Database\ConnectionInterface;
use CasinoEngine\Core\Exception\AuthorizationException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Auth\AuthServiceInterface;
use CasinoEngine\Services\Wallet\WalletServiceInterface;

/**
 * Authentication endpoints.
 *
 * `POST /api/v1/auth/login` and `POST /api/v1/auth/register` are the only
 * endpoints that accept a password. `GET /api/v1/auth/me` exists so a client
 * can confirm a token is still valid without playing a round.
 *
 * Self-service registration was added for the player frontend. It is an
 * additive route: it reuses the same repositories the `user:create` console
 * command uses, and it grants only virtual credits. An operator can switch it
 * off with `security.allow_self_registration`.
 */
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly WalletServiceInterface $wallet,
        private readonly UserRepository $users,
        private readonly WalletRepository $wallets,
        private readonly ConnectionInterface $connection,
        private readonly ClockInterface $clock,
        private readonly int $startingBalanceMinor,
        private readonly string $currency,
        private readonly int $scale,
        private readonly bool $registrationEnabled = true
    ) {
    }

    /**
     * Create a player account and sign it in.
     *
     * The response has the same shape as a successful login so the client uses
     * one code path for both. Only virtual credits are created; there is no
     * payment, deposit or withdrawal step anywhere in this flow.
     *
     * @param array<string, string> $parameters
     */
    public function register(array $parameters, Request $request, RequestContext $context): Response
    {
        if (!$this->registrationEnabled) {
            throw new AuthorizationException('Self-service registration is disabled on this deployment.');
        }

        $username = trim($request->requireString('username'));
        $password = $request->requireString('password');
        $displayName = trim((string) ($request->input('display_name') ?? $username));

        if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username) !== 1) {
            throw new ValidationException(
                'Username must be 3-64 characters of letters, digits, dot, dash or underscore.'
            );
        }

        $length = strlen($password);
        if ($length < 10 || $length > 128) {
            throw new ValidationException('Password must be between 10 and 128 characters.');
        }

        if ($displayName === '') {
            $displayName = $username;
        }
        if (mb_strlen($displayName) > 64) {
            throw new ValidationException('Display name must be at most 64 characters.');
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new ValidationException('That username is already taken.');
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // A user without a wallet cannot play, so both rows are written
        // together or not at all.
        $this->connection->beginTransaction();
        try {
            $userId = $this->users->create($username, $displayName, $now);
            $this->wallets->createForUser($userId, $this->currency, $this->scale, $this->startingBalanceMinor, $now);
            $this->users->setCredentials(
                $userId,
                $this->auth->hashPassword($password),
                $this->auth->passwordAlgo(),
                $now
            );
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        $token = $this->auth->issueToken($userId);
        $user = $this->users->findById($userId);

        return Response::created([
            'user' => [
                'id'           => $userId,
                'username'     => $username,
                'display_name' => $displayName,
                'role'         => (string) ($user['role'] ?? 'player'),
            ],
            'token'      => $token['token'],
            'expires_at' => $token['expires_at'],
            'balance'    => $this->wallet->balance($userId)->jsonSerialize(),
        ]);
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
