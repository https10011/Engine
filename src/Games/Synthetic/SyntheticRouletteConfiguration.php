<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Synthetic;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;

/** Configuration for the synthetic roulette fixture. */
final class SyntheticRouletteConfiguration implements GameConfigurationInterface
{
    public function __construct(private readonly ?RouletteVariant $variant = null)
    {
    }

    /** The variant a fresh configuration uses, built from the standard wheels. */
    public static function defaultVariant(): RouletteVariant
    {
        return RouletteVariant::european();
    }

    public static function fromArray(array $values): static
    {
        $known = ['variant'];
        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw new ConfigurationException('Unknown roulette configuration key(s): ' . implode(', ', $unknown) . '.');
        }

        return new self(self::variantFromName((string) ($values['variant'] ?? RouletteVariant::EUROPEAN)));
    }

    public static function variantFromName(string $name): RouletteVariant
    {
        return match ($name) {
            RouletteVariant::AMERICAN => RouletteVariant::american(),
            RouletteVariant::FRENCH   => RouletteVariant::french(),
            RouletteVariant::EUROPEAN => RouletteVariant::european(),
            default => throw new ConfigurationException("Unknown roulette variant '$name'."),
        };
    }

    public function variant(): RouletteVariant
    {
        return $this->variant ?? self::defaultVariant();
    }

    public function toArray(): array
    {
        return ["variant" => $this->variant()->name()];
    }

    public function validate(): void
    {
        if ($this->variant()->pocketCount() < 2) {
            throw new ConfigurationException('A roulette wheel needs at least two pockets.');
        }
    }
}
