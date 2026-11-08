<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * An auxiliary multiplier reel beside the main reels.
 *
 * Fortune Gems 2's "multiplier reel on the right side" and Fortune Garuda's
 * "special side reels" are the same shape: a separate strip that stops on a
 * multiplier, which is applied to the line win when it aligns.
 *
 * The reel is a list of integer multipliers rather than symbols, because a side
 * reel is not evaluated for wins — it only multiplies. Spinning it through the
 * injected RNG keeps it authoritative and reproducible under a seed.
 *
 * `$alignChance` models "when it aligns": a side reel does not multiply every
 * win, so the multiplier is applied only when the alignment roll succeeds.
 */
final class AuxiliaryReelFeature implements FeatureInterface, SpinLifecycleInterface
{
    /**
     * @param list<int> $multipliers
     */
    public function __construct(
        private readonly array $multipliers,
        private readonly float $alignChance = 1.0,
        private readonly string $featureType = 'auxiliary_reel'
    ) {
        if ($multipliers === []) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                'An auxiliary reel needs at least one multiplier.'
            );
        }
    }

    public function type(): string
    {
        return $this->featureType;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function onSpinStart(array $state): array
    {
        return ['spins' => (int) ($state['spins'] ?? 0)];
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $spins = (int) ($context->state['spins'] ?? 0);

        // The side reel stops once per spin. Re-spinning it on every cascade
        // step would let a single win be multiplied several times.
        if ($spins > 0) {
            return new FeatureStep(['spins' => $spins], null, 1, false, [], ['already_spun' => true]);
        }

        if (!$context->evaluation->isWin()) {
            return new FeatureStep(['spins' => 1], null, 1, false, [], ['no_win' => true]);
        }

        if (!$context->rng->chance($this->alignChance)) {
            return new FeatureStep(['spins' => 1], null, 1, false, [], ['not_aligned' => true]);
        }

        $multiplier = $this->multipliers[$context->rng->int(0, count($this->multipliers) - 1)];

        return new FeatureStep(
            ['spins' => 1],
            null,
            max(1, $multiplier),
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $multiplier,
                ['multiplier' => $multiplier]
            )],
            ['multiplier' => $multiplier]
        );
    }
}
