<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

/**
 * Game-specific result payload. The engine stores it verbatim and the API
 * returns it so a frontend can animate exactly what the server decided.
 */
interface GameOutcomeDetailInterface
{
    public function type(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
