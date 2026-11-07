<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Engine;

final class GameListCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'games:list';
    }

    public function description(): string
    {
        return 'List every registered game and its metadata.';
    }

    public function usage(): string
    {
        return 'games:list [--category=slot|card|roulette] [--json]';
    }

    public function execute(Engine $engine, array $options): int
    {
        $engine->discoverGames();

        $categoryOption = $this->option($options, 'category');
        $games = $categoryOption === null
            ? $engine->registry()->all(false)
            : $engine->registry()->byCategory(GameCategory::from($categoryOption), false);

        if ($this->boolOption($options, 'json')) {
            $this->json(array_map(static fn ($m) => $m->toArray(), $games));

            return 0;
        }

        if ($games === []) {
            $this->line('No games are registered.');

            return 0;
        }

        $this->line(sprintf('%-28s %-9s %-12s %-14s %s', 'ID', 'CATEGORY', 'VERSION', 'VOLATILITY', 'NAME'));
        $this->line(str_repeat('-', 96));

        foreach ($games as $metadata) {
            $this->line(sprintf(
                '%-28s %-9s %-12s %-14s %s',
                $metadata->id(),
                $metadata->category()->value,
                $metadata->version(),
                $metadata->volatility()->value,
                $metadata->name()
            ));
        }

        $this->line('');
        $this->line(sprintf('%d game(s).', count($games)));

        return 0;
    }
}
