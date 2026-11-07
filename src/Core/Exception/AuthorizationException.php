<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The caller is authenticated but not allowed to perform this action. */
final class AuthorizationException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'FORBIDDEN', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
