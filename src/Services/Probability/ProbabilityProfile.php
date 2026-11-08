<?php

declare(strict_types=1);

namespace CasinoEngine\Services\Probability;

use CasinoEngine\Core\Exception\ConfigurationException;

/**
 * One resolved probability policy.
 *
 * The directive requires six metrics to be represented separately, because
 * they are genuinely different questions:
 *
 *  - any-win: did the round return anything at all?
 *  - net-positive: did the round return more than it cost?
 *  - RTP: over many rounds, how much came back per unit wagered?
 *  - jackpot: did the jackpot roll succeed?
 *  - bonus trigger: did a bonus feature start?
 *  - free-spin trigger: were free spins awarded?
 *
 * A 50% any-win target is therefore *not* 50% RTP and *not* "every second spin
 * wins". Each field is independently configurable and `null` means "do not
 * constrain this metric", so a game with no bonus round is never asked to
 * satisfy a bonus probability.
 *
 * Instances are immutable; the service replaces rather than mutates one.
 */
final class ProbabilityProfile
{
    public const NAME_GLOBAL = 'global';

    /**
     * @param array<string, array{probability:float,payout_multiplier:float}> $jackpotTiers
     */
    public function __construct(
        public readonly string $name = self::NAME_GLOBAL,
        public readonly ?string $gameId = null,
        public readonly ?float $anyWinTarget = null,
        public readonly ?float $netPositiveTarget = null,
        public readonly ?float $rtpTarget = null,
        public readonly ?float $jackpotProbability = null,
        public readonly ?float $bonusTriggerProbability = null,
        public readonly ?float $freeSpinTriggerProbability = null,
        public readonly ?float $payoutScale = null,
        public readonly array $jackpotTiers = [],
        public readonly bool $enabled = false,
        public readonly int $maxResampleAttempts = 200,
        public readonly int $version = 0
    ) {
        $this->validate();
    }

    /** A policy that constrains nothing: the engine's own behaviour. */
    public static function inactive(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values, ?string $gameId = null): self
    {
        $tiers = [];
        $rawTiers = $values['jackpot_tiers'] ?? [];
        if (is_array($rawTiers)) {
            foreach ($rawTiers as $tier => $tierValue) {
                if (!is_array($tierValue)) {
                    continue;
                }
                $tiers[(string) $tier] = [
                    'probability'       => (float) ($tierValue['probability'] ?? 0.0),
                    'payout_multiplier' => (float) ($tierValue['payout_multiplier'] ?? 0.0),
                ];
            }
        }

        return new self(
            (string) ($values['name'] ?? ($gameId ?? self::NAME_GLOBAL)),
            $gameId,
            self::nullableFloat($values['any_win_target'] ?? null),
            self::nullableFloat($values['net_positive_target'] ?? null),
            self::nullableFloat($values['rtp_target'] ?? null),
            self::nullableFloat($values['jackpot_probability'] ?? null),
            self::nullableFloat($values['bonus_trigger_probability'] ?? null),
            self::nullableFloat($values['free_spin_trigger_probability'] ?? null),
            self::nullableFloat($values['payout_scale'] ?? null),
            $tiers,
            (bool) ($values['enabled'] ?? false),
            (int) ($values['max_resample_attempts'] ?? 200),
            (int) ($values['version'] ?? 0)
        );
    }

    /**
     * Whether this policy constrains anything at all.
     *
     * A disabled or empty policy must be inert: a module handed one behaves
     * exactly as it did before the probability service existed.
     */
    public function isActive(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->anyWinTarget !== null
            || $this->netPositiveTarget !== null
            || $this->rtpTarget !== null
            || $this->jackpotProbability !== null
            || $this->bonusTriggerProbability !== null
            || $this->freeSpinTriggerProbability !== null;
    }

    public function hasJackpot(): bool
    {
        return $this->enabled && $this->jackpotProbability !== null && $this->jackpotProbability > 0.0;
    }

    /** The scale applied to an outcome multiplier, clamped to a sane range. */
    public function scale(): float
    {
        if ($this->payoutScale === null) {
            return 1.0;
        }

        return max(0.0, min(1000.0, $this->payoutScale));
    }

    /** A copy of this profile stamped with the version it was stored as. */
    public function withVersion(int $version): self
    {
        return new self(
            $this->name,
            $this->gameId,
            $this->anyWinTarget,
            $this->netPositiveTarget,
            $this->rtpTarget,
            $this->jackpotProbability,
            $this->bonusTriggerProbability,
            $this->freeSpinTriggerProbability,
            $this->payoutScale,
            $this->jackpotTiers,
            $this->enabled,
            $this->maxResampleAttempts,
            $version
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name'                      => $this->name,
            'game_id'                   => $this->gameId,
            'any_win_target'            => $this->anyWinTarget,
            'net_positive_target'       => $this->netPositiveTarget,
            'rtp_target'                => $this->rtpTarget,
            'jackpot_probability'       => $this->jackpotProbability,
            'bonus_trigger_probability' => $this->bonusTriggerProbability,
            'free_spin_trigger_probability' => $this->freeSpinTriggerProbability,
            'payout_scale'              => $this->payoutScale,
            'jackpot_tiers'             => $this->jackpotTiers,
            'enabled'                   => $this->enabled,
            'max_resample_attempts'     => $this->maxResampleAttempts,
            'version'                   => $this->version,
        ];
    }

    /**
     * Range-checks every field.
     *
     * A probability outside [0, 1] is a configuration error, not a value to
     * clamp silently: an administrator who typed 500 means something the engine
     * cannot honour, and pretending otherwise would hide the mistake.
     */
    public function validate(): void
    {
        $probabilities = [
            'any_win_target' => $this->anyWinTarget,
            'net_positive_target' => $this->netPositiveTarget,
            'jackpot_probability' => $this->jackpotProbability,
            'bonus_trigger_probability' => $this->bonusTriggerProbability,
            'free_spin_trigger_probability' => $this->freeSpinTriggerProbability,
        ];

        foreach ($probabilities as $field => $value) {
            if ($value !== null && ($value < 0.0 || $value > 1.0)) {
                throw new ConfigurationException(
                    "The probability '$field' must be between 0 and 1; got $value."
                );
            }
        }

        if ($this->rtpTarget !== null && ($this->rtpTarget < 0.0 || $this->rtpTarget > 20.0)) {
            throw new ConfigurationException('rtp_target must be between 0 and 20.');
        }

        if ($this->payoutScale !== null && ($this->payoutScale < 0.0 || $this->payoutScale > 1000.0)) {
            throw new ConfigurationException('payout_scale must be between 0 and 1000.');
        }

        if ($this->maxResampleAttempts < 0 || $this->maxResampleAttempts > 10000) {
            throw new ConfigurationException('max_resample_attempts must be between 0 and 10000.');
        }

        foreach ($this->jackpotTiers as $tier => $definition) {
            $probability = $definition['probability'];
            if ($probability < 0.0 || $probability > 1.0) {
                throw new ConfigurationException("Jackpot tier '$tier' probability must be between 0 and 1.");
            }
            if ($definition['payout_multiplier'] < 0.0) {
                throw new ConfigurationException("Jackpot tier '$tier' payout cannot be negative.");
            }
        }
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new ConfigurationException('A probability value must be numeric or null.');
        }

        return (float) $value;
    }
}
