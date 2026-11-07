<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Value;

use CasinoEngine\Core\Contract\GameConfigurationInterface;

/** A configuration with no settings. Used as the placeholder before a module exists. */
final class NullConfiguration implements GameConfigurationInterface
{
    public static function fromArray(array $values): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }

    public function validate(): void
    {
    }
}
