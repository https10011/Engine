<?php

declare(strict_types=1);

namespace CasinoEngine\Core\Contract;

/**
 * Typed, validated configuration for a game module.
 *
 * Implementations reject unknown keys so that a typo in a game definition
 * fails loudly instead of silently falling back to a default.
 */
interface GameConfigurationInterface
{
    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): static;

    /** @return array<string, mixed> */
    public function toArray(): array;

    /**
     * @throws \CasinoEngine\Core\Exception\ConfigurationException
     */
    public function validate(): void;
}
