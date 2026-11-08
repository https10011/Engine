<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Persistence\Repository\UserRepository;

/**
 * Grants or revokes the administrator role.
 *
 * This is the only supported way to create an administrator. There is no
 * endpoint that promotes an account, because a self-service promotion path is
 * exactly the hole an attacker would use; an operator with shell access is the
 * intended actor.
 */
final class UserAdminCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'user:admin';
    }

    public function description(): string
    {
        return 'Grant or revoke the administrator role for a user.';
    }

    public function usage(): string
    {
        return 'user:admin --username=<name> [--revoke]';
    }

    public function execute(Engine $engine, array $options): int
    {
        $username = $this->option($options, 'username');
        if ($username === null || trim($username) === '') {
            throw new ValidationException('--username=<name> is required.');
        }

        /** @var UserRepository $users */
        $users = $engine->container()->get(UserRepository::class);

        $user = $users->findByUsername(trim($username));
        if ($user === null) {
            throw new ValidationException("No user named '$username' exists.");
        }

        $revoke = $this->boolOption($options, 'revoke');
        $role = $revoke ? 'player' : 'admin';

        $users->setRole((int) $user['id'], $role, $engine->clock()->now()->format('Y-m-d H:i:s'));

        $this->line(sprintf(
            "User '%s' is now %s.",
            (string) $user['username'],
            $revoke ? 'a player' : 'an administrator'
        ));

        return 0;
    }
}
