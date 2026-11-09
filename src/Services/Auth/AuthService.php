<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Auth;

use CasinoEngine\Core\Contract\Service\ClockInterface;
use CasinoEngine\Core\Exception\AuthenticationException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Persistence\Repository\AuditRepository;
use CasinoEngine\Persistence\Repository\TokenRepository;
use CasinoEngine\Persistence\Repository\UserRepository;

/**
 * API authentication: password login and bearer-token verification.
 *
 * Two design decisions carry the security weight here:
 *
 *  1. Passwords are verified with {@see password_verify()} against an Argon2id
 *     hash; the plaintext is never stored, logged or compared by hand.
 *  2. Tokens are random and are stored only as a SHA-256 hash. Verification
 *     hashes the presented token and looks that hash up, so the database never
 *     holds a usable credential and no hand-written comparison of a secret
 *     ever happens.
 *
 * Every failure — unknown user, wrong password, expired token, revoked token —
 * raises the same {@see AuthenticationException}. Telling a caller *which* part
 * was wrong would let them enumerate usernames.
 */
final class AuthService implements AuthServiceInterface
{
    /** Recognised values of `security.password_algo`. */
    private const PASSWORD_ALGORITHMS = ['argon2id', 'bcrypt'];

    /**
     * The algorithm actually used. Argon2id is preferred, but some shared
     * hosts build PHP without Argon2 support; there is no point making the
     * whole application unbootable for that. Falling back to bcrypt is
     * recorded in `user_credentials.algo`, so hashes stay self-describing.
     */
    private readonly string $effectivePasswordAlgo;

    /** A `PASSWORD_*` constant (a string on PHP 8.3). */
    private readonly string $passwordAlgorithm;

    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly ClockInterface $clock,
        private readonly int $tokenTtlSeconds = 86400,
        private readonly ?AuditRepository $audit = null,
        string $passwordAlgo = 'argon2id'
    ) {
        if (!in_array($passwordAlgo, self::PASSWORD_ALGORITHMS, true)) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                "Unknown password algorithm '{$passwordAlgo}'; expected one of "
                . implode(', ', self::PASSWORD_ALGORITHMS) . '.'
            );
        }

        if ($passwordAlgo === 'argon2id' && !defined('PASSWORD_ARGON2ID')) {
            $passwordAlgo = 'bcrypt';
        }

        $this->effectivePasswordAlgo = $passwordAlgo;
        $this->passwordAlgorithm = $passwordAlgo === 'argon2id' ? \PASSWORD_ARGON2ID : \PASSWORD_BCRYPT;
    }

    /** The algorithm name actually used, as stored in `user_credentials.algo`. */
    public function passwordAlgo(): string
    {
        return $this->effectivePasswordAlgo;
    }

    public function login(string $identifier, string $password, ?string $ipAddress = null): array
    {
        $user = $this->verifyPassword($identifier, $password, $ipAddress);

        return [
            'user'  => $user,
            'token' => $this->issueToken((int) $user['id']),
        ];
    }

    public function verifyPassword(string $identifier, string $password, ?string $ipAddress = null): array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            throw new AuthenticationException('Phone number and password are required.');
        }

        // A phone number and a legacy/operator username are both accepted. The
        // phone lookup uses the canonical value, so spacing and 09/+63 forms
        // resolve to the same account.
        $phone = PhoneNumber::tryNormalize($identifier);
        $user = $phone === null ? null : $this->users->findByPhone($phone);
        $user ??= $this->users->findByUsername($identifier);

        if ($user === null || ($user['status'] ?? 'active') !== 'active') {
            // A dummy verify keeps the timing of an unknown user close to that
            // of a known one with a wrong password.
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$AAAAAAAAAAAAAAAAAAAAAA$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
            $this->audit('auth.login.failed', null, $identifier, $ipAddress);
            throw new AuthenticationException('Invalid phone number or password.');
        }

        $credentials = $this->users->findCredentials((int) $user['id']);
        if ($credentials === null || !password_verify($password, (string) $credentials['password_hash'])) {
            $this->audit('auth.login.failed', (int) $user['id'], $identifier, $ipAddress);
            throw new AuthenticationException('Invalid phone number or password.');
        }

        $this->audit('auth.login.succeeded', (int) $user['id'], $identifier, $ipAddress);

        return $this->publicUser($user);
    }

    public function issueToken(int $userId): array
    {
        $now = $this->clock->now();
        $expiresAt = $now->modify('+' . $this->tokenTtlSeconds . ' seconds');
        $token = bin2hex(random_bytes(32));

        $this->tokens->create(
            $userId,
            $this->hash($token),
            $expiresAt->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s')
        );

        return [
            'token'      => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];
    }

    public function authenticate(string $presentedToken): array
    {
        $presentedToken = trim($presentedToken);
        if ($presentedToken === '') {
            throw new AuthenticationException('An API token is required.');
        }

        $row = $this->tokens->findLiveByHash($this->hash($presentedToken), $this->clock->now()->format('Y-m-d H:i:s'));
        if ($row === null) {
            throw new AuthenticationException('The API token is invalid, expired or revoked.');
        }

        $user = $this->users->findById((int) $row['user_id']);
        if ($user === null || ($user['status'] ?? 'active') !== 'active') {
            throw new AuthenticationException('The API token is invalid, expired or revoked.');
        }

        return $this->publicUser($user);
    }

    public function revoke(string $presentedToken, ?string $ipAddress = null): void
    {
        $this->tokens->revokeByHash($this->hash(trim($presentedToken)), $this->clock->now()->format('Y-m-d H:i:s'));
        $this->audit('auth.token.revoked', null, null, $ipAddress);
    }

    public function hashPassword(string $plaintext): string
    {
        return password_hash($plaintext, $this->passwordAlgorithm);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Records a security-relevant event.
     *
     * The password and the token are deliberately absent from the context: an
     * audit row must never become a second place a credential is stored. A
     * failed login records the attempted username, which is what makes
     * credential stuffing visible.
     */
    private function audit(string $event, ?int $userId, ?string $username, ?string $ipAddress): void
    {
        $this->audit?->record(
            $userId,
            $event,
            $username === null ? [] : ['username' => $username],
            $ipAddress,
            $this->clock->now()->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function publicUser(array $user): array
    {
        return [
            'id'           => (int) $user['id'],
            'username'     => (string) $user['username'],
            'display_name' => (string) $user['display_name'],
            'phone'        => $user['phone'] === null ? null : (string) $user['phone'],
            'age'          => $user['age'] === null ? null : (int) $user['age'],
            'status'       => (string) ($user['status'] ?? 'active'),
            // The role is reported so the client can hide the administrator
            // navigation. It is *not* what authorises anything: the admin
            // middleware re-reads the role from the database on every request,
            // so a player cannot reach an administrator endpoint by editing a
            // response in the browser.
            'role'         => (string) ($user['role'] ?? 'player'),
        ];
    }
}
