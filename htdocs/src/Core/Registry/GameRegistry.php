<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Registry;

use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameNotFoundException;
use CasinoEngine\Core\Event\EngineEvents;
use CasinoEngine\Core\Event\EventDispatcherInterface;

final class GameRegistry implements GameRegistryInterface
{
    /** @var array<string, GameInterface> */
    private array $games = [];

    /**
     * @param string $minSdkVersion Modules declaring an SDK contract older than
     *        this are refused at registration, so the engine never runs a module
     *        written against a contract it no longer honours.
     */
    public function __construct(
        private readonly ?EventDispatcherInterface $events = null,
        private readonly string $minSdkVersion = '1.0.0'
    ) {
    }

    public function register(GameInterface $game): void
    {
        $metadata = $game->metadata();
        $id = $metadata->id();

        if (isset($this->games[$id])) {
            throw new ConfigurationException(
                "A game with id '$id' is already registered.",
                ['game_id' => $id]
            );
        }

        // Validate the module's declared category against what it reports, so a
        // mismatch is caught at boot rather than at play time.
        if ($game->category() !== $metadata->category()) {
            throw new ConfigurationException(
                "Game '$id' reports category '{$game->category()->value}' but its metadata says '{$metadata->category()->value}'.",
                ['game_id' => $id]
            );
        }

        // A module written against an older SDK contract than the engine still
        // supports is refused here rather than failing in some subtle way at
        // play time.
        if (version_compare($metadata->sdkVersion(), $this->minSdkVersion, '<')) {
            throw new ConfigurationException(
                "Game '$id' targets SDK contract {$metadata->sdkVersion()}, "
                . "but this engine requires at least {$this->minSdkVersion}.",
                ['game_id' => $id, 'sdk_version' => $metadata->sdkVersion()]
            );
        }

        // Force configuration validation now: a module with an unusable default
        // configuration must not reach the registry at all.
        $game->validateConfiguration($game->defaultConfiguration());

        $this->games[$id] = $game;

        $this->events?->dispatch(EngineEvents::GAME_REGISTERED, [
            'game_id'  => $id,
            'category' => $metadata->category()->value,
            'version'  => $metadata->version(),
        ]);
    }

    public function get(string $gameId): GameInterface
    {
        if (!isset($this->games[$gameId])) {
            throw new GameNotFoundException(
                "Game '$gameId' is not registered.",
                ['game_id' => $gameId]
            );
        }

        return $this->games[$gameId];
    }

    public function has(string $gameId): bool
    {
        return isset($this->games[$gameId]);
    }

    public function all(bool $enabledOnly = true): array
    {
        $metadata = array_map(
            static fn (GameInterface $game): GameMetadataInterface => $game->metadata(),
            array_values($this->games)
        );

        if ($enabledOnly) {
            $metadata = array_values(array_filter(
                $metadata,
                static fn (GameMetadataInterface $m): bool => $m->isEnabled()
            ));
        }

        usort($metadata, static fn (GameMetadataInterface $a, GameMetadataInterface $b): int
            => [$a->category()->value, $a->name()] <=> [$b->category()->value, $b->name()]);

        return $metadata;
    }

    public function byCategory(GameCategory $category, bool $enabledOnly = true): array
    {
        return array_values(array_filter(
            $this->all($enabledOnly),
            static fn (GameMetadataInterface $m): bool => $m->category() === $category
        ));
    }

    public function ids(bool $enabledOnly = true): array
    {
        return array_map(
            static fn (GameMetadataInterface $m): string => $m->id(),
            $this->all($enabledOnly)
        );
    }

    public function count(): int
    {
        return count($this->games);
    }
}
