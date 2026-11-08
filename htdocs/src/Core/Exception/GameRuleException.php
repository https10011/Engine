<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** The action is illegal for the current game state. */
final class GameRuleException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'GAME_RULE_VIOLATION', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
