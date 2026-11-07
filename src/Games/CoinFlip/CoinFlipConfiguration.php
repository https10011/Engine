<?php

declare(strict_types=1);

namespace CasinoEngine\Games\CoinFlip;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;

final class CoinFlipConfiguration implements GameConfigurationInterface
{
    public function __construct(public readonly bool $doubleOnHeads = true)
    {
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): static
    {
        $known = ['double_on_heads'];
        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw new ConfigurationException(
                'Unknown configuration key(s): ' . implode(', ', $unknown) . '.'
            );
        }

        return new self((bool) ($values['double_on_heads'] ?? true));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['double_on_heads' => $this->doubleOnHeads];
    }

    public function validate(): void
    {
    }
}
