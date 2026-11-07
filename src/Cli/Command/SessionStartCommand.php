<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Persistence\Repository\UserRepository;

final class SessionStartCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'session:start';
    }

    public function description(): string
    {
        return 'Start a game session for a user.';
    }

    public function usage(): string
    {
        return 'session:start --user=<username> --game=<game-id>';
    }

    public function execute(Engine $engine, array $options): int
    {
        $username = $this->option($options, 'user');
        $gameId = $this->option($options, 'game');

        if ($username === null || $gameId === null) {
            throw new ValidationException('--user=<username> and --game=<game-id> are both required.');
        }

        $engine->discoverGames();

        /** @var UserRepository $users */
        $users = $engine->container()->get(UserRepository::class);
        $user = $users->findByUsername($username);
        if ($user === null) {
            throw new ValidationException("No user named '$username'.");
        }

        $game = $engine->registry()->get($gameId);
        $context = $engine->contextFor($game);
        $state = $game->createInitialState($context);

        $session = $engine->sessions()->create((int) $user['id'], $gameId, $state);

        $this->json($session->toArray());

        return 0;
    }
}
