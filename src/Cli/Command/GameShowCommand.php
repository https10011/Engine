<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;

final class GameShowCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'games:show';
    }

    public function description(): string
    {
        return 'Show full metadata and configuration for one game.';
    }

    public function usage(): string
    {
        return 'games:show --game=<game-id>';
    }

    public function execute(Engine $engine, array $options): int
    {
        $engine->discoverGames();

        $gameId = $this->option($options, 'game');
        if ($gameId === null) {
            $this->error('--game=<game-id> is required.');

            return 1;
        }

        $game = $engine->registry()->get($gameId);

        $this->json([
            'metadata'      => $game->metadata()->toArray(),
            'configuration' => $game->defaultConfiguration()->toArray(),
        ]);

        return 0;
    }
}
