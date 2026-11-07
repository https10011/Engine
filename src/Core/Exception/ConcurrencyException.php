<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** A concurrent modification prevented the operation from completing safely. */
final class ConcurrencyException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'CONCURRENCY_CONFLICT', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
