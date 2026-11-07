<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Auth;

/**
 * Authentication boundary for the HTTP API.
 *
 * Kept as an interface so the API layer depends on the capability rather than
 * the implementation, and so a future SSO or MFA provider can be substituted
 * without touching a controller.
 */
interface AuthServiceInterface
{
    /**
     * Verifies credentials and returns the user plus a fresh token.
     *
     * @return array{user:array<string,mixed>,token:array{token:string,expires_at:string}}
     * @throws \CasinoEngine\Core\Exception\AuthenticationException
     */
    public function login(string $username, string $password, ?string $ipAddress = null): array;

    /**
     * Issues a token for an already-authenticated user.
     *
     * @return array{token:string,expires_at:string}
     */
    public function issueToken(int $userId): array;

    /**
     * Resolves a presented bearer token to its user, or throws.
     *
     * @return array<string, mixed>
     * @throws \CasinoEngine\Core\Exception\AuthenticationException
     */
    public function authenticate(string $presentedToken): array;

    public function revoke(string $presentedToken, ?string $ipAddress = null): void;

    /** Produces a password hash suitable for storage. */
    public function hashPassword(string $plaintext): string;

    /** The configured algorithm name, as recorded in `user_credentials.algo`. */
    public function passwordAlgo(): string;
}
