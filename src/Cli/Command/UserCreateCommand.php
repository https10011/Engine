<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Persistence\Repository\UserRepository;
use CasinoEngine\Persistence\Repository\WalletRepository;
use CasinoEngine\Services\Wallet\WalletServiceInterface;

final class UserCreateCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'user:create';
    }

    public function description(): string
    {
        return 'Create a local user with a virtual wallet.';
    }

    public function usage(): string
    {
        return 'user:create --username=<name> [--display="Name"] [--balance=1000.00]';
    }

    public function execute(Engine $engine, array $options): int
    {
        $username = $this->option($options, 'username');
        if ($username === null) {
            throw new ValidationException('--username=<name> is required.');
        }

        if (preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username) !== 1) {
            throw new ValidationException('Username must be 3-64 characters of letters, digits, dot, dash or underscore.');
        }

        /** @var UserRepository $users */
        $users = $engine->container()->get(UserRepository::class);

        if ($users->findByUsername($username) !== null) {
            throw new ValidationException("A user named '$username' already exists.");
        }

        $display = $this->option($options, 'display', $username) ?? $username;
        $currency = (string) $engine->config()->get('engine.currency', 'PHP');
        $scale = (int) $engine->config()->get('betting.currency_minor_units', 2);
        $now = $engine->clock()->now()->format('Y-m-d H:i:s');

        $userId = $users->create($username, $display, $now);

        /** @var WalletRepository $wallets */
        $wallets = $engine->container()->get(WalletRepository::class);

        $balanceOption = $this->option($options, 'balance', '0');
        $balance = Money::fromDecimalString((string) $balanceOption, $currency, $scale);

        $wallets->createForUser($userId, $currency, $scale, $balance->minorUnits(), $now);

        $this->line(sprintf('Created user #%d (%s) with a balance of %s.', $userId, $username, $balance->format()));

        return 0;
    }
}
