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
use CasinoEngine\Services\Auth\PhoneNumber;
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

        $phone = PhoneNumber::normalize($request->requireString('phone'));
        $password = $request->requireString('password');
        $displayName = trim($request->requireString('display_name'));
        $age = $request->input('age');
        $otp = $request->input('otp');

        if (!is_int($age) && !(is_string($age) && ctype_digit($age))) {
            throw new ValidationException('Age must be a whole number.');
        }
        $age = (int) $age;
        if ($age < 18 || $age > 120) {
            throw new ValidationException('You must be between 18 and 120 years old to register.');
        }

        if (!is_string($otp) || preg_match('/^\d{6}$/', $otp) !== 1) {
            throw new ValidationException('Enter the six-digit verification code.');
        }

        $length = strlen($password);
        if ($length < 10 || $length > 128) {
            throw new ValidationException('Password must be between 10 and 128 characters.');
        }

        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 64) {
            throw new ValidationException('Display name must be between 2 and 64 characters.');
        }

        if ($this->users->findByPhone($phone) !== null) {
            throw new ValidationException('An account with that phone number already exists.');
        }

        // `username` remains the internal unique key; players never choose or
        // see it. The phone number is the credential they use to sign in.
        $username = 'p_' . substr(preg_replace('/\D+/', '', $phone) ?? '', -12)
            . '_' . bin2hex(random_bytes(3));
        if (strlen($username) > 64) {
            $username = substr($username, 0, 64);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // A user without a wallet cannot play, so every row is written together
        // or not at all. The starting balance is granted exactly once, here.
        $this->connection->beginTransaction();
        try {
            $userId = $this->users->createPlayer($username, $phone, $displayName, $age, $now);
            $this->wallets->createForUser($userId, $this->currency, $this->scale, $this->startingBalanceMinor, $now);
            $this->users->setCredentials(
                $userId,
                $this->auth->hashPassword($password),
                $this->auth->passwordAlgo(),
                $now
            );
            $this->connection->commit();
        } catch (\PDOException $error) {
            $this->connection->rollBack();
            if ($error->getCode() === '23000') {
                throw new ValidationException('An account with that phone number already exists.');
            }
            throw $error;
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
                'phone'        => $phone,
                'display_name' => $displayName,
                'age'          => $age,
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
        $identifier = $request->input('identifier');
        if (!is_string($identifier) || trim($identifier) === '') {
            $identifier = $request->requireString('username');
        }

        $result = $this->auth->login(
            $identifier,
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
