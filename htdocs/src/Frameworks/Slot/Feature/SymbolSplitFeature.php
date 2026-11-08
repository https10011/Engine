<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Symbol splitting.
 *
 * Fortune Gems 3's distinctive mechanic: a winning symbol *splits* into
 * multiple paying units, which multiplies that win. The split is applied as a
 * multiplier contribution to the evaluation that produced it, because a split
 * unit is another copy of the same win rather than another line.
 *
 * The split chance and the maximum number of units are configuration, since the
 * provider does not publish its own rules.
 */
final class SymbolSplitFeature implements FeatureInterface
{
    /**
     * @param list<string> $splitSymbols Symbols eligible to split
     */
    public function __construct(
        private readonly array $splitSymbols,
        private readonly float $probability = 0.15,
        private readonly int $maxUnits = 3,
        private readonly string $featureType = 'symbol_split'
    ) {
        if ($maxUnits < 2) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                'A split must produce at least two units.'
            );
        }
    }

    public function type(): string
    {
        return $this->featureType;
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        if (!$context->evaluation->isWin()) {
            return FeatureStep::idle([]);
        }

        $splits = 0;
        foreach ($context->evaluation->wins as $win) {
            if (!in_array($win->symbolId, $this->splitSymbols, true)) {
                continue;
            }
            if (!$context->rng->chance($this->probability)) {
                continue;
            }
            $splits++;
        }

        if ($splits === 0) {
            return FeatureStep::idle([]);
        }

        // One split multiplies the step's win by the number of units a split
        // produces. Several splits stack, bounded by the configured maximum, so
        // a run of splits cannot produce an unbounded multiplier.
        $multiplier = min($this->maxUnits ** $splits, $this->maxUnits ** 4);

        return new FeatureStep(
            [],
            null,
            max(1, $multiplier),
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $multiplier,
                ['splits' => $splits, 'multiplier' => $multiplier]
            )],
            ['splits' => $splits, 'multiplier' => $multiplier]
        );
    }
}
