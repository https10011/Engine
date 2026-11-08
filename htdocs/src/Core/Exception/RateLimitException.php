<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** Too many requests. */
final class RateLimitException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'RATE_LIMIT_EXCEEDED', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 429;
    }
}
