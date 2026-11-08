<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Table\Blackjack;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackRules;

/**
 * Blackjack table configuration.
 *
 * Every rule that differs between real tables is a field: the number of decks,
 * whether the dealer hits soft 17, whether doubling after a split is allowed,
 * how many hands a round of splits may produce, and whether insurance and
 * surrender are offered. The framework already models these in
 * {@see BlackjackRules}; this class is the configuration object the engine
 * hands to the module, so the table's rules come from the session rather than
 * from a constant in the game.
 */
final class BlackjackConfiguration implements GameConfigurationInterface
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
        $known = [
            'decks',
            'dealer_hits_soft_17',
            'dealer_peeks',
            'double_after_split',
            'resplit_aces',
            'hit_split_aces',
            'max_hands',
            'split_limit',
            'insurance_enabled',
            'surrender_enabled',
            'reshuffle_threshold',
        ];

        $unknown = array_diff(array_keys($values), $known);
        if ($unknown !== []) {
            throw new ConfigurationException(
                'Unknown blackjack configuration key(s): ' . implode(', ', $unknown) . '.'
            );
        }

        return new self(new BlackjackRules(
            decks: (int) ($values['decks'] ?? 6),
            dealerHitsSoft17: (bool) ($values['dealer_hits_soft_17'] ?? false),
            dealerPeeks: (bool) ($values['dealer_peeks'] ?? true),
            doubleAfterSplit: (bool) ($values['double_after_split'] ?? true),
            resplitAces: (bool) ($values['resplit_aces'] ?? false),
            hitSplitAces: (bool) ($values['hit_split_aces'] ?? false),
            maxHands: (int) ($values['max_hands'] ?? 4),
            splitLimit: (int) ($values['split_limit'] ?? 3),
            insuranceEnabled: (bool) ($values['insurance_enabled'] ?? true),
            surrenderEnabled: (bool) ($values['surrender_enabled'] ?? false),
            reshuffleThreshold: (float) ($values['reshuffle_threshold'] ?? 0.75)
        ));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->rules->toArray();
    }

    public function validate(): void
    {
        $this->rules->validate();
    }
}
