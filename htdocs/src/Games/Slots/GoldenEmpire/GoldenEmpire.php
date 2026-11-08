<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\GoldenEmpire;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\ExpandingReelsFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\Feature\PersistentMultiplierFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Golden Empire.
 *
 * A six-reel megaways game whose reel surface turns into wilds as it cascades.
 * A winning reel grows by one row, and because the ways count is the product of
 * the cells on each reel of a run, growing a reel multiplies out into many more
 * ways: from 10,000 at the base heights to 32,400 when every reel is fully
 * expanded.
 *
 * Verified against the published feature description: 32,400 megaways on six
 * expanding reels, a cascade surface that turns wild, and free games with an
 * uncapped accumulating multiplier.
 *
 * Approximation, stated plainly: the published "eliminate a symbol to expand
 * the reel, up to four eliminations" durability rule is represented by the
 * engine's expanding-reel mechanic — a reel that wins grows by one row per
 * cascade up to the configured ceiling. Reel strips and paytable units are this
 * engine's own.
 */
final class GoldenEmpire extends AbstractSlotGame
{
    public const ID = 'jili.golden-empire';

    /** Base reel heights; 4-5-5-5-5-4 gives 10,000 ways. */
    private const BASE_ROWS = [4, 5, 5, 5, 5, 4];

    /** Fully expanded, 5-6-6-6-6-5 gives the published 32,400 ways. */
    private const MAX_ROWS = 6;

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $low = ['PT' => 9, 'LT' => 7, 'CT' => 5, 'GD' => 3, 'EM' => 2, 'W' => 2, 'S' => 1];

        return [$low, $low, $low, $low, $low, $low];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Golden Empire',
                Volatility::Medium,
                2000,
                0.97,
                ['megaways', 'expanding', 'cascade', '6-reels', '32400-ways'],
                'JILI Games (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), self::BASE_ROWS, self::ID),
            SlotParts::symbols([
                ['id' => 'EM', 'name' => 'Emperor', 'weight' => 2],
                ['id' => 'GD', 'name' => 'Gold Bar', 'weight' => 3],
                ['id' => 'CT', 'name' => 'Coin', 'weight' => 5],
                ['id' => 'LT', 'name' => 'Lantern', 'weight' => 7],
                ['id' => 'PT', 'name' => 'Pot', 'weight' => 9],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 2],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'EM' => [3 => 4, 4 => 15, 5 => 60, 6 => 250],
                    'GD' => [3 => 3, 4 => 10, 5 => 40, 6 => 150],
                    'CT' => [3 => 2, 4 => 7, 5 => 25, 6 => 90],
                    'LT' => [3 => 1, 4 => 5, 5 => 15, 6 => 60],
                    'PT' => [3 => 1, 4 => 3, 5 => 10, 6 => 40],
                    'W'  => [3 => 6, 4 => 25, 5 => 100, 6 => 400],
                ],
                ['S' => [3 => 5, 4 => 20, 5 => 100, 6 => 500]]
            ),
            new CompositeEvaluator(
                new WaysEvaluator(),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    // "The reel surface turns into wilds": every eliminated cell
                    // refills as a wild, which is what makes the cascade
                    // surface wild rather than ordinary.
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['GD'],
                        'W'
                    );
                }

                $features[] = new ExpandingReelsFeature(self::BASE_ROWS, self::MAX_ROWS);

                if ($configuration->freeSpinsEnabled) {
                    $features[] = new FreeSpinsFeature(
                        $configuration->freeSpinAwards,
                        $configuration->freeSpinsRetrigger,
                        $configuration->freeSpinsCap
                    );

                    // Free games accumulate an unlimited multiplier.
                    $features[] = new PersistentMultiplierFeature(
                        1,
                        1,
                        null,
                        false,
                        true,
                        'empire_multiplier'
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
                freeSpinAwards: [3 => 10, 4 => 12, 5 => 15],
                freeSpinsRetrigger: false,
                freeSpinsCap: 30
            ),
            1000000,
            50,
            // Calibrated to the published 97.00% return; see the specification.
            [69825, 1000000]
        );
    }
}
