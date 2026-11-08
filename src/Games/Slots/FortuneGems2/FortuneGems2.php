<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\FortuneGems2;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\AuxiliaryReelFeature;
use CasinoEngine\Frameworks\Slot\Feature\BonusWheelFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Fortune Gems 2.
 *
 * A 3x3, five-line fruit machine with a multiplier reel beside the reels and a
 * Lucky Wheel bonus. The side reel stops on multipliers of 1x to 15x; the
 * scatter starts the wheel, which pays a cash prize from the wheel's own ladder.
 *
 * Verified against the published feature description: a 3x3 board with five
 * lines, a right-hand multiplier reel carrying 1x-15x, and a scatter-triggered
 * Lucky Wheel with prizes from 1x to 200x.
 */
final class FortuneGems2 extends AbstractSlotGame
{
    public const ID = 'jili.fortune-gems-2';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['LM' => 8, 'OR' => 7, 'WM' => 6, 'BE' => 4, 'BAR' => 3, 'D7' => 2, 'W' => 2, 'S' => 1];

        return [$reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Fortune Gems 2',
                Volatility::Medium,
                10000,
                0.97,
                ['fruit', '3x3', '5-lines', 'multiplier-reel', 'lucky-wheel'],
                'JILI Games (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 3, self::ID),
            SlotParts::symbols([
                ['id' => 'D7', 'name' => 'Lucky Seven', 'weight' => 2],
                ['id' => 'BAR', 'name' => 'Bar', 'weight' => 3],
                ['id' => 'BE', 'name' => 'Bell', 'weight' => 4],
                ['id' => 'WM', 'name' => 'Watermelon', 'weight' => 6],
                ['id' => 'OR', 'name' => 'Orange', 'weight' => 7],
                ['id' => 'LM', 'name' => 'Lemon', 'weight' => 8],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 2],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'D7'  => [3 => 30],
                    'BAR' => [3 => 20],
                    'BE'  => [3 => 12],
                    'WM'  => [3 => 8],
                    'OR'  => [3 => 6],
                    'LM'  => [3 => 4],
                    'W'   => [3 => 40],
                ],
                ['S' => [3 => 5]]
            ),
            new CompositeEvaluator(
                new PaylineEvaluator(SlotParts::paylines3x3()),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                // The right-hand multiplier reel.
                $features[] = new AuxiliaryReelFeature([1, 2, 3, 5, 10, 15], 0.6);

                // Three scatters start the Lucky Wheel and pay one of its
                // prizes.
                $features[] = new BonusWheelFeature(
                    'S',
                    [1, 3, 5, 8, 10, 15, 20, 30, 50, 100, 200],
                    3
                );

                return $features;
            },
            new SlotConfiguration(
                coinValueMinor: 100,
                betLevels: [1, 2, 5, 10, 20, 50]
            ),
            5,
            50,
            // Calibrated to the published 97.00% return; see the specification.
            [2366431, 1000000]
        );
    }
}
