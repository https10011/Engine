<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Rng;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\ValidationException;

/**
 * Production randomness, backed by PHP's CSPRNG (`random_int` / `random_bytes`).
 *
 * `random_int` is the correct primitive here: it is cryptographically secure,
 * unbiased across the requested range, and does not need seeding. Ordinary
 * `rand()` and `mt_rand()` are predictable from a small amount of observed
 * output and must never decide a wager outcome, so they are not used anywhere
 * in the engine.
 */
final class SecureRandomGenerator implements RngServiceInterface
{
    public function int(int $min, int $max): int
    {
        if ($min > $max) {
            throw new ValidationException('Random range minimum cannot exceed the maximum.');
        }

        return random_int($min, $max);
    }

    public function float(): float
    {
        // 53 bits of entropy, the most an IEEE-754 double can represent
        // exactly, mapped onto [0, 1).
        return random_int(0, (2 ** 53) - 1) / (2 ** 53);
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

        // Unreachable for non-negative weights, but keeps the return type total.
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
        return 'secure-random-int';
    }

    public function isDeterministic(): bool
    {
        return false;
    }
}
