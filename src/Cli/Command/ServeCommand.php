<?php

declare(strict_types=1);

namespace CasinoEngine\Cli\Command;

use CasinoEngine\Core\Engine;

/**
 * Starts PHP's built-in server pointed at `public/`.
 *
 * The production front controller is `public/index.php`, which is the only file
 * that should ever be reachable. This command exists so a developer can exercise
 * the HTTP API locally without installing Apache or Nginx; it is a development
 * tool and is never the production entry point.
 */
final class ServeCommand extends AbstractCommand
{
    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Run a local development HTTP server for the API (development only).';
    }

    public function usage(): string
    {
        return 'serve [--host=127.0.0.1] [--port=8000]';
    }

    public function execute(Engine $engine, array $options): int
    {
        // PHP's built-in server is single-threaded and not a production front
        // end. Refusing outright stops it being left running by accident on a
        // host that sets APP_ENV correctly.
        if ($engine->config()->environment() === 'production') {
            $this->error('The development server must not run in production. Use a real web server.');

            return 1;
        }

        $host = $this->option($options, 'host', '127.0.0.1') ?? '127.0.0.1';
        $port = $this->intOption($options, 'port', 8000);

        if ($port < 1 || $port > 65535) {
            $this->error("Invalid port $port; expected 1-65535.");

            return 1;
        }

        $documentRoot = \dirname(__DIR__, 3) . '/public';
        $router = $documentRoot . '/index.php';

        if (!is_file($router)) {
            $this->error("Front controller not found at $router.");

            return 1;
        }

        $bound = sprintf('%s:%d', $host, $port);
        $this->line("");
        $this->line("Development server: http://$bound");
        $this->line("Document root:      $documentRoot");
        $this->line("API base:           http://$bound/api/v1");
        $this->line('');
        $this->line('This is a development server. Production uses a real web server');
        $this->line('with its document root set to public/ (see DEPLOYMENT_GUIDE.md).');
        $this->line('');
        $this->line('Press Ctrl+C to stop.');
        $this->line('');

        // Escaped for the shell, because the path is printed and executed.
        $command = sprintf(
            '%s -S %s -t %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($bound),
            escapeshellarg($documentRoot),
            escapeshellarg($router)
        );

        passthru($command, $exitCode);

        return (int) $exitCode;
    }
}
