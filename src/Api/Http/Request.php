<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Http;

use CasinoEngine\Core\Exception\ValidationException;

/**
 * An immutable HTTP request.
 *
 * The body is parsed once here and never re-read, so a controller can be sure
 * the JSON it sees is the JSON that was validated. `json()` returns only an
 * object-shaped body, which stops a controller accidentally treating a scalar
 * or a list as a field map.
 */
final class Request
{
    /**
     * @param array<string, string> $headers lower-cased header names
     * @param array<string, mixed>  $query
     * @param array<string, mixed>|null $body decoded JSON object, or null
     * @param array<string, string> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $headers = [],
        private readonly array $query = [],
        private readonly ?array $body = null,
        private readonly array $server = []
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** The bearer token from the Authorization header, if present. */
    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');
        if ($header === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        return $this->body ?? [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * The request body as a required string field.
     *
     * @throws ValidationException
     */
    public function requireString(string $key): string
    {
        $value = $this->input($key);
        if (!is_string($value) || trim($value) === '') {
            throw new ValidationException("The field '$key' is required and must be a non-empty string.");
        }

        return $value;
    }

    public function clientIp(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->header('user-agent') ?? '');
    }

    public function contentType(): string
    {
        return (string) ($this->header('content-type') ?? '');
    }

    /**
     * Builds a request from the PHP superglobals.
     *
     * Kept separate from the constructor so tests can construct a request
     * directly and never touch global state.
     */
    public static function fromGlobals(): self
    {
        /** @var array<string, mixed> $server */
        $server = $_SERVER;

        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $server['CONTENT_TYPE'];
        }

        /** @var array<string, mixed> $query */
        $query = $_GET;
        $body = self::decodeBody();

        return new self(
            strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')),
            (string) parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH),
            $headers,
            $query,
            $body,
            array_map('strval', $server)
        );
    }

    /** @return array<string, mixed>|null */
    private static function decodeBody(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
