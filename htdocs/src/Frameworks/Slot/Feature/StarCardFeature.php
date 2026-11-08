<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Frameworks\Slot\Grid\Grid;

/**
 * The Star Card.
 *
 * Super Ace Deluxe's mechanic: a Star Card may activate on the 2nd–4th cascade
 * and is **guaranteed from the 5th**; when it activates it converts the symbols
 * on reels 2–4 into Golden Symbols, which then become Joker Wilds when they are
 * eliminated.
 *
 * The "probabilistic early, guaranteed late" rule is expressed as two
 * parameters rather than a special case, because that shape recurs across the
 * target titles. The conversion is a grid transformation, so the very next
 * evaluation sees the golden symbols.
 */
final class StarCardFeature implements FeatureInterface
{
    /**
     * @param list<int> $targetReels Reels the Star Card converts
     */
    public function __construct(
        private readonly string $starSymbol,
        private readonly string $goldenSymbol,
        private readonly array $targetReels = [1, 2, 3],
        private readonly int $minimumCascade = 2,
        private readonly int $guaranteedCascade = 5,
        private readonly float $probability = 0.25,
        private readonly string $featureType = 'star_card'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $cascadeIndex = (int) ($context->state['index'] ?? 0);
        $activated = (bool) ($context->state['activated'] ?? false);

        // The card has already done its work this spin.
        if ($activated) {
            return new FeatureStep(
                ['index' => $cascadeIndex, 'activated' => true],
                null,
                1,
                false,
                [],
                ['already_active' => true]
            );
        }

        // The Star Card is not available before the configured cascade.
        if ($cascadeIndex < $this->minimumCascade - 1) {
            return new FeatureStep(['index' => $cascadeIndex, 'activated' => false], null, 1, false, [], []);
        }

        // Guaranteed from the configured cascade, probabilistic before it.
        $guaranteed = $cascadeIndex >= $this->guaranteedCascade - 1;
        if (!$guaranteed && !$context->rng->chance($this->probability)) {
            return new FeatureStep(
                ['index' => $cascadeIndex, 'activated' => false],
                null,
                1,
                false,
                [],
                ['activated' => false]
            );
        }

        $grid = $context->grid;
        $converted = 0;

        foreach ($this->targetReels as $reel) {
            if (!$grid->has($reel, 0)) {
                continue;
            }
            for ($row = 0; $row < $grid->rowsFor($reel); $row++) {
                if ($grid->symbolAt($reel, $row) === $this->starSymbol) {
                    continue;
                }
                $grid = $grid->withSymbol($reel, $row, $this->goldenSymbol);
                $converted++;
            }
        }

        if ($converted === 0) {
            return new FeatureStep(['index' => $cascadeIndex, 'activated' => false], null, 1, false, [], []);
        }

        return new FeatureStep(
            ['index' => $cascadeIndex, 'activated' => true],
            $grid,
            1,
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::STARTED,
                $converted,
                ['converted' => $converted, 'guaranteed' => $guaranteed]
            )],
            ['converted' => $converted]
        );
    }

    /** True when the configured symbol is the Star Card itself. */
    public function handles(string $symbolId): bool
    {
        return $symbolId === $this->starSymbol;
    }

    /** Kept for a caller that wants to build an empty grid of the right shape. */
    public static function emptyGrid(Grid $grid): Grid
    {
        return $grid;
    }
}
