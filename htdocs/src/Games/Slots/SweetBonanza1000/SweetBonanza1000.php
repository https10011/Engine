<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\SweetBonanza1000;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\ClusterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Feature\SymbolMultiplierFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Sweet Bonanza 1000.
 *
 * A 6x5 cluster game in the sweet family. Candy bombs land with random values up
 * to 1,000x, stay on screen through the tumbles, are added together, and are
 * applied to the total win of the sequence.
 *
 * Verified against the published feature description: a 6x5 grid, prizes for
 * eight or more of a symbol, tumbles, four lollipops awarding ten free spins,
 * candy-bomb multipliers up to 1,000x that remain on screen and are added
 * together, and five further free spins when three or more lollipops land
 * during the bonus.
 *
 * The published rule is pay-anywhere for eight or more symbols. This engine
 * evaluates the sweet family as clusters — eight or more orthogonally adjacent
 * — which is the stricter form, and the choice is recorded in the game
 * specification.
 */
final class SweetBonanza1000 extends AbstractSlotGame
{
    public const ID = 'pragmatic.sweet-bonanza-1000';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['CH' => 9, 'PL' => 9, 'WM' => 7, 'GR' => 5, 'BN' => 3, 'AP' => 2, 'CB' => 1, 'LP' => 1];

        return [$reel, $reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Sweet Bonanza 1000',
                Volatility::High,
                25000,
                0.9653,
                ['cluster', 'tumble', 'multiplier', 'sweet', '6x5'],
                'Pragmatic Play (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 5, self::ID),
            SlotParts::symbols([
                ['id' => 'AP', 'name' => 'Apple', 'weight' => 2],
                ['id' => 'BN', 'name' => 'Banana', 'weight' => 3],
                ['id' => 'GR', 'name' => 'Grape', 'weight' => 5],
                ['id' => 'WM', 'name' => 'Watermelon', 'weight' => 7],
                ['id' => 'PL', 'name' => 'Plum', 'weight' => 9],
                ['id' => 'CH', 'name' => 'Cherry', 'weight' => 9],
                ['id' => 'CB', 'name' => 'Candy Bomb', 'weight' => 1],
                ['id' => 'LP', 'name' => 'Lollipop', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'AP' => [8 => 3, 10 => 6, 12 => 12, 15 => 30],
                    'BN' => [8 => 2, 10 => 4, 12 => 8, 15 => 20],
                    'GR' => [8 => 1, 10 => 2, 12 => 5, 15 => 12],
                    'WM' => [8 => 1, 10 => 2, 12 => 4, 15 => 9],
                    'PL' => [8 => 1, 10 => 1, 12 => 3, 15 => 7],
                    'CH' => [8 => 1, 10 => 1, 12 => 3, 15 => 7],
                ],
                ['LP' => [4 => 2, 5 => 10, 6 => 50]]
            ),
            new CompositeEvaluator(
                new ClusterEvaluator(8),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    $features[] = new CascadeFeature($configuration->maxCascades);
                }

                // The ladder is heavily skewed: the x1,000 bomb is a long
                // shot, not one value among nine.
                $features[] = new SymbolMultiplierFeature(
                    'CB',
                    [2, 5, 10, 25, 50, 100, 250, 500, 1000],
                    false,
                    [400, 250, 150, 90, 50, 30, 15, 10, 5]
                );

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
                freeSpinAwards: [4 => 10, 5 => 15, 6 => 20],
                freeSpinsRetrigger: true,
                freeSpinsCap: 100
            ),
            3,
            50,
            // Calibrated to the published 96.53% return; see the specification.
            [1028689, 1000000]
        );
    }
}
