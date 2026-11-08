<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\SuperAceDeluxe;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\ComboMultiplierFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Feature\StarCardFeature;
use CasinoEngine\Frameworks\Slot\Feature\WildReplicationFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Super Ace Deluxe.
 *
 * A 5x4, 1,024-ways cascade game in the Super Ace family, with two additions:
 * a Star Card that converts the middle reels' symbols into golden symbols from
 * the second cascade onward and is guaranteed from the fifth, and a Big Joker
 * that replicates itself into up to four wilds.
 *
 * Verified against the published feature description: 5x4 with 1,024 ways, the
 * extended combo trail x1-x10 in the base game and x2-x20 in free spins, the
 * Star Card's second-to-fourth-cascade window with a guarantee from the fifth,
 * golden symbols that become Joker Wilds when eliminated, the Big Joker's
 * replication of one to four wilds, and ten free games at three scatters.
 */
final class SuperAceDeluxe extends AbstractSlotGame
{
    public const ID = 'jili.super-ace-deluxe';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['N' => 9, 'T' => 8, 'J' => 6, 'Q' => 5, 'K' => 4, 'A' => 3, 'ST' => 1, 'JW' => 1, 'BJ' => 1, 'S' => 1];

        return [$reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Super Ace Deluxe',
                Volatility::Medium,
                10000,
                0.97,
                ['ways', 'cascade', 'starcard', 'joker', '5x4', '1024-ways'],
                'JILI Games (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 4, self::ID),
            SlotParts::symbols([
                ['id' => 'A', 'name' => 'Ace', 'weight' => 3],
                ['id' => 'K', 'name' => 'King', 'weight' => 4],
                ['id' => 'Q', 'name' => 'Queen', 'weight' => 5],
                ['id' => 'J', 'name' => 'Jack', 'weight' => 6],
                ['id' => 'T', 'name' => 'Ten', 'weight' => 8],
                ['id' => 'N', 'name' => 'Nine', 'weight' => 9],
                ['id' => 'ST', 'name' => 'Star Card', 'weight' => 1],
                ['id' => 'JW', 'name' => 'Joker Wild', 'type' => 'wild', 'weight' => 1],
                ['id' => 'BJ', 'name' => 'Big Joker', 'type' => 'wild', 'weight' => 1],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'A'  => [3 => 5, 4 => 20, 5 => 90],
                    'K'  => [3 => 4, 4 => 15, 5 => 60],
                    'Q'  => [3 => 3, 4 => 10, 5 => 40],
                    'J'  => [3 => 2, 4 => 7, 5 => 25],
                    'T'  => [3 => 2, 4 => 5, 5 => 18],
                    'N'  => [3 => 1, 4 => 4, 5 => 12],
                    'JW' => [3 => 10, 4 => 40, 5 => 200],
                    'BJ' => [3 => 10, 4 => 40, 5 => 200],
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

                if ($configuration->cascadesEnabled) {
                    // Golden symbols become Joker Wilds when eliminated.
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['A'],
                        'JW'
                    );
                }

                // The Star Card converts the middle reels from the second
                // cascade and is guaranteed from the fifth.
                $features[] = new StarCardFeature('ST', 'A', [1, 2, 3], 2, 5, 0.35);

                // The Big Joker replicates into one to four wilds.
                $features[] = new WildReplicationFeature('BJ', 'JW', 1, 4, [1, 2, 3, 4]);

                // Extended combo trail, steeper inside the feature.
                $ladder = ($spin['is_free_spin'] ?? false) === true
                    ? [2, 4, 6, 10, 20]
                    : $configuration->comboLadder;

                if ($configuration->comboMultiplierEnabled) {
                    $features[] = new ComboMultiplierFeature($ladder, true);
                }

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
                comboMultiplierEnabled: true,
                comboLadder: [1, 2, 3, 5, 10],
                freeSpinsEnabled: true,
                freeSpinAwards: [3 => 10, 4 => 12, 5 => 15],
                freeSpinsRetrigger: false,
                freeSpinsCap: 30
            ),
            3000,
            50,
            // Calibrated to the published 97.00% return; see the specification.
            [949149, 1000000]
        );
    }
}
