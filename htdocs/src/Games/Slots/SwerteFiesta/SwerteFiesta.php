<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\SwerteFiesta;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\ComboMultiplierFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Swerte Fiesta.
 *
 * A 5x3, 243-ways game with a wild, a paying scatter and free spins that carry a
 * doubling win multiplier.
 *
 * **This title is unverified.** No provider catalogue or review database
 * describes a game with this exact name; "swerte" is Tagalog for luck, so the
 * title is plausibly a Philippine-market slot, and the module is an explicit,
 * documented approximation using the most common 5x3 ways structure of that
 * market segment rather than a recreation of a specific product.
 */
final class SwerteFiesta extends AbstractSlotGame
{
    public const ID = 'slots.swerte-fiesta';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['CO' => 9, 'MA' => 8, 'GR' => 6, 'FL' => 5, 'DI' => 3, 'CR' => 2, 'W' => 1, 'S' => 1];

        return [$reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Swerte Fiesta',
                Volatility::Medium,
                5000,
                0.965,
                ['fiesta', 'ways', '243-ways', '5x3', 'approximation'],
                'Engine Approximation (unverified title)'
            ),
            SlotParts::reels(self::reelWeights(), 3, self::ID),
            SlotParts::symbols([
                ['id' => 'CR', 'name' => 'Crown', 'weight' => 2],
                ['id' => 'DI', 'name' => 'Diamond', 'weight' => 3],
                ['id' => 'FL', 'name' => 'Flower', 'weight' => 5],
                ['id' => 'GR', 'name' => 'Guitar', 'weight' => 6],
                ['id' => 'MA', 'name' => 'Mango', 'weight' => 8],
                ['id' => 'CO', 'name' => 'Coconut', 'weight' => 9],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 1],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'CR' => [3 => 20, 4 => 80, 5 => 300],
                    'DI' => [3 => 12, 4 => 45, 5 => 160],
                    'FL' => [3 => 8, 4 => 30, 5 => 100],
                    'GR' => [3 => 5, 4 => 20, 5 => 70],
                    'MA' => [3 => 3, 4 => 12, 5 => 40],
                    'CO' => [3 => 2, 4 => 8, 5 => 25],
                    'W'  => [3 => 30, 4 => 120, 5 => 500],
                ],
                ['S' => [3 => 5, 4 => 20, 5 => 100]]
            ),
            new CompositeEvaluator(
                new WaysEvaluator(),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration, array $spin): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->freeSpinsEnabled) {
                    $features[] = new FreeSpinsFeature(
                        $configuration->freeSpinAwards,
                        $configuration->freeSpinsRetrigger,
                        $configuration->freeSpinsCap
                    );

                    // The feature's win multiplier doubles up the ladder, and
                    // applies only inside the feature.
                    if (($spin['is_free_spin'] ?? false) === true) {
                        $features[] = new ComboMultiplierFeature([2, 4, 8, 16, 32], true);
                    }
                }

                return $features;
            },
            new SlotConfiguration(
                coinValueMinor: 100,
                betLevels: [1, 2, 5, 10, 20, 50],
                freeSpinsEnabled: true,
                freeSpinAwards: [3 => 10, 4 => 12, 5 => 15],
                freeSpinsRetrigger: false,
                freeSpinsCap: 30
            ),
            40,
            50,
            // Calibrated to the published 96.50% return; see the specification.
            [920091, 1000000]
        );
    }
}
