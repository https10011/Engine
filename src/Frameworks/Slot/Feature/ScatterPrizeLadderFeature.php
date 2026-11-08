<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * A scatter prize ladder.
 *
 * Mahjong Wins' "Super Scatter": a special scatter pays a fixed cash prize from
 * a ladder (50x / 500x / 5,000x / 100,000x) rather than a paytable entry. The
 * prize is expressed as a multiplier of the bet, so the module turns it into an
 * outcome multiplier without the framework ever touching currency.
 *
 * The ladder is configuration, and the feature is inert unless a game enables
 * it, so a game that has no super scatter is unaffected.
 */
final class ScatterPrizeLadderFeature implements FeatureInterface
{
    /**
     * @param array<int, int> $ladder count => multiplier of the bet
     */
    public function __construct(
        private readonly string $scatterSymbol,
        private readonly array $ladder,
        private readonly string $featureType = 'scatter_prize'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $count = $context->grid->countOf($this->scatterSymbol);
        if ($count === 0 || $this->ladder === []) {
            return FeatureStep::idle([]);
        }

        $prize = $this->prizeFor($count);
        if ($prize <= 1) {
            return FeatureStep::idle([]);
        }

        return new FeatureStep(
            [],
            null,
            $prize,
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $prize,
                ['scatter_count' => $count, 'prize_multiplier' => $prize]
            )],
            ['scatter_count' => $count, 'prize_multiplier' => $prize]
        );
    }

    private function prizeFor(int $count): int
    {
        if (isset($this->ladder[$count])) {
            return $this->ladder[$count];
        }

        // Above the highest configured tier the top prize applies, which is the
        // usual "5+ scatters" convention.
        $highest = max(array_keys($this->ladder));

        return $count >= $highest ? $this->ladder[$highest] : 1;
    }
}
