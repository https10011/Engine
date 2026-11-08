<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

/**
 * Published volatility band. Declared by the module's metadata so listings and
 * documentation can present it; the engine never derives behaviour from it.
 */
enum Volatility: string
{
    case Low        = 'low';
    case LowMedium  = 'low-medium';
    case Medium     = 'medium';
    case HighMedium = 'high-medium';
    case High       = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low        => 'Low',
            self::LowMedium  => 'Low-Medium',
            self::Medium     => 'Medium',
            self::HighMedium => 'High-Medium',
            self::High       => 'High',
        };
    }
}
