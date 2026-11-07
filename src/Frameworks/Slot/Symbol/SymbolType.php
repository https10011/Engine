<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Symbol;

/**
 * How a symbol behaves during evaluation.
 *
 * `Regular` is paid on a matching run. `Wild` substitutes for regular symbols
 * but never for a scatter or a bonus. `Scatter` pays on count anywhere in the
 * grid and is not substituted by a wild. `Bonus` triggers a feature and pays
 * nothing by itself. `Blank` is a decorative gap that never wins.
 */
enum SymbolType: string
{
    case Regular = 'regular';
    case Wild    = 'wild';
    case Scatter = 'scatter';
    case Bonus   = 'bonus';
    case Blank   = 'blank';

    public function substitutes(): bool
    {
        return $this === self::Wild;
    }

    public function isPayable(): bool
    {
        return $this === self::Regular || $this === self::Wild || $this === self::Scatter;
    }
}
