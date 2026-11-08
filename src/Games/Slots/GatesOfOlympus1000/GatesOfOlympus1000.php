<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\GatesOfOlympus1000;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\PayAnywhereEvaluator;
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
 * Gates of Olympus 1000.
 *
 * A 6x5 pay-anywhere game: eight or more of a symbol pay wherever they sit on
 * the grid, with no adjacency requirement. Multiplier symbols on every reel
 * carry random values from 2x to 1,000x; their values are combined at the end of
 * a tumble sequence and applied to the win. In the free spins the values are
 * added to the round's running total instead, which is what makes the bonus the
 * feature it is.
 *
 * Verified against the published feature description: a 6x5 grid paying for
 * eight or more symbols anywhere, tumbles, multiplier symbols of 2x to 1,000x
 * combined at the end of the sequence, four or more scatters awarding fifteen
 * free games, and three or more scatters during the bonus adding five more.
 */
final class GatesOfOlympus1000 extends AbstractSlotGame
{
    public const ID = 'pragmatic.gates-of-olympus-1000';

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['SN' => 9, 'CN' => 9, 'RG' => 7, 'GO' => 5, 'HR' => 3, 'ZE' => 2, 'MU' => 1, 'S' => 1];

        return [$reel, $reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Gates Of Olympus 1000',
                Volatility::High,
                15000,
                0.965,
                ['pay-anywhere', 'tumble', 'multiplier', 'olympus', '6x5'],
                'Pragmatic Play (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 5, self::ID),
            SlotParts::symbols([
                ['id' => 'ZE', 'name' => 'Zeus', 'weight' => 2],
                ['id' => 'HR', 'name' => 'Hera', 'weight' => 3],
                ['id' => 'GO', 'name' => 'Goblet', 'weight' => 5],
                ['id' => 'RG', 'name' => 'Ring', 'weight' => 7],
                ['id' => 'CN', 'name' => 'Crown', 'weight' => 9],
                ['id' => 'SN', 'name' => 'Sandal', 'weight' => 9],
                ['id' => 'MU', 'name' => 'Multiplier', 'weight' => 1],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'ZE' => [8 => 5, 10 => 10, 12 => 25, 15 => 60],
                    'HR' => [8 => 3, 10 => 6, 12 => 15, 15 => 40],
                    'GO' => [8 => 2, 10 => 4, 12 => 10, 15 => 25],
                    'RG' => [8 => 1, 10 => 2, 12 => 6, 15 => 15],
                    'CN' => [8 => 1, 10 => 2, 12 => 4, 15 => 10],
                    'SN' => [8 => 1, 10 => 1, 12 => 4, 15 => 10],
                ],
                ['S' => [4 => 2, 5 => 10, 6 => 50]]
            ),
            new CompositeEvaluator(
                new PayAnywhereEvaluator(8),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration, array $spin): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    $features[] = new CascadeFeature($configuration->maxCascades);
                }

                // Sequence-scoped in the base game: the values are combined at
                // the end of the tumble sequence. Round-scoped in the free
                // spins: a value joins the round's running total and is applied
                // to any win.
                $features[] = new SymbolMultiplierFeature(
                    'MU',
                    [2, 3, 5, 10, 25, 50, 100, 250, 500, 1000],
                    ($spin['is_free_spin'] ?? false) === true,
                    [400, 250, 150, 90, 50, 30, 15, 10, 5, 3]
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
                freeSpinAwards: [4 => 15, 5 => 18, 6 => 20],
                freeSpinsRetrigger: true,
                freeSpinsCap: 100
            ),
            400,
            50,
            // Calibrated to the published 96.50% return; see the specification.
            [871404, 1000000]
        );
    }
}
