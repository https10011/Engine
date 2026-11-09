<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Services\Auth\AuthServiceInterface;

/**
 * Replaces an existing account's password.
 *
 * This is the supported way to rotate the seeded administrator password without
 * ever storing plaintext in the repository or the database. The password is
 * hashed with the deployment's configured algorithm, exactly like a login-time
 * registration.
 */
final class UserPasswordCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'user:password';
    }

    public function description(): string
    {
        return 'Set a new password for an existing account (including the administrator).';
    }

    public function usage(): string
    {
        return 'user:password --username=<name> --password=<new-secret>';
    }

    public function execute(Engine $engine, array $options): int
    {
        $username = $this->option($options, 'username');
        if ($username === null || trim($username) === '') {
            throw new ValidationException('--username=<name> is required.');
        }

        $password = $this->option($options, 'password');
        if ($password === null || strlen($password) < 10 || strlen($password) > 128) {
            throw new ValidationException('--password must be between 10 and 128 characters.');
        }

        /** @var UserRepository $users */
        $users = $engine->container()->get(UserRepository::class);
        $user = $users->findByUsername(trim($username));
        if ($user === null) {
            throw new ValidationException("No user named '$username' exists.");
        }

        /** @var AuthServiceInterface $auth */
        $auth = $engine->container()->get(AuthServiceInterface::class);
        $users->setCredentials(
            (int) $user['id'],
            $auth->hashPassword($password),
            $auth->passwordAlgo(),
            $engine->clock()->now()->format('Y-m-d H:i:s')
        );

        $this->line(sprintf("Password updated for '%s'.", (string) $user['username']));

        return 0;
    }
}
