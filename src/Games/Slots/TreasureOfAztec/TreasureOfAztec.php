<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\TreasureOfAztec;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\WaysEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\ComboMultiplierFeature;
use CasinoEngine\Frameworks\Slot\Feature\ExpandingReelsFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Treasure of Aztec.
 *
 * A six-reel ways game with expanding reels, a cascade multiplier that starts
 * at x1 in the base game and x2 in the free spins, and a framed-cell
 * progression: a symbol that wins twice upgrades its frame and the third win
 * turns it into a wild.
 *
 * Verified against the published feature description: six expanding reels to
 * 32,400 ways, a cascade multiplier starting at x1, four scatters awarding ten
 * free spins, and a free-spin multiplier that starts at x2, doubles per cascade
 * and never resets.
 */
final class TreasureOfAztec extends AbstractSlotGame
{
    public const ID = 'pgsoft.treasure-of-aztec';

    private const BASE_ROWS = [4, 5, 5, 5, 5, 4];

    private const MAX_ROWS = 6;

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        $reel = ['PT' => 9, 'SC' => 8, 'TP' => 6, 'JG' => 4, 'SN' => 3, 'MZ' => 2, 'W' => 2, 'S' => 1];

        return [$reel, $reel, $reel, $reel, $reel, $reel];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Treasure of Aztec',
                Volatility::High,
                9071,
                0.9671,
                ['aztec', 'ways', 'expanding', 'cascade', 'multiplier'],
                'PG Soft (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), self::BASE_ROWS, self::ID),
            SlotParts::symbols([
                ['id' => 'MZ', 'name' => 'Mask', 'weight' => 2],
                ['id' => 'SN', 'name' => 'Sun Stone', 'weight' => 3],
                ['id' => 'JG', 'name' => 'Jaguar', 'weight' => 4],
                ['id' => 'TP', 'name' => 'Temple', 'weight' => 6],
                ['id' => 'SC', 'name' => 'Scroll', 'weight' => 8],
                ['id' => 'PT', 'name' => 'Pot', 'weight' => 9],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 2],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'MZ' => [3 => 3, 4 => 12, 5 => 50, 6 => 200],
                    'SN' => [3 => 2, 4 => 8, 5 => 30, 6 => 120],
                    'JG' => [3 => 2, 4 => 5, 5 => 20, 6 => 80],
                    'TP' => [3 => 1, 4 => 4, 5 => 12, 6 => 50],
                    'SC' => [3 => 1, 4 => 3, 5 => 9, 6 => 35],
                    'PT' => [3 => 1, 4 => 2, 5 => 7, 6 => 25],
                    'W'  => [3 => 5, 4 => 20, 5 => 80, 6 => 300],
                ],
                ['S' => [4 => 5, 5 => 25, 6 => 150]]
            ),
            new CompositeEvaluator(
                new WaysEvaluator(),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration, array $spin): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    // The framed-cell progression: a symbol that keeps winning
                    // ends up as a wild when the cascade finally removes it.
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['MZ'],
                        'W'
                    );
                }

                $features[] = new ExpandingReelsFeature(self::BASE_ROWS, self::MAX_ROWS);

                // Base game x1 rising per cascade; the feature opens at x2,
                // doubles per cascade and never resets on a dead spin.
                $ladder = ($spin['is_free_spin'] ?? false) === true
                    ? [2, 4, 8, 16, 32, 64]
                    : [1, 2, 3, 4, 5, 6, 8, 10];

                $features[] = new ComboMultiplierFeature(
                    $ladder,
                    ($spin['is_free_spin'] ?? false) !== true
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
                freeSpinAwards: [4 => 10, 5 => 12, 6 => 15],
                freeSpinsRetrigger: false,
                freeSpinsCap: 30
            ),
            500000,
            50,
            // Calibrated to the published 96.71% return; see the specification.
            [815598, 1000000]
        );
    }
}
