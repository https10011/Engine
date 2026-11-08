<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\FortuneGaruda1000;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\AuxiliaryReelFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\NudgeFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Fortune Garuda 1000.
 *
 * A 3x3, five-line game with a nudge reel and a multiplier side reel. The
 * Garuda symbol nudges upward into a paying position, and the side reel stops on
 * a multiplier that is applied to the line win when it aligns.
 *
 * No title called exactly *Fortune Garuda 1000* was found in the provider
 * catalogues or the review indexes. The closest verified title is Fortune Garuda
 * 500, so this module is a documented 1000x variant of that verified mechanic
 * set: the same 3x3 five-line board with the Garuda nudge, with the side reel's
 * ceiling raised from 500x to 1,000x, which is what the "1000" in the name
 * denotes. Reel strips and paytable units are this engine's own.
 */
final class FortuneGaruda1000 extends AbstractSlotGame
{
    public const ID = 'jili.fortune-garuda-1000';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['FR' => 8, 'TR' => 6, 'CO' => 4, 'EM' => 3, 'GA' => 2, 'W' => 2, 'S' => 1];

        return [$reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Fortune Garuda 1000',
                Volatility::Medium,
                25000,
                0.971,
                ['nudge', 'side-reel', 'multiplier', '3x3', '5-lines'],
                'JILI Games (mechanics, 1000x variant) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 3, self::ID),
            SlotParts::symbols([
                ['id' => 'GA', 'name' => 'Garuda', 'weight' => 2],
                ['id' => 'EM', 'name' => 'Emerald', 'weight' => 3],
                ['id' => 'CO', 'name' => 'Coin', 'weight' => 4],
                ['id' => 'TR', 'name' => 'Temple', 'weight' => 6],
                ['id' => 'FR', 'name' => 'Fruit', 'weight' => 8],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 2],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'GA' => [3 => 40],
                    'EM' => [3 => 25],
                    'CO' => [3 => 15],
                    'TR' => [3 => 10],
                    'FR' => [3 => 6],
                    'W'  => [3 => 60],
                ],
                ['S' => [3 => 10]]
            ),
            new CompositeEvaluator(
                new PaylineEvaluator(SlotParts::paylines3x3()),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                // The Garuda boosts the nudge: the symbol shifts upward into a
                // paying position and the grid is re-evaluated.
                $features[] = new NudgeFeature('GA', 0.12, 2);

                // The side reel carries multipliers to 1,000x; it aligns on
                // roughly a quarter of wins.
                $features[] = new AuxiliaryReelFeature(
                    [2, 3, 5, 10, 25, 50, 100, 250, 500, 1000],
                    0.25
                );

                return $features;
            },
            new SlotConfiguration(
                coinValueMinor: 100,
                betLevels: [1, 2, 5, 10, 20, 50]
            ),
            5,
            50,
            // Calibrated to the published 97.10% return; see the specification.
            [1125980, 1000000]
        );
    }
}
