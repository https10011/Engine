<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * Wild replication.
 *
 * Super Ace's Big Joker: a wild that, when it participates, spawns one to four
 * additional wilds onto the other reels. The number is drawn from the injected
 * RNG, so the replication is random rather than fixed, and the target reels
 * exclude reel 0 so the spawn cannot create a win out of nothing on the first
 * reel.
 *
 * Replication is bounded by `$maxWilds` and by the cells actually available, so
 * a nearly-full grid cannot be over-written.
 */
final class WildReplicationFeature implements FeatureInterface, SpinLifecycleInterface
{
    /**
     * @param list<int> $targetReels Reels the spawned wilds may land on
     */
    public function __construct(
        private readonly string $sourceSymbol,
        private readonly string $wildSymbol,
        private readonly int $minWilds = 1,
        private readonly int $maxWilds = 4,
        private readonly array $targetReels = [1, 2, 3, 4],
        private readonly string $featureType = 'wild_replication'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    public function onSpinStart(array $state): array
    {
        return ['done' => false];
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        // Replication happens once per spin. A feature that replicated on every
        // cascade step would carpet the grid with wilds and turn a bounded
        // bonus into an unbounded one.
        if (($context->state['done'] ?? false) === true) {
            return new FeatureStep(['done' => true], null, 1, false, [], ['already_done' => true]);
        }

        $sources = $context->grid->positionsOfSymbol($this->sourceSymbol);
        if ($sources === []) {
            return new FeatureStep(['done' => false], null, 1, false, [], ['no_source' => true]);
        }

        $count = $context->rng->int($this->minWilds, max($this->minWilds, $this->maxWilds));
        $grid = $context->grid;
        $placed = 0;

        for ($i = 0; $i < $count; $i++) {
            $reel = $this->targetReels[$context->rng->int(0, max(0, count($this->targetReels) - 1))] ?? null;
            if ($reel === null || !$grid->has($reel, 0)) {
                continue;
            }

            $row = $context->rng->int(0, $grid->rowsFor($reel) - 1);
            if (!$grid->has($reel, $row) || $grid->symbolAt($reel, $row) === $this->wildSymbol) {
                continue;
            }

            $grid = $grid->withSymbol($reel, $row, $this->wildSymbol);
            $placed++;
        }

        if ($placed === 0) {
            return new FeatureStep(['done' => true], null, 1, false, [], ['placed' => 0]);
        }

        return new FeatureStep(
            ['done' => true],
            $grid,
            1,
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::UPDATED,
                $placed,
                ['placed' => $placed]
            )],
            ['placed' => $placed]
        );
    }
}
