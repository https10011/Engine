<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\MahjongHit;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\ComboMultiplierFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Feature\ScatterPrizeLadderFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Mahjong Hit.
 *
 * A 5x5 ways grid with cascading tiles, a gold-plated top tile that turns into
 * a wild when it is eliminated, a progressive win multiplier, and 10 free spins
 * at three scatters with a steeper multiplier ladder inside the feature.
 *
 * **This title is unverified.** No provider catalogue or review database
 * describes a game with this exact name, so the module is an explicit,
 * documented approximation of the Mahjong-family mechanics rather than a
 * recreation of a specific product. The mechanics are modelled on the verified
 * Mahjong Ways (PG Soft) and Mahjong Wins Super Scatter (Pragmatic Play)
 * titles: gold-tile-to-wild cascades, a progressive win multiplier that
 * steepens in free spins, and an optional super-scatter prize ladder. The
 * metadata names the provider as an engine approximation so the status is
 * visible in the API as well as here.
 */
final class MahjongHit extends AbstractSlotGame
{
    public const ID = 'slots.mahjong-hit';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $low = ['T5' => 9, 'T4' => 7, 'T3' => 5, 'T2' => 3, 'T1' => 2, 'W' => 1, 'S' => 1];

        return [$low, $low, $low, $low, $low];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Mahjong Hit',
                Volatility::High,
                100000,
                0.9695,
                ['mahjong', 'ways', 'cascade', 'multiplier', '5x5', 'approximation'],
                'Engine Approximation (unverified title)'
            ),
            SlotParts::reels(self::reelWeights(), 5, self::ID),
            SlotParts::symbols([
                ['id' => 'T1', 'name' => 'Dragon Tile', 'weight' => 2],
                ['id' => 'T2', 'name' => 'Bamboo Tile', 'weight' => 3],
                ['id' => 'T3', 'name' => 'Circle Tile', 'weight' => 5],
                ['id' => 'T4', 'name' => 'Character Tile', 'weight' => 7],
                ['id' => 'T5', 'name' => 'Flower Tile', 'weight' => 9],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 1],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
                ['id' => 'SS', 'name' => 'Super Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'T1' => [3 => 6, 4 => 25, 5 => 120],
                    'T2' => [3 => 4, 4 => 15, 5 => 70],
                    'T3' => [3 => 3, 4 => 10, 5 => 40],
                    'T4' => [3 => 2, 4 => 6, 5 => 25],
                    'T5' => [3 => 1, 4 => 4, 5 => 15],
                    'W'  => [3 => 10, 4 => 40, 5 => 200],
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
                    // The dragon tile is gold-plated: eliminated, it refills as
                    // a wild.
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['T1'],
                        'W'
                    );
                }

                // Base-game ladder x1, x2, x3, x5; free spins use the steeper
                // x2, x4, x6, x10 ladder.
                $features[] = new ComboMultiplierFeature(
                    ($spin['is_free_spin'] ?? false) === true ? [2, 4, 6, 10] : [1, 2, 3, 5],
                    true
                );

                if ($configuration->freeSpinsEnabled) {
                    $features[] = new FreeSpinsFeature(
                        $configuration->freeSpinAwards,
                        $configuration->freeSpinsRetrigger,
                        $configuration->freeSpinsCap
                    );
                }

                if (($configuration->extra['super_scatter_enabled'] ?? false) === true) {
                    $features[] = new ScatterPrizeLadderFeature('SS', [3 => 5000, 4 => 10000, 5 => 100000]);
                }

                return $features;
            },
            new SlotConfiguration(
                coinValueMinor: 100,
                betLevels: [1, 2, 5, 10, 20, 50],
                cascadesEnabled: true,
                maxCascades: 20,
                freeSpinsEnabled: true,
                freeSpinAwards: [3 => 10, 4 => 12, 5 => 15],
                freeSpinsRetrigger: false,
                freeSpinsCap: 30,
                // The super scatter ladder is off by default; the configuration
                // turns it on, which is why it is an extra key rather than a
                // property of the definition.
                extra: ['super_scatter_enabled' => false]
            ),
            25000,
            50,
            // Calibrated to the published 96.95% return; see the specification.
            [1053885, 1000000]
        );
    }
}
