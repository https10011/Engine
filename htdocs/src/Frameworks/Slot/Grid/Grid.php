<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Grid;

use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Slot\Reel\ReelConfiguration;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolType;

/**
 * A resolved visible grid.
 *
 * Cells are addressed `[reel][row]` and the grid may be ragged (a ways game
 * with unequal reel heights). Every read goes through a bounds check so an
 * evaluator cannot silently index past the end of a shorter reel and read a
 * symbol from the wrong column.
 */
final class Grid implements \JsonSerializable
{
    /**
     * @param list<list<string>> $cells `cells[reel][row]` => symbol id
     */
    public function __construct(
        private readonly array $cells,
        private readonly SymbolSet $symbols
    ) {
        if ($cells === []) {
            throw new ConfigurationException('A grid needs at least one reel.');
        }

        foreach ($cells as $reelIndex => $column) {
            if ($column === []) {
                throw new ConfigurationException("Grid reel $reelIndex is empty.");
            }
            foreach ($column as $symbolId) {
                if (!$symbols->has($symbolId)) {
                    throw new ConfigurationException("Grid references unknown symbol '$symbolId'.");
                }
            }
        }
    }

    /** Builds a grid by spinning the given reel configuration. */
    public static function spin(
        ReelConfiguration $reels,
        SymbolSet $symbols,
        \CasinoEngine\Core\Contract\Service\RngServiceInterface $rng
    ): self {
        return self::fromStopPositions($reels, $symbols, $reels->spin($rng));
    }

    /**
     * Builds a grid from explicit stop positions.
     *
     * @param list<int> $positions
     */
    public static function fromStopPositions(
        ReelConfiguration $reels,
        SymbolSet $symbols,
        array $positions
    ): self {
        if (count($positions) !== $reels->reelCount()) {
            throw new ConfigurationException('One stop position is required per reel.');
        }

        $cells = [];
        foreach ($reels->reels() as $index => $reel) {
            $cells[] = $reel->window((int) $positions[$index], $reels->rowsFor($index));
        }

        return new self($cells, $symbols);
    }

    /**
     * Builds a grid from an explicit cell matrix.
     *
     * @param list<list<string>> $cells
     */
    public static function fromCells(array $cells, SymbolSet $symbols): self
    {
        return new self($cells, $symbols);
    }

    public function symbolSet(): SymbolSet
    {
        return $this->symbols;
    }

    public function reelCount(): int
    {
        return count($this->cells);
    }

    public function rowsFor(int $reelIndex): int
    {
        return count($this->cells[$reelIndex] ?? []);
    }

    public function has(int $reel, int $row): bool
    {
        return isset($this->cells[$reel][$row]);
    }

    public function symbolAt(int $reel, int $row): string
    {
        if (!$this->has($reel, $row)) {
            throw new ConfigurationException("No cell at reel $reel row $row.");
        }

        return $this->cells[$reel][$row];
    }

    public function symbol(int $reel, int $row): \CasinoEngine\Frameworks\Slot\Symbol\Symbol
    {
        return $this->symbols->get($this->symbolAt($reel, $row));
    }

    public function typeAt(int $reel, int $row): SymbolType
    {
        return $this->symbol($reel, $row)->type;
    }

    public function isWildAt(int $reel, int $row): bool
    {
        return $this->has($reel, $row) && $this->symbol($reel, $row)->isWild();
    }

    public function isScatterAt(int $reel, int $row): bool
    {
        return $this->has($reel, $row) && $this->symbol($reel, $row)->isScatter();
    }

    /** @return list<list<string>> */
    public function cells(): array
    {
        return $this->cells;
    }

    /**
     * Replaces one cell, returning a new grid.
     *
     * Used by features that transform symbols (a wild upgrade, a cascade
     * refill). Returning a new grid rather than mutating keeps evaluation
     * free of hidden state changes.
     */
    public function withSymbol(int $reel, int $row, string $symbolId): self
    {
        if (!$this->has($reel, $row)) {
            throw new ConfigurationException("No cell at reel $reel row $row.");
        }
        if (!$this->symbols->has($symbolId)) {
            throw new ConfigurationException("Unknown symbol '$symbolId'.");
        }

        $cells = $this->cells;
        $cells[$reel][$row] = $symbolId;

        return new self($cells, $this->symbols);
    }

    /**
     * Removes cells, returning a new ragged grid.
     *
     * @param list<array{0:int,1:int}> $positions `[reel, row]` pairs to remove
     */
    public function withoutPositions(array $positions): self
    {
        $remove = [];
        foreach ($positions as [$reel, $row]) {
            $remove[$reel][$row] = true;
        }

        $cells = [];
        foreach ($this->cells as $reelIndex => $column) {
            $kept = [];
            foreach ($column as $rowIndex => $symbolId) {
                if (!isset($remove[$reelIndex][$rowIndex])) {
                    $kept[] = $symbolId;
                }
            }
            $cells[] = $kept;
        }

        return new self($cells, $this->symbols);
    }

    /**
     * Counts occurrences of each symbol id.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];
        foreach ($this->cells as $column) {
            foreach ($column as $symbolId) {
                $counts[$symbolId] = ($counts[$symbolId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Positions of every occurrence of a symbol, optionally filtered by type.
     *
     * @return list<array{0:int,1:int}>
     */
    public function positionsOf(?SymbolType $type = null): array
    {
        $positions = [];
        foreach ($this->cells as $reelIndex => $column) {
            foreach ($column as $rowIndex => $symbolId) {
                if ($type !== null && $this->symbols->get($symbolId)->type !== $type) {
                    continue;
                }
                $positions[] = [$reelIndex, $rowIndex];
            }
        }

        return $positions;
    }

    /** @return list<array{0:int,1:int}> */
    public function positionsOfSymbol(string $symbolId): array
    {
        $positions = [];
        foreach ($this->cells as $reelIndex => $column) {
            foreach ($column as $rowIndex => $id) {
                if ($id === $symbolId) {
                    $positions[] = [$reelIndex, $rowIndex];
                }
            }
        }

        return $positions;
    }

    public function countOf(string $symbolId): int
    {
        return $this->counts()[$symbolId] ?? 0;
    }

    /** @return list<list<string>> */
    public function jsonSerialize(): array
    {
        return $this->cells;
    }
}
