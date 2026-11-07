<?php

declare(strict_types=1);

namespace CasinoEngine\Cli;

use CasinoEngine\Cli\Command\CommandInterface;
use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\EngineException;
use Throwable;

/**
 * Minimal command-line application.
 *
 * Deliberately not a framework: a shared host does not need one, and the
 * engine must be runnable with nothing but `php bin/console`. Commands are
 * registered explicitly so `--help` lists exactly what exists.
 */
final class Application
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    public function __construct(private readonly Engine $engine)
    {
    }

    public function register(CommandInterface $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /** @param list<CommandInterface> $commands */
    public function registerAll(array $commands): void
    {
        foreach ($commands as $command) {
            $this->register($command);
        }
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $args = array_slice($argv, 1);
        $commandName = null;
        $options = [];

        foreach ($args as $index => $arg) {
            if ($index === 0 && !str_starts_with($arg, '-')) {
                $commandName = $arg;
                continue;
            }

            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
                continue;
            }

            if (str_starts_with($arg, '-')) {
                $options[substr($arg, 1)] = true;
            }
        }

        if ($commandName === null || isset($options['help']) || $commandName === 'help') {
            $this->printHelp($commandName);

            return $commandName === null ? 0 : 0;
        }

        if (!isset($this->commands[$commandName])) {
            $this->error("Unknown command '$commandName'.");
            $this->printHelp(null);

            return 1;
        }

        $command = $this->commands[$commandName];

        try {
            return $command->execute($this->engine, $options);
        } catch (EngineException $e) {
            $this->error($e->getMessage());

            // Non-public detail goes to the log, not to the console.
            $this->engine->logger()->error('Command failed', [
                'command'   => $commandName,
                'error_code' => $e->errorCode(),
                'context'   => $e->context(),
            ]);

            return 1;
        } catch (Throwable $e) {
            $this->error('An unexpected error occurred. See the log for details.');
            $this->engine->logger()->error('Unhandled command error', [
                'command'   => $commandName,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);

            return 1;
        }
    }

    private function printHelp(?string $commandName): void
    {
        if ($commandName !== null && isset($this->commands[$commandName])) {
            $command = $this->commands[$commandName];
            $this->write($command->name() . ' — ' . $command->description());
            $this->write('');
            $this->write('Usage: php bin/console ' . $command->usage());

            return;
        }

        $this->write('Universal Casino Game Engine');
        $this->write('');
        $this->write('Usage: php bin/console <command> [--option=value]');
        $this->write('');
        $this->write('Commands:');

        $names = array_keys($this->commands);
        sort($names);
        foreach ($names as $name) {
            $this->write(sprintf('  %-22s %s', $name, $this->commands[$name]->description()));
        }
    }

    private function write(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    private function error(string $message): void
    {
        fwrite(STDERR, 'Error: ' . $message . PHP_EOL);
    }
}
