<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Services\Wallet\WalletServiceInterface;

final class WalletShowCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'wallet:show';
    }

    public function description(): string
    {
        return 'Show a user\'s virtual balance and recent transactions.';
    }

    public function usage(): string
    {
        return 'wallet:show --user=<username> [--history=10]';
    }

    public function execute(Engine $engine, array $options): int
    {
        $username = $this->option($options, 'user');
        if ($username === null) {
            throw new ValidationException('--user=<username> is required.');
        }

        /** @var UserRepository $users */
        $users = $engine->container()->get(UserRepository::class);
        $user = $users->findByUsername($username);
        if ($user === null) {
            throw new ValidationException("No user named '$username'.");
        }

        /** @var WalletServiceInterface $wallet */
        $wallet = $engine->container()->get(WalletServiceInterface::class);

        $this->json([
            'user'    => $user['username'],
            'balance' => $wallet->balance((int) $user['id'])->jsonSerialize(),
            'history' => $engine->history()->walletHistory((int) $user['id'], $this->intOption($options, 'history', 10)),
        ]);

        return 0;
    }
}
