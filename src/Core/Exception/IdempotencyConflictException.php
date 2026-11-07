<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The idempotency key was reused with a different request payload. */
final class IdempotencyConflictException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'IDEMPOTENCY_CONFLICT', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
