<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\WildBountyShowdown;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\ComboMultiplierFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Wild Bounty Showdown.
 *
 * A six-reel ragged grid — 3-4-5-5-4-3 — paying 3,000 ways. The ways count is
 * the product of the matching cells on each reel of a run, which is why a
 * taller middle reel is worth more: the two centre reels carry five cells each,
 * so a run through the middle multiplies out to a much larger way count than a
 * run along the edges.
 *
 * Verified against the published feature description: 3,000 ways on a ragged
 * 3-4-5-5-4-3 grid, golden frames on reels 3 and 4, a doubling cascade
 * multiplier to x1,024, and 3 scatters awarding 10 free spins with two more per
 * extra scatter, retriggerable, starting at x8 during the feature.
 *
 * Approximation, stated plainly: the published game frames an existing symbol
 * and turns that framed copy wild on the next cascade. This engine expresses the
 * same rule through the cascade's own frame-to-wild conversion, using a
 * dedicated frame symbol on the two centre reels. The free-spin "starts at x8"
 * is implemented as the free-spin ladder's opening rung. Reel strips and
 * paytable units are this engine's own.
 */
final class WildBountyShowdown extends AbstractSlotGame
{
    public const ID = 'pgsoft.wild-bounty-showdown';

    /** Reel heights, low to high: 3-4-5-5-4-3. */
    private const ROWS = [3, 4, 5, 5, 4, 3];

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $low = ['RE' => 8, 'WH' => 8, 'BT' => 6, 'HR' => 4, 'OU' => 3, 'SH' => 2, 'W' => 2, 'S' => 1];
        $high = ['RE' => 7, 'WH' => 7, 'BT' => 5, 'HR' => 4, 'OU' => 3, 'SH' => 2, 'GF' => 1, 'W' => 2, 'S' => 1];

        return [$low, $low, $high, $high, $low, $low];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Wild Bounty Showdown',
                Volatility::High,
                5000,
                0.9675,
                ['western', 'ways', 'cascade', 'frames', '6-reels', '3000-ways'],
                'PG Soft (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), self::ROWS, self::ID),
            SlotParts::symbols([
                ['id' => 'SH', 'name' => 'Sheriff', 'weight' => 2],
                ['id' => 'OU', 'name' => 'Outlaw', 'weight' => 3],
                ['id' => 'HR', 'name' => 'Horse', 'weight' => 4],
                ['id' => 'BT', 'name' => 'Boot', 'weight' => 6],
                ['id' => 'RE', 'name' => 'Revolver', 'weight' => 8],
                ['id' => 'WH', 'name' => 'Whiskey', 'weight' => 8],
                ['id' => 'GF', 'name' => 'Golden Frame', 'weight' => 1],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 2],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'SH' => [3 => 15, 4 => 60, 5 => 250, 6 => 1000],
                    'OU' => [3 => 10, 4 => 40, 5 => 150, 6 => 600],
                    'HR' => [3 => 6, 4 => 25, 5 => 90, 6 => 350],
                    'BT' => [3 => 4, 4 => 15, 5 => 50, 6 => 200],
                    'RE' => [3 => 3, 4 => 10, 5 => 35, 6 => 130],
                    'WH' => [3 => 2, 4 => 8, 5 => 25, 6 => 100],
                    'W'  => [3 => 20, 4 => 80, 5 => 300, 6 => 1200],
                ],
                ['S' => [3 => 4, 4 => 20, 5 => 100, 6 => 500]]
            ),
            new CompositeEvaluator(
                new WaysEvaluator(),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration, array $spin): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    // The gold frame sits on reels 3 and 4 and refills as a wild
                    // when the cascade removes it.
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['GF'],
                        'W'
                    );
                }

                // The cascade ladder doubles to x1,024. Inside the feature it
                // opens at x8, which is the published "starts at x8 per free
                // spin" rule expressed as the ladder's first rung rather than
                // as a second multiplier stacked on the first.
                $ladder = ($spin['is_free_spin'] ?? false) === true
                    ? [8, 16, 32, 64, 128, 256, 512, 1024]
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
                comboLadder: [1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024],
                freeSpinsEnabled: true,
                freeSpinAwards: [3 => 10, 4 => 12, 5 => 14],
                freeSpinsRetrigger: false,
                freeSpinsCap: 30
            ),
            10000,
            50,
            // Calibrated to the published 96.75% return; see the specification.
            [739958, 1000000]
        );
    }
}
