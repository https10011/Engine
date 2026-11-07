<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Http;

use CasinoEngine\Core\Exception\AuthenticationException;

/**
 * Per-request state shared between middleware and controllers.
 *
 * A {@see Request} is immutable, so anything middleware learns (the
 * authenticated user, the loaded session) is attached here instead of being
 * smuggled back into the request. One context exists per dispatch, so nothing
 * leaks between requests in a long-lived worker.
 */
final class RequestContext
{
    /** @var array<string, mixed>|null */
    private ?array $user = null;

    /** @var array<string, mixed>|null */
    private ?array $session = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @param array<string, mixed> $user */
    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * The authenticated user, or an exception if authentication was skipped.
     *
     * @return array<string, mixed>
     */
    public function user(): array
    {
        if ($this->user === null) {
            throw new AuthenticationException('This endpoint requires authentication.');
        }

        return $this->user;
    }

    public function userId(): int
    {
        return (int) ($this->user()['id'] ?? 0);
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /** @param array<string, mixed> $session */
    public function setSession(array $session): void
    {
        $this->session = $session;
    }

    /** @return array<string, mixed> */
    public function session(): array
    {
        if ($this->session === null) {
            throw new AuthenticationException('No session is loaded for this request.');
        }

        return $this->session;
    }

    /** @param mixed $value */
    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
