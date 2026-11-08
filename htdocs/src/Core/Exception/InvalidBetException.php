<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The bet is outside the game's allowed range or step. */
final class InvalidBetException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'INVALID_BET', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
