<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Unit\Sdk;

use CasinoEngine\Tests\Support\TestCase;

/**
 * Enforces the purity invariant that the whole engine rests on.
 *
 * `GameInterface` promises three things about a module: it takes randomness
 * only from the injected RNG, it never touches the wallet or the database, and
 * it never decides currency amounts. Those promises are what make it safe to
 * load a module from a discovered namespace without auditing its source.
 *
 * A convention in a docblock is not an enforcement, so this test reads the
 * shipped module source and fails if a module reaches for a forbidden symbol.
 * It is intentionally a source scan rather than a runtime check: a forbidden
 * call is forbidden whether or not the branch that contains it executes during
 * the tests.
 */
final class GameModulePurityTest extends TestCase
{
    /** Calls a module must never make, and why. */
    private const FORBIDDEN = [
        'random_int'   => 'authoritative randomness must come from $context->rng()',
        'random_bytes' => 'authoritative randomness must come from $context->rng()',
        'mt_rand'      => 'authoritative randomness must come from $context->rng()',
        'rand'         => 'authoritative randomness must come from $context->rng()',
        'uniqid'       => 'authoritative randomness must come from $context->rng()',
    ];

    /** Namespaces a module must never import, and why. */
    private const FORBIDDEN_IMPORTS = [
        'CasinoEngine\\Persistence\\' => 'a module must not reach the database directly',
        'CasinoEngine\\Services\\Wallet' => 'a module must not move money itself',
        'CasinoEngine\\Services\\Transaction' => 'a module must not manage transactions itself',
    ];

    /** @return list<string> */
    private function moduleFiles(): array
    {
        $dir = \dirname(__DIR__, 3) . '/src/Games';
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function testThereIsAtLeastOneModuleToCheck(): void
    {
        // A test that silently checks nothing is worse than no test, because it
        // reports success. If discovery paths move, this must fail loudly.
        self::assertNotEmpty($this->moduleFiles(), 'No game modules were found to check.');
    }

    public function testNoModuleDrawsRandomnessOutsideTheInjectedRng(): void
    {
        foreach ($this->moduleFiles() as $file) {
            $source = $this->stripComments((string) file_get_contents($file));

            foreach (self::FORBIDDEN as $function => $reason) {
                self::assertSame(
                    0,
                    preg_match('/\b' . preg_quote($function, '/') . '\s*\(/', $source),
                    sprintf('%s calls %s(); %s.', $this->shortName($file), $function, $reason)
                );
            }
        }
    }

    public function testNoModuleImportsPersistenceOrWalletServices(): void
    {
        foreach ($this->moduleFiles() as $file) {
            $source = $this->stripComments((string) file_get_contents($file));

            foreach (self::FORBIDDEN_IMPORTS as $namespace => $reason) {
                self::assertSame(
                    0,
                    preg_match('/\buse\s+' . preg_quote($namespace, '/') . '/', $source),
                    sprintf('%s imports %s: %s.', $this->shortName($file), $namespace, $reason)
                );
            }
        }
    }

    public function testNoModuleConstructsCurrencyAmountsAsAPayout(): void
    {
        // A module may describe a bet or a limit in Money. It must not build the
        // amount it is paid, because that is the engine's job and a module that
        // does it can invent a balance. The signal is Money::of() appearing in
        // the same file as a payout multiplier: legitimate uses name a bet or a
        // metadata limit, which this heuristic accepts by only flagging the
        // combination inside execute().
        foreach ($this->moduleFiles() as $file) {
            $source = (string) file_get_contents($file);
            if (!preg_match('/function execute\s*\(/', $source)) {
                continue;
            }

            $body = $this->methodBody($this->stripComments($source), 'execute');

            self::assertSame(
                0,
                preg_match('/\bMoney::of\s*\(/', $body),
                sprintf(
                    '%s constructs a Money amount inside execute(); an outcome must return ratios, not amounts.',
                    $this->shortName($file)
                )
            );
        }
    }

    /** Removes comments and strings so a mention in prose is not a false hit. */
    private function stripComments(string $source): string
    {
        $tokens = token_get_all($source);
        $output = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) {
                    continue;
                }
                $output .= $token[1];
                continue;
            }

            $output .= $token;
        }

        return $output;
    }

    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, 'function ' . $method . '(');
        if ($start === false) {
            return '';
        }

        $open = strpos($source, '{', $start);
        if ($open === false) {
            return '';
        }

        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $open, $i - $open + 1);
                }
            }
        }

        return substr($source, $open);
    }

    private function shortName(string $file): string
    {
        return basename($file);
    }
}
