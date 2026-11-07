<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** Client-supplied input failed validation. */
final class ValidationException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'VALIDATION_ERROR', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
