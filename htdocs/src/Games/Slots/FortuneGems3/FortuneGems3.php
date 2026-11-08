<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\FortuneGems3;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\JackpotFeature;
use CasinoEngine\Frameworks\Slot\Feature\SymbolSplitFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Fortune Gems 3.
 *
 * A 3x3, five-line fruit machine. Its distinctive rule is the symbol split: a
 * winning premium symbol can split into extra paying units, which multiplies
 * that line's win. The split is drawn from the engine's RNG, so it is part of
 * the game's mathematics rather than a display effect.
 *
 * Verified against the published feature description: a 3x3 board with five
 * connections, a multiplier on wins, and the symbol-split rule. Reel strips,
 * split frequency and paytable units are this engine's own.
 */
final class FortuneGems3 extends AbstractSlotGame
{
    public const ID = 'jili.fortune-gems-3';

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
                'Fortune Gems 3',
                Volatility::High,
                10125,
                0.97,
                ['fruit', '3x3', '5-lines', 'split', 'multiplier'],
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
                ['S' => [3 => 10]]
            ),
            new CompositeEvaluator(
                new PaylineEvaluator(SlotParts::paylines3x3()),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration, array $spinConfiguration = []): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                // Every winning premium can split into up to three units, so a
                // three-of-a-kind pays as much as three times its table value.
                $features[] = new SymbolSplitFeature(
                    ['D7', 'BAR', 'BE', 'WM', 'OR', 'LM'],
                    0.25,
                    3
                );

                // The advertised four-tier jackpot. Only added when an
                // administrator has configured a jackpot probability.
                if (((float) ($spinConfiguration['jackpot_probability'] ?? 0.0)) > 0.0) {
                    $features[] = new JackpotFeature();
                }

                return $features;
            },
            new SlotConfiguration(
                coinValueMinor: 100,
                betLevels: [1, 2, 5, 10, 20, 50]
            ),
            5,
            50,
            // Calibrated to the published 97.00% return; see the specification.
            [1364506, 1000000]
        );
    }
}
