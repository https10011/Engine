<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract\Service;

/**
 * The only source of randomness an authoritative outcome may use.
 *
 * Two implementations exist and they are deliberately different types:
 * {@see \CasinoEngine\Services\Rng\SecureRandomGenerator} for production and
 * {@see \CasinoEngine\Services\Rng\SeededRandomGenerator} for tests and
 * simulation. The seeded generator refuses to construct in a production
 * environment, so a deterministic RNG cannot reach real play by accident.
 */
interface RngServiceInterface
{
    /**
     * Uniform integer in `[min, max]` inclusive.
     *
     * @throws \CasinoEngine\Core\Exception\ValidationException when min > max
     */
    public function int(int $min, int $max): int;

    /** Uniform float in `[0, 1)`. */
    public function float(): float;

    /** True with probability `$probability` (0.0–1.0). */
    public function chance(float $probability): bool;

    /**
     * Picks one element with uniform probability.
     *
     * @template T
     * @param non-empty-list<T> $items
     * @return T
     */
    public function pick(array $items): mixed;

    /**
     * Picks an index using integer weights. Weights must be non-negative and
     * at least one must be positive.
     *
     * @param non-empty-list<int> $weights
     */
    public function weightedIndex(array $weights): int;

    /**
     * Fisher–Yates shuffle, returning a new list.
     *
     * @template T
     * @param list<T> $items
     * @return list<T>
     */
    public function shuffle(array $items): array;

    /** Identifies the implementation for logging and simulation reports. */
    public function describe(): string;

    /** True when the generator is deterministic and therefore not for production. */
    public function isDeterministic(): bool;
}
