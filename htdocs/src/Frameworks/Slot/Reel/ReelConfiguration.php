<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Reel;

use CasinoEngine\Core\Contract\Service\RngServiceInterface;
use CasinoEngine\Core\Exception\ConfigurationException;

/**
 * The reels and visible rows of one game.
 *
 * `rows` may be a single integer for a rectangular grid or a per-reel list for
 * a game whose reels have different heights — which is exactly what a
 * "ways" game with 3-4-5-4-3 rows needs, so the shape is not assumed to be
 * rectangular.
 */
final class ReelConfiguration
{
    /** @var list<ReelStrip> */
    private array $reels;

    /** @var list<int> */
    private array $rows;

    /**
     * @param list<ReelStrip> $reels
     * @param int|list<int> $rows
     */
    public function __construct(array $reels, int|array $rows)
    {
        if ($reels === []) {
            throw new ConfigurationException('A reel configuration needs at least one reel.');
        }

        $this->reels = array_values($reels);

        $rowList = is_int($rows) ? array_fill(0, count($this->reels), $rows) : array_values($rows);

        if (count($rowList) !== count($this->reels)) {
            throw new ConfigurationException(
                'The row count must be given once per reel (or as a single integer).'
            );
        }

        foreach ($rowList as $index => $rowCount) {
            if ($rowCount < 1) {
                throw new ConfigurationException("Reel $index must show at least one row.");
            }
            if ($rowCount > $this->reels[$index]->length()) {
                throw new ConfigurationException(
                    "Reel $index shows $rowCount rows but its strip has only {$this->reels[$index]->length()} symbols."
                );
            }
        }

        $this->rows = $rowList;
    }

    /** @return list<ReelStrip> */
    public function reels(): array
    {
        return $this->reels;
    }

    public function reelCount(): int
    {
        return count($this->reels);
    }

    /** @return list<int> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function rowsFor(int $reelIndex): int
    {
        return $this->rows[$reelIndex];
    }

    public function maxRows(): int
    {
        return max($this->rows);
    }

    public function isRectangular(): bool
    {
        return count(array_unique($this->rows)) === 1;
    }

    /**
     * Spins the reels, returning the stop position of each.
     *
     * @return list<int>
     */
    public function spin(RngServiceInterface $rng): array
    {
        $positions = [];
        foreach ($this->reels as $reel) {
            $positions[] = $reel->randomPosition($rng);
        }

        return $positions;
    }

    /**
     * Spins with a forced stop position per reel. Used by tests to construct an
     * exact grid, and by a module that needs to place a symbol deliberately
     * (for example a bonus trigger).
     *
     * @param list<int> $positions
     * @return list<int>
     */
    public function spinAt(array $positions): array
    {
        if (count($positions) !== $this->reelCount()) {
            throw new ConfigurationException('One stop position is required per reel.');
        }

        return array_map('intval', $positions);
    }
}
