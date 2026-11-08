<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Jackpot roll.
 *
 * The directive requires jackpot probability to be independently configurable
 * and distinguishable from an ordinary win. It is therefore a separate roll
 * with its own probability and its own tier ladder, not a paytable entry and
 * not a consequence of the win frequency.
 *
 * The probability and the tiers arrive through the spin configuration, which
 * the module fills from the effective probability profile. An absent or zero
 * probability means the jackpot is disabled, so a game with no jackpot
 * configured never rolls one.
 */
final class JackpotFeature implements FeatureInterface, SpinLifecycleInterface
{
    /**
     * The tiers and the trigger probability are not constructor arguments: they
     * arrive through the spin configuration, which the module fills from the
     * effective probability profile, so an administrator's change takes effect
     * on the next round without rebuilding the game.
     *
     * The configuration keys read are `jackpot_probability` (0–1) and
     * `jackpot_tiers` (tier name => `{probability, payout_multiplier}`).
     */
    public function __construct(
        private readonly string $featureType = 'jackpot'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function onSpinStart(array $state): array
    {
        return ['rolled' => false];
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        // One roll per spin. Rolling on every cascade step would multiply the
        // effective jackpot probability by the number of steps, which is
        // exactly the kind of silent divergence from the configured value the
        // directive forbids.
        if (($context->state['rolled'] ?? false) === true) {
            return new FeatureStep(['rolled' => true], null, 1, false, [], []);
        }

        $probability = (float) ($context->configuration['jackpot_probability'] ?? 0.0);
        if ($probability <= 0.0) {
            return new FeatureStep(['rolled' => true], null, 1, false, [], ['disabled' => true]);
        }

        if (!$context->rng->chance($probability)) {
            return new FeatureStep(['rolled' => true], null, 1, false, [], ['triggered' => false]);
        }

        $tier = $this->pickTier($context);
        $payout = $tier === null ? 1000 : (int) round($tier['payout_multiplier']);
        $tierName = $tier === null ? 'default' : (string) $tier['name'];

        // The step multiplier is deliberately 1: a jackpot is a prize paid on
        // top of whatever the reels did, not a factor applied to a line win. If
        // it were returned as a step multiplier it would (a) multiply a line
        // win by the jackpot and (b) vanish entirely on a spin with no line
        // win, which is the very case a jackpot most often arrives in. The
        // module settles the prize from the transition instead.
        return new FeatureStep(
            ['rolled' => true, 'tier' => $tierName],
            null,
            1,
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::STARTED,
                $payout,
                ['tier' => $tierName, 'payout_multiplier' => $payout, 'probability' => $probability]
            )],
            ['triggered' => true, 'tier' => $tierName, 'payout_multiplier' => $payout]
        );
    }

    /**
     * Picks a jackpot tier, or null when only the default payout is configured.
     *
     * @return array{name:string,probability:float,payout_multiplier:float}|null
     */
    private function pickTier(FeatureContext $context): ?array
    {
        $tiers = $context->configuration['jackpot_tiers'] ?? null;
        if (!is_array($tiers) || $tiers === []) {
            return null;
        }

        $normalised = [];
        foreach ($tiers as $name => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $normalised[] = [
                'name'              => (string) $name,
                'probability'       => (float) ($definition['probability'] ?? 0.0),
                'payout_multiplier' => (float) ($definition['payout_multiplier'] ?? 0.0),
            ];
        }

        if ($normalised === []) {
            return null;
        }

        $weights = array_map(
            static fn (array $tier): int => max(0, (int) round($tier['probability'] * 1_000_000)),
            $normalised
        );

        if (array_sum($weights) <= 0) {
            return $normalised[0];
        }

        return $normalised[$context->rng->weightedIndex($weights)];
    }
}
