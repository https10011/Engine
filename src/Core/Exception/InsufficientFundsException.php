<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The wallet balance cannot cover the requested wager. */
final class InsufficientFundsException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'INSUFFICIENT_FUNDS', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
