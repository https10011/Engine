<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Exception;

/** Configuration is missing, malformed or inconsistent. */
final class ConfigurationException extends EngineException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'CONFIGURATION_ERROR', $context, $previous);
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
