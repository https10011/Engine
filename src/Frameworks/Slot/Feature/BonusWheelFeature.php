<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Slot\Feature;

use CasinoEngine\Core\Contract\FeatureTransition;

/**
 * The Lucky Wheel bonus.
 *
 * Fortune Gems 2's mechanic: a scatter starts a wheel round, and the wheel
 * stops on a cash prize from a configured ladder. The wheel is modelled as a
 * weighted pick over the ladder, and the prize is a multiplier of the bet, so
 * the module converts it into an outcome multiplier and the framework never
 * touches currency.
 *
 * The trigger count is configuration because the provider's own threshold is
 * published only as "scatter triggers the wheel".
 */
final class BonusWheelFeature implements FeatureInterface
{
    /**
     * @param list<int> $prizes Cash prizes as multipliers of the bet
     */
    public function __construct(
        private readonly string $triggerSymbol,
        private readonly array $prizes,
        private readonly int $triggerCount = 3,
        private readonly string $featureType = 'bonus_wheel'
    ) {
    }

    public function type(): string
    {
        return $this->featureType;
    }

    public function apply(FeatureContext $context): FeatureStep
    {
        $count = $context->grid->countOf($this->triggerSymbol);
        if ($count < $this->triggerCount || $this->prizes === []) {
            return FeatureStep::idle([]);
        }

        $prize = $this->prizes[$context->rng->int(0, count($this->prizes) - 1)];

        return new FeatureStep(
            [],
            null,
            max(1, $prize),
            false,
            [new FeatureTransition(
                $this->type(),
                FeatureTransition::STARTED,
                $prize,
                ['prize_multiplier' => $prize, 'trigger_count' => $count]
            )],
            ['prize_multiplier' => $prize]
        );
    }
}
