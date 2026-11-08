<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** An unexpected engine failure occurred. */
final class EngineRuntimeException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'ENGINE_RUNTIME_ERROR', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
