<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\SugarRush1000;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\ClusterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Feature\PositionMultiplierFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Sugar Rush 1000.
 *
 * A 7x7 cluster game. When a winning cluster explodes, the spot it occupied is
 * marked; a second explosion on the same spot adds a multiplier that starts at
 * x2 and doubles to x1,024, and that multiplier applies to every winning
 * combination formed on top of it. Marks and their multipliers persist through
 * the free spins and grow.
 *
 * Verified against the published feature description: a 7x7 grid, clusters of
 * five or more adjacent symbols, marked spots that double their multiplier on a
 * second explosion, ten to thirty free spins for three to seven scatters, and
 * marks that remain in place until the round ends.
 */
final class SugarRush1000 extends AbstractSlotGame
{
    public const ID = 'pragmatic.sugar-rush-1000';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['CI' => 9, 'DI' => 9, 'TR' => 7, 'SQ' => 5, 'ST' => 3, 'HB' => 2, 'LP' => 1];

        return [$reel, $reel, $reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Sugar Rush 1000',
                Volatility::High,
                25000,
                0.96,
                ['cluster', 'tumble', 'multiplier', 'sweet', '7x7'],
                'Pragmatic Play (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 7, self::ID),
            SlotParts::symbols([
                ['id' => 'HB', 'name' => 'Heart', 'weight' => 2],
                ['id' => 'ST', 'name' => 'Star', 'weight' => 3],
                ['id' => 'SQ', 'name' => 'Square', 'weight' => 5],
                ['id' => 'TR', 'name' => 'Triangle', 'weight' => 7],
                ['id' => 'DI', 'name' => 'Diamond', 'weight' => 9],
                ['id' => 'CI', 'name' => 'Circle', 'weight' => 9],
                ['id' => 'LP', 'name' => 'Lollipop', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'HB' => [5 => 2, 8 => 4, 12 => 10, 20 => 30],
                    'ST' => [5 => 2, 8 => 3, 12 => 8, 20 => 20],
                    'SQ' => [5 => 1, 8 => 2, 12 => 5, 20 => 12],
                    'TR' => [5 => 1, 8 => 2, 12 => 4, 20 => 9],
                    'DI' => [5 => 1, 8 => 1, 12 => 3, 20 => 7],
                    'CI' => [5 => 1, 8 => 1, 12 => 3, 20 => 7],
                ],
                ['LP' => [3 => 2, 4 => 5, 5 => 20, 6 => 100, 7 => 500]]
            ),
            new CompositeEvaluator(
                new ClusterEvaluator(5),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    $features[] = new CascadeFeature($configuration->maxCascades);
                }

                // A spot marked twice starts multiplying at x2 and doubles to
                // x1,024. Marks survive between spins only inside the feature,
                // which is what "until the round ends" means here.
                $features[] = new PositionMultiplierFeature(2, 1024, true);

                if ($configuration->freeSpinsEnabled) {
                    $features[] = new FreeSpinsFeature(
                        $configuration->freeSpinAwards,
                        $configuration->freeSpinsRetrigger,
                        $configuration->freeSpinsCap
                    );
                }

                return $features;
            },
            new SlotConfiguration(
                coinValueMinor: 100,
                betLevels: [1, 2, 5, 10, 20, 50],
                cascadesEnabled: true,
                maxCascades: 20,
                freeSpinsEnabled: true,
                freeSpinAwards: [3 => 10, 4 => 12, 5 => 15, 6 => 20, 7 => 30],
                freeSpinsRetrigger: false,
                freeSpinsCap: 30
            ),
            8,
            50,
            // Calibrated to the published 96.00% return; see the specification.
            [904474, 1000000]
        );
    }
}
