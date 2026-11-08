<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Discovery;

use CasinoEngine\Core\Contract\GameFactoryInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Finds and registers game modules.
 *
 * Discovery is driven entirely by configuration: the engine scans configured
 * namespaces for classes that implement {@see GameInterface} or
 * {@see GameFactoryInterface}. Adding game #19 therefore means adding a class
 * to a discovered namespace — never editing the core.
 *
 * Scanning uses Composer's class map plus a PSR-4 directory walk. Both are
 * read-only operations on already-installed code; nothing is downloaded or
 * executed during discovery.
 */
final class GameDiscovery
{
    /**
     * @param list<string> $namespaces PSR-4 prefixes to scan, e.g. `CasinoEngine\Games`.
     * @param array<string, string> $namespacePaths prefix => absolute directory.
     */
    public function __construct(
        private readonly GameRegistryInterface $registry,
        private readonly array $namespaces,
        private readonly array $namespacePaths,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Registers every discoverable module.
     *
     * @param callable(GameInterface|null): GameContext|null $contextFactory
     *        Builds a context for a module; during discovery it is called with
     *        null, because the module does not exist yet and the placeholder
     *        context only has to carry metadata and configuration. When null,
     *        factories are skipped and only direct {@see GameInterface}
     *        implementations are registered.
     *
     * @return list<string> ids registered by this call
     */
    public function discover(?callable $contextFactory = null): array
    {
        $registered = [];

        foreach ($this->candidateClasses() as $className) {
            try {
                $game = $this->instantiate($className, $contextFactory);
            } catch (Throwable $e) {
                // One broken module must not stop the engine from booting, but
                // it must be visible.
                $this->logger?->error('Game module could not be loaded', [
                    'class'     => $className,
                    'exception' => $e::class,
                    'message'   => $e->getMessage(),
                ]);
                continue;
            }

            if ($game === null) {
                continue;
            }

            try {
                $this->registry->register($game);
                $registered[] = $game->metadata()->id();
            } catch (ConfigurationException $e) {
                $this->logger?->warning('Game module rejected by the registry', [
                    'class'   => $className,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $registered;
    }

    /** @return list<class-string> */
    private function candidateClasses(): array
    {
        $classes = [];

        foreach ($this->namespaces as $namespace) {
            $prefix = rtrim($namespace, '\\') . '\\';
            $baseDir = $this->namespacePaths[$prefix] ?? null;

            if ($baseDir === null || !is_dir($baseDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php' || !$file->isFile()) {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($baseDir));
                $class = $prefix . str_replace('/', '\\', trim($relative, '/\\'));
                $class = substr($class, 0, -4); // strip .php

                if (!class_exists($class)) {
                    continue;
                }

                $classes[$class] = true;
            }
        }

        return array_keys($classes);
    }

    private function instantiate(string $className, ?callable $contextFactory): ?GameInterface
    {
        $reflection = new \ReflectionClass($className);

        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return null;
        }

        if ($reflection->implementsInterface(GameInterface::class)) {
            /** @var GameInterface $instance */
            $instance = $reflection->newInstance();

            return $instance;
        }

        if ($reflection->implementsInterface(GameFactoryInterface::class)) {
            if ($contextFactory === null) {
                return null;
            }

            /** @var GameFactoryInterface $factory */
            $factory = $reflection->newInstance();

            // A placeholder context is enough: a factory only needs metadata
            // and configuration to build its module, and it is given the real
            // context again at session time.
            return $factory->create($contextFactory(null));
        }

        return null;
    }
}
