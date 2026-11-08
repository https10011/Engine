<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Http;

/**
 * An HTTP response with a fixed JSON envelope.
 *
 * Every response — success or failure — has the same shape:
 *
 *   {"ok": true,  "data": {...}, "error": null,      "meta": {...}}
 *   {"ok": false, "data": null,  "error": {...},     "meta": {...}}
 *
 * A client can therefore branch on `ok` without inspecting the status code,
 * while the status code still carries the HTTP-correct value for proxies,
 * logs and generic tooling.
 */
final class Response
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed>|null $error
     * @param array<string, mixed>      $meta
     * @param array<string, string>     $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly ?array $data = null,
        public readonly ?array $error = null,
        public readonly array $meta = [],
        public readonly array $headers = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function ok(array $data, int $status = 200, array $meta = []): self
    {
        return new self($status, $data, null, $meta);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function created(array $data, array $meta = []): self
    {
        return new self(201, $data, null, $meta);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    /**
     * @param array<string, mixed> $error
     * @param array<string, mixed> $meta
     */
    public static function error(int $status, array $error, array $meta = []): self
    {
        return new self($status, null, $error, $meta);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->data, $this->error, $this->meta, $headers + $this->headers);
    }

    /** @return array<string, mixed> */
    public function envelope(): array
    {
        return [
            'ok'    => $this->error === null,
            'data'  => $this->data,
            'error' => $this->error,
            'meta'  => (object) $this->meta,
        ];
    }

    public function body(): string
    {
        if ($this->status === 204) {
            return '';
        }

        return (string) json_encode(
            $this->envelope(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Emits the response to the SAPI.
     *
     * Only called from the front controller; tests use {@see body()} and
     * {@see envelope()} so nothing is ever echoed during a test run.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: no-referrer');
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body();
    }
}
