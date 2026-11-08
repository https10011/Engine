<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

use CasinoEngine\Core\GameContext;

/**
 * The one interface a game module must implement.
 *
 * Rules that apply to every implementation:
 *
 *  - `metadata()` is deterministic and side-effect free; discovery calls it
 *    before any session exists.
 *  - `execute()` never moves money. It returns what happened in game terms and
 *    the engine settles it. A module has no access to the wallet service at
 *    all, so this is enforced by the type system, not by convention.
 *  - `execute()` obtains randomness from `$context->rng()`; calling
 *    `random_int()` directly is forbidden and is checked by the purity test.
 *  - `execute()` may throw {@see \CasinoEngine\Core\Exception\GameRuleException}
 *    for illegal play. The runtime turns that into a safe failure without
 *    touching the wallet.
 */
interface GameInterface
{
    public function metadata(): GameMetadataInterface;

    public function category(): GameCategory;

    public function defaultConfiguration(): GameConfigurationInterface;

    /**
     * @throws \CasinoEngine\Core\Exception\ConfigurationException
     */
    public function validateConfiguration(GameConfigurationInterface $configuration): void;

    /** State for a brand new session. */
    public function createInitialState(GameContext $context): GameStateInterface;

    /**
     * Rebuilds the module's typed state from a stored snapshot.
     *
     * The session service persists state as an opaque array so it never needs
     * to know what a reel or a hand is. This method is the module's half of
     * that contract: the engine hands back exactly what `toArray()` produced,
     * and the module returns its own state object.
     *
     * @param array<string, mixed> $data
     * @throws \CasinoEngine\Core\Exception\ValidationException when the snapshot
     *         is malformed or comes from an incompatible schema version
     */
    public function hydrateState(array $data): GameStateInterface;

    /**
     * @throws \CasinoEngine\Core\Exception\GameRuleException
     */
    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface;

    /**
     * @throws \CasinoEngine\Core\Exception\GameRuleException
     */
    public function validateAction(GameActionInterface $action, GameStateInterface $state): void;
}
