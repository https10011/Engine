<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

enum SessionStatus: string
{
    case Active  = 'active';
    case Closed  = 'closed';
    case Expired = 'expired';

    public function isPlayable(): bool
    {
        return $this === self::Active;
    }
}
