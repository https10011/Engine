<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

use CasinoEngine\Core\GameContext;

/**
 * Builds a game module.
 *
 * Discovery instantiates factories, not games, so a module can receive
 * whatever collaborators it needs without the registry knowing anything about
 * them. A game with no dependencies can return a shared instance.
 */
interface GameFactoryInterface
{
    public function create(GameContext $context): GameInterface;
}
