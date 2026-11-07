<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Synthetic;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackRules;

/** Configuration for the synthetic blackjack fixture. */
final class SyntheticBlackjackConfiguration implements GameConfigurationInterface
{
    public function __construct(private readonly BlackjackRules $rules = new BlackjackRules())
    {
    }

    public function rules(): BlackjackRules
    {
        return $this->rules;
    }

    public static function fromArray(array $values): static
    {
        $known = ['decks', 'dealer_hits_soft_17', 'double_after_split', 'insurance_enabled', 'surrender_enabled'];
        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw new ConfigurationException('Unknown blackjack configuration key(s): ' . implode(', ', $unknown) . '.');
        }

        return new self(new BlackjackRules(
            decks: (int) ($values['decks'] ?? 6),
            dealerHitsSoft17: (bool) ($values['dealer_hits_soft_17'] ?? false),
            doubleAfterSplit: (bool) ($values['double_after_split'] ?? true),
            insuranceEnabled: (bool) ($values['insurance_enabled'] ?? true),
            surrenderEnabled: (bool) ($values['surrender_enabled'] ?? false)
        ));
    }

    public function toArray(): array
    {
        return $this->rules->toArray();
    }

    public function validate(): void
    {
        $this->rules->validate();
    }
}
