<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The caller is not authenticated. */
final class AuthenticationException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'UNAUTHENTICATED', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
