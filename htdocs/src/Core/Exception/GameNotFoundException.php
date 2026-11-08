<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The requested game is not registered. */
final class GameNotFoundException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'GAME_NOT_FOUND', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
