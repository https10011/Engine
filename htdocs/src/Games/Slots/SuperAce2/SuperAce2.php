<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\SuperAce2;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\ComboMultiplierFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Feature\WildReplicationFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Super Ace 2.
 *
 * A 5x4, 1,024-ways cascade game in the Super Ace family. Its signature is the
 * Stacked Golden Joker: a multi-cell wild unit that is replicated across the
 * grid, which is what enables the family's consecutive wins. Free-game
 * multipliers are doubled.
 *
 * Verified against the published feature description: 5x4 with 1,024 ways, the
 * stacked golden joker enabling consecutive wins, cascading reels with a combo
 * trail, and doubled multipliers in the free games.
 */
final class SuperAce2 extends AbstractSlotGame
{
    public const ID = 'jili.super-ace-2';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['N' => 9, 'T' => 8, 'J' => 6, 'Q' => 5, 'K' => 4, 'A' => 3, 'SJ' => 1, 'JW' => 1, 'S' => 1];

        return [$reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Super Ace 2',
                Volatility::Medium,
                10000,
                0.97,
                ['ways', 'cascade', 'joker', 'stacked-wild', '5x4', '1024-ways'],
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
                ['id' => 'SJ', 'name' => 'Stacked Golden Joker', 'type' => 'wild', 'weight' => 1],
                ['id' => 'JW', 'name' => 'Joker Wild', 'type' => 'wild', 'weight' => 1],
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
                    'SJ' => [3 => 12, 4 => 50, 5 => 250],
                    'JW' => [3 => 10, 4 => 40, 5 => 200],
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
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['A'],
                        'JW'
                    );
                }

                // The stacked golden joker replicates itself into a run of
                // wilds, which is what sustains consecutive wins.
                $features[] = new WildReplicationFeature('SJ', 'JW', 2, 4, [1, 2, 3, 4]);

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
            15000,
            50,
            // Calibrated to the published 97.00% return; see the specification.
            [1098699, 1000000]
        );
    }
}
