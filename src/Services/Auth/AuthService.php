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
    /** Recognised values of `security.password_algo`, mapped to PHP's constants. */
    private const PASSWORD_ALGORITHMS = [
        'argon2id' => \PASSWORD_ARGON2ID,
        'bcrypt'   => \PASSWORD_BCRYPT,
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly ClockInterface $clock,
        private readonly int $tokenTtlSeconds = 86400,
        private readonly ?AuditRepository $audit = null,
        private readonly string $passwordAlgo = 'argon2id'
    ) {
        if (!isset(self::PASSWORD_ALGORITHMS[$this->passwordAlgo])) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                "Unknown password algorithm '{$this->passwordAlgo}'; expected one of "
                . implode(', ', array_keys(self::PASSWORD_ALGORITHMS)) . '.'
            );
        }
    }

    /** The algorithm name, as stored in `user_credentials.algo`. */
    public function passwordAlgo(): string
    {
        return $this->passwordAlgo;
    }

    public function login(string $username, string $password, ?string $ipAddress = null): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new AuthenticationException('Username and password are required.');
        }

        $user = $this->users->findByUsername($username);
        if ($user === null || ($user['status'] ?? 'active') !== 'active') {
            // A dummy verify keeps the timing of an unknown user close to that
            // of a known one with a wrong password.
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$AAAAAAAAAAAAAAAAAAAAAA$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
            $this->audit('auth.login.failed', null, $username, $ipAddress);
            throw new AuthenticationException('Invalid username or password.');
        }

        $credentials = $this->users->findCredentials((int) $user['id']);
        if ($credentials === null || !password_verify($password, (string) $credentials['password_hash'])) {
            $this->audit('auth.login.failed', (int) $user['id'], $username, $ipAddress);
            throw new AuthenticationException('Invalid username or password.');
        }

        $this->audit('auth.login.succeeded', (int) $user['id'], $username, $ipAddress);

        return [
            'user'  => $this->publicUser($user),
            'token' => $this->issueToken((int) $user['id']),
        ];
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
        return password_hash($plaintext, self::PASSWORD_ALGORITHMS[$this->passwordAlgo]);
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
        ];
    }
}
