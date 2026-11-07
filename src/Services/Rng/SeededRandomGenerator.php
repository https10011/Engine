<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Rng;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\ValidationException;

/**
 * Deterministic generator for tests and simulation only.
 *
 * Implements xoshiro128** using 32-bit words. Everything stays inside the
 * signed 64-bit integer range PHP guarantees, so no intermediate value is ever
 * silently promoted to a float — a subtle failure mode that quietly degrades a
 * generator's quality.
 *
 * It is fast and statistically sound enough for simulation, and exactly
 * reproducible from a seed, which is what makes a simulation run repeatable.
 *
 * It is **not** cryptographically secure and refuses to construct in a
 * production environment, so a deterministic stream cannot silently become the
 * source of real outcomes.
 */
final class SeededRandomGenerator implements RngServiceInterface
{
    private const MASK32 = 0xFFFFFFFF;

    /** Largest exactly-representable 53-bit integer, matching float precision. */
    private const MAX53 = 0x1FFFFFFFFFFFFF; // 2^53 - 1

    /** @var array{int,int,int,int} Four 32-bit state words. */
    private array $state;

    /**
     * @param int $seed Any integer; the same seed always yields the same stream.
     *
     * @throws ValidationException when constructed in production.
     */
    public function __construct(int $seed, string $environment = 'testing')
    {
        if ($environment === 'production') {
            throw new ValidationException(
                'The seeded random generator must not be used in production.'
            );
        }

        // SplitMix32 expands a single seed into four well-separated words.
        $words = [];
        $x = $seed & self::MASK32;
        for ($i = 0; $i < 4; $i++) {
            $x = ($x + 0x9E3779B9) & self::MASK32;
            $words[] = self::splitMix32($x);
        }

        /** @var array{int,int,int,int} $words */
        $this->state = $words;

        // Discard the first outputs so the initial state is fully diffused.
        for ($i = 0; $i < 8; $i++) {
            $this->next32();
        }
    }

    public function int(int $min, int $max): int
    {
        if ($min > $max) {
            throw new ValidationException('Random range minimum cannot exceed the maximum.');
        }

        $range = $max - $min + 1;
        if ($range <= 0) {
            return $min + $this->next53();
        }

        // Rejection sampling avoids the modulo bias a plain `% range` would
        // introduce for ranges that do not divide the output space evenly.
        $limit = intdiv(self::MAX53, $range) * $range;
        do {
            $draw = $this->next53();
        } while ($draw >= $limit);

        return $min + ($draw % $range);
    }

    public function float(): float
    {
        return $this->next53() / (self::MAX53 + 1);
    }

    public function chance(float $probability): bool
    {
        if ($probability < 0.0 || $probability > 1.0) {
            throw new ValidationException('Probability must be between 0 and 1.');
        }
        if ($probability <= 0.0) {
            return false;
        }
        if ($probability >= 1.0) {
            return true;
        }

        return $this->float() < $probability;
    }

    public function pick(array $items): mixed
    {
        if ($items === []) {
            throw new ValidationException('Cannot pick from an empty list.');
        }

        return $items[$this->int(0, count($items) - 1)];
    }

    public function weightedIndex(array $weights): int
    {
        if ($weights === []) {
            throw new ValidationException('Cannot pick from an empty weight list.');
        }

        $total = 0;
        foreach ($weights as $weight) {
            if (!is_int($weight) || $weight < 0) {
                throw new ValidationException('Weights must be non-negative integers.');
            }
            $total += $weight;
        }

        if ($total <= 0) {
            throw new ValidationException('At least one weight must be positive.');
        }

        $target = $this->int(1, $total);
        $running = 0;
        foreach ($weights as $index => $weight) {
            $running += $weight;
            if ($target <= $running) {
                return $index;
            }
        }

        return count($weights) - 1;
    }

    public function shuffle(array $items): array
    {
        $result = array_values($items);
        for ($i = count($result) - 1; $i > 0; $i--) {
            $j = $this->int(0, $i);
            [$result[$i], $result[$j]] = [$result[$j], $result[$i]];
        }

        return $result;
    }

    public function describe(): string
    {
        return 'seeded-xoshiro128';
    }

    public function isDeterministic(): bool
    {
        return true;
    }

    /** SplitMix32 finaliser, all arithmetic masked to 32 bits. */
    private static function splitMix32(int $value): int
    {
        $value = ($value + 0x9E3779B9) & self::MASK32;
        $value = (($value ^ ($value >> 16)) * 0x21F0AAAD) & self::MASK32;
        $value = (($value ^ ($value >> 15)) * 0x735A2D97) & self::MASK32;

        return ($value ^ ($value >> 15)) & self::MASK32;
    }

    /** One xoshiro128** step, returning a 32-bit unsigned value. */
    private function next32(): int
    {
        $s = $this->state;

        $result = (self::rotl32(($s[1] * 5) & self::MASK32, 7) * 9) & self::MASK32;
        $t = ($s[1] << 9) & self::MASK32;

        $s[2] ^= $s[0];
        $s[3] ^= $s[1];
        $s[1] ^= $s[2];
        $s[0] ^= $s[3];
        $s[2] ^= $t;
        $s[3] = self::rotl32($s[3], 11);

        $this->state = $s;

        return $result;
    }

    /** 53 bits assembled from two 32-bit draws, staying float-exact. */
    private function next53(): int
    {
        $high = $this->next32() >> 5;  // 27 bits
        $low  = $this->next32() >> 6;  // 26 bits

        return ($high << 26) | $low;
    }

    private static function rotl32(int $value, int $shift): int
    {
        return (($value << $shift) | ($value >> (32 - $shift))) & self::MASK32;
    }
}
