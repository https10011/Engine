<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class for every error the engine raises deliberately.
 *
 * The contract is that `getMessage()` is always safe to show a client: it
 * never contains SQL, file paths, stack frames or credentials. Anything an
 * operator needs for debugging goes into {@see context()} and is only written
 * to the log.
 */
class EngineException extends RuntimeException
{
    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param array<string, mixed> $context Non-public debugging detail.
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = 'ENGINE_ERROR',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    /** Stable machine-readable code, e.g. `INSUFFICIENT_FUNDS`. */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    /** HTTP status this error maps to when it reaches the API layer. */
    public function httpStatus(): int
    {
        return 500;
    }
}
