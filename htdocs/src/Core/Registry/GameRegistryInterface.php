<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Registry;

use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;

/**
 * The catalogue of registered games.
 *
 * The registry stores instances, not class names, and knows nothing about what
 * any game does. It is the only place a game id is resolved to a module.
 */
interface GameRegistryInterface
{
    /**
     * @throws \CasinoEngine\Core\Exception\ConfigurationException on a
     *         duplicate id or a module whose metadata is unusable.
     */
    public function register(GameInterface $game): void;

    /**
     * @throws \CasinoEngine\Core\Exception\GameNotFoundException
     */
    public function get(string $gameId): GameInterface;

    public function has(string $gameId): bool;

    /** @return list<GameMetadataInterface> */
    public function all(bool $enabledOnly = true): array;

    /** @return list<GameMetadataInterface> */
    public function byCategory(GameCategory $category, bool $enabledOnly = true): array;

    /** @return list<string> */
    public function ids(bool $enabledOnly = true): array;

    public function count(): int;
}
