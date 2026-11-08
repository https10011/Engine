<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The requested game session does not exist. */
final class SessionNotFoundException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'SESSION_NOT_FOUND', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
