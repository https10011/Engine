<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Reel;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;

/**
 * One physical reel as an ordered strip of symbol ids.
 *
 * This is the classic slot model: the reel is a cycle and a spin lands on a
 * position in it. Modelling the strip explicitly (rather than picking symbols
 * independently per cell) is what makes symbol clustering on adjacent rows
 * emerge naturally, and it is the only way to reproduce the exact symbol
 * distribution of a real reel.
 *
 * `fromWeights()` builds a strip from per-symbol weights when a game publishes
 * weights rather than a strip. The result is a strip whose symbol *counts*
 * match the weights, so it is the same model afterwards.
 */
final class ReelStrip
{
    /** @param list<string> $symbols */
    private function __construct(private readonly array $symbols)
    {
    }

    /** @param list<string> $symbols */
    public static function fromSymbols(array $symbols): self
    {
        if ($symbols === []) {
            throw new ConfigurationException('A reel strip cannot be empty.');
        }

        return new self(array_values($symbols));
    }

    /**
     * Builds a strip where symbol `i` appears `weights[i]` times, then shuffles
     * the strip so identical symbols are not adjacent in a fixed pattern.
     *
     * @param array<string, int> $weights symbol id => count
     */
    public static function fromWeights(array $weights, RngServiceInterface $rng, ?SymbolSet $symbols = null): self
    {
        if ($weights === []) {
            throw new ConfigurationException('Reel weights cannot be empty.');
        }

        $strip = [];
        foreach ($weights as $symbolId => $weight) {
            if ($weight < 0) {
                throw new ConfigurationException("Reel weight for '$symbolId' cannot be negative.");
            }
            if ($symbols !== null && !$symbols->has($symbolId)) {
                throw new ConfigurationException("Reel weight references unknown symbol '$symbolId'.");
            }
            for ($i = 0; $i < $weight; $i++) {
                $strip[] = $symbolId;
            }
        }

        if ($strip === []) {
            throw new ConfigurationException('Reel weights produced an empty strip.');
        }

        return new self($rng->shuffle($strip));
    }

    /** @return list<string> */
    public function symbols(): array
    {
        return $this->symbols;
    }

    public function length(): int
    {
        return count($this->symbols);
    }

    /**
     * Returns `$count` consecutive symbols starting at `$position`, wrapping.
     *
     * @return list<string>
     */
    public function window(int $position, int $count): array
    {
        if ($count < 1) {
            throw new ConfigurationException('A reel window must contain at least one symbol.');
        }
        if ($count > $this->length()) {
            throw new ConfigurationException(
                "A reel window of $count exceeds the strip length of {$this->length()}."
            );
        }

        $result = [];
        $normalised = (($position % $this->length()) + $this->length()) % $this->length();

        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->symbols[($normalised + $i) % $this->length()];
        }

        return $result;
    }

    /** A random position in the strip, uniformly distributed. */
    public function randomPosition(RngServiceInterface $rng): int
    {
        return $rng->int(0, $this->length() - 1);
    }
}
