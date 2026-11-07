<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Symbol;

use CasinoEngine\Core\Exception\ConfigurationException;

/**
 * The symbol catalogue for one game.
 *
 * Lookup by id is the hot path during evaluation, so symbols are indexed on
 * construction and an unknown id is a configuration error rather than a null
 * that surfaces later as a mysterious zero payout.
 */
final class SymbolSet
{
    /** @var array<string, Symbol> */
    private array $symbols = [];

    /** @param list<Symbol> $symbols */
    public function __construct(array $symbols)
    {
        if ($symbols === []) {
            throw new ConfigurationException('A symbol set cannot be empty.');
        }

        foreach ($symbols as $symbol) {
            if (isset($this->symbols[$symbol->id])) {
                throw new ConfigurationException("Duplicate symbol id '{$symbol->id}'.");
            }
            $this->symbols[$symbol->id] = $symbol;
        }
    }

    public function get(string $id): Symbol
    {
        if (!isset($this->symbols[$id])) {
            throw new ConfigurationException("Unknown symbol '$id'.");
        }

        return $this->symbols[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->symbols[$id]);
    }

    /** @return list<Symbol> */
    public function all(): array
    {
        return array_values($this->symbols);
    }

    /** @return list<Symbol> */
    public function ofType(SymbolType $type): array
    {
        return array_values(array_filter(
            $this->symbols,
            static fn (Symbol $s): bool => $s->type === $type
        ));
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->symbols);
    }

    public function firstOfType(SymbolType $type): ?Symbol
    {
        foreach ($this->symbols as $symbol) {
            if ($symbol->type === $type) {
                return $symbol;
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->symbols as $id => $symbol) {
            $result[$id] = $symbol->toArray();
        }

        return $result;
    }
}
