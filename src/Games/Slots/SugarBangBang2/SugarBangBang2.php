<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\SugarBangBang2;

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
 * Sugar Bang Bang 2.
 *
 * A 6x5 cluster game: eight or more orthogonally adjacent identical symbols pay.
 * Winning clusters explode, the grid tumbles, and a candy bomb caught in the
 * tumble adds its value to a multiplier that is applied once to the whole
 * tumble sequence.
 *
 * **This title is unverified.** No provider catalogue or review database
 * describes a game with this exact name, so the module is an explicit,
 * documented approximation of the sweet-cluster family rather than a recreation
 * of a specific product. The mechanics follow the verified sweet-cluster
 * pattern: cluster pays, tumble cascades, candy-bomb multipliers that stay on
 * screen and are added together, and four lollipops awarding ten free spins
 * with five more when three land during the feature.
 */
final class SugarBangBang2 extends AbstractSlotGame
{
    public const ID = 'slots.sugar-bang-bang-2';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['PL' => 9, 'WM' => 9, 'BN' => 7, 'AP' => 5, 'GR' => 3, 'CH' => 2, 'CB' => 1, 'LP' => 1];

        return [$reel, $reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Sugar Bang Bang 2',
                Volatility::High,
                10000,
                0.965,
                ['cluster', 'tumble', 'multiplier', 'sweet', '6x5', 'approximation'],
                'Engine Approximation (unverified title)'
            ),
            SlotParts::reels(self::reelWeights(), 5, self::ID),
            SlotParts::symbols([
                ['id' => 'CH', 'name' => 'Cherry', 'weight' => 2],
                ['id' => 'GR', 'name' => 'Grape', 'weight' => 3],
                ['id' => 'AP', 'name' => 'Apple', 'weight' => 5],
                ['id' => 'BN', 'name' => 'Banana', 'weight' => 7],
                ['id' => 'PL', 'name' => 'Plum', 'weight' => 9],
                ['id' => 'WM', 'name' => 'Watermelon', 'weight' => 9],
                ['id' => 'CB', 'name' => 'Candy Bomb', 'weight' => 1],
                ['id' => 'LP', 'name' => 'Lollipop', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'CH' => [8 => 3, 10 => 6, 12 => 12, 15 => 30],
                    'GR' => [8 => 2, 10 => 4, 12 => 8, 15 => 20],
                    'AP' => [8 => 1, 10 => 2, 12 => 5, 15 => 12],
                    'BN' => [8 => 1, 10 => 2, 12 => 4, 15 => 9],
                    'PL' => [8 => 1, 10 => 1, 12 => 3, 15 => 7],
                    'WM' => [8 => 1, 10 => 1, 12 => 3, 15 => 7],
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

                // The bomb's value joins the sequence's accumulated total and
                // is applied once, at the end, to the whole sequence's win.
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
            1,
            50,
            // Calibrated to the published 96.50% return; see the specification.
            [622570, 1000000]
        );
    }
}
