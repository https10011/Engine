<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;

/**
 * Typed configuration for a slot module.
 *
 * Unknown keys are rejected so a typo in a game definition fails at boot rather
 * than silently leaving a default in place. Every value is optional and has a
 * documented default, so a minimal game only sets what it changes.
 */
final class SlotConfiguration implements GameConfigurationInterface
{
    /**
     * @param list<int> $betLevels available bet multipliers
     */
    public function __construct(
        public readonly int $coinValueMinor = 100,
        public readonly array $betLevels = [1, 2, 5, 10, 20],
        public readonly bool $cascadesEnabled = false,
        public readonly int $maxCascades = 20,
        public readonly bool $comboMultiplierEnabled = false,
        /** @var list<int> */
        public readonly array $comboLadder = [1, 2, 3, 5, 10],
        public readonly bool $freeSpinsEnabled = false,
        /** @var array<int, int> scatter count => spins */
        public readonly array $freeSpinAwards = [3 => 10, 4 => 15, 5 => 20],
        public readonly bool $freeSpinsRetrigger = true,
        public readonly int $freeSpinsCap = 200,
        /** @var array<string, mixed> */
        public readonly array $extra = []
    ) {
    }

    public static function fromArray(array $values): static
    {
        $known = [
            'coin_value_minor',
            'bet_levels',
            'cascades_enabled',
            'max_cascades',
            'combo_multiplier_enabled',
            'combo_ladder',
            'free_spins_enabled',
            'free_spin_awards',
            'free_spins_retrigger',
            'free_spins_cap',
            'extra',
        ];

        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw new ConfigurationException(
                'Unknown slot configuration key(s): ' . implode(', ', $unknown) . '.'
            );
        }

        $awards = [];
        $rawAwards = $values['free_spin_awards'] ?? [3 => 10, 4 => 15, 5 => 20];
        if (is_array($rawAwards)) {
            foreach ($rawAwards as $count => $spins) {
                $awards[(int) $count] = (int) $spins;
            }
        }

        /** @var list<int> $ladder */
        $ladder = array_values(array_map('intval', is_array($values['combo_ladder'] ?? null)
            ? $values['combo_ladder']
            : [1, 2, 3, 5, 10]));

        /** @var list<int> $betLevels */
        $betLevels = array_values(array_map('intval', is_array($values['bet_levels'] ?? null)
            ? $values['bet_levels']
            : [1, 2, 5, 10, 20]));

        return new self(
            (int) ($values['coin_value_minor'] ?? 100),
            $betLevels,
            (bool) ($values['cascades_enabled'] ?? false),
            (int) ($values['max_cascades'] ?? 20),
            (bool) ($values['combo_multiplier_enabled'] ?? false),
            $ladder,
            (bool) ($values['free_spins_enabled'] ?? false),
            $awards,
            (bool) ($values['free_spins_retrigger'] ?? true),
            (int) ($values['free_spins_cap'] ?? 200),
            is_array($values['extra'] ?? null) ? $values['extra'] : []
        );
    }

    public function toArray(): array
    {
        return [
            'coin_value_minor'        => $this->coinValueMinor,
            'bet_levels'              => $this->betLevels,
            'cascades_enabled'        => $this->cascadesEnabled,
            'max_cascades'            => $this->maxCascades,
            'combo_multiplier_enabled' => $this->comboMultiplierEnabled,
            'combo_ladder'            => $this->comboLadder,
            'free_spins_enabled'      => $this->freeSpinsEnabled,
            'free_spin_awards'        => $this->freeSpinAwards,
            'free_spins_retrigger'    => $this->freeSpinsRetrigger,
            'free_spins_cap'          => $this->freeSpinsCap,
            'extra'                   => $this->extra,
        ];
    }

    public function validate(): void
    {
        if ($this->coinValueMinor < 0) {
            throw new ConfigurationException('coin_value_minor cannot be negative.');
        }
        if ($this->maxCascades < 1) {
            throw new ConfigurationException('max_cascades must be at least 1.');
        }
        if ($this->freeSpinsCap < 1) {
            throw new ConfigurationException('free_spins_cap must be at least 1.');
        }
        foreach ($this->comboLadder as $value) {
            if ($value < 1) {
                throw new ConfigurationException('Every combo ladder step must be at least 1.');
            }
        }
        foreach ($this->betLevels as $level) {
            if ($level < 1) {
                throw new ConfigurationException('Every bet level must be at least 1.');
            }
        }
    }
}
