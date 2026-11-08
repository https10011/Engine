<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** A database operation failed. */
final class PersistenceException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'PERSISTENCE_ERROR', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
