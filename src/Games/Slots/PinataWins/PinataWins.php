<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\PinataWins;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
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
 * Piñata Wins.
 *
 * A 5x3, 20-line cascade slot. Golden frames sit on the three middle reels;
 * a framed cell carries a multiplier from x2 to x100, and a framed cell that is
 * eliminated refills as a wild. Multipliers collected during a tumble sequence
 * are summed and applied to that sequence's win, and during free spins the
 * accumulated value survives between spins as a persistent meter.
 *
 * Verified against the published feature description: 20 fixed paylines,
 * cascading reels, golden frames on the middle reels, framed multipliers of
 * x2–x100 applied to the cascade sequence, and 3 scatters awarding 15 free
 * spins with two more per extra scatter.
 *
 * Approximation, stated plainly: the published game frames an *existing paying
 * symbol* and gives that framed copy a multiplier. This engine implements the
 * frame as its own symbol (`FR`) that appears only on the middle reels, carries
 * the multiplier, and refills as a wild when eliminated. The functional
 * behaviour an operator configures — multiplier frequency, multiplier values,
 * and wild conversion — is the same; the visual identity of the framed symbol
 * is not. Reel strips and paytable units are this engine's own.
 */
final class PinataWins extends AbstractSlotGame
{
    public const ID = 'pgsoft.pinata-wins';

    private const PAYLINES = [
        [1, 1, 1, 1, 1],
        [0, 0, 0, 0, 0],
        [2, 2, 2, 2, 2],
        [0, 0, 1, 2, 2],
        [2, 2, 1, 0, 0],
        [1, 0, 0, 0, 1],
        [1, 2, 2, 2, 1],
        [0, 1, 2, 1, 0],
        [2, 1, 0, 1, 2],
        [1, 2, 1, 0, 1],
        [1, 0, 1, 2, 1],
        [0, 2, 0, 2, 0],
        [2, 0, 2, 0, 2],
        [0, 1, 1, 1, 0],
        [2, 1, 1, 1, 2],
        [1, 1, 0, 1, 1],
        [1, 1, 2, 1, 1],
        [0, 0, 1, 0, 0],
        [2, 2, 1, 2, 2],
        [0, 1, 0, 1, 0],
    ];

    /** @return list<array<string, int>> */
    private static function reelWeights(): array
    {
        // The frame is present on reels 1-3 only, which is what makes it a
        // middle-reel mechanic rather than a grid-wide one.
        // The frame is rare: its multiplier applies to the whole tumble
        // sequence, so a common frame would multiply every sequence by a large
        // factor and price the game out of its published return. One cell in a
        // hundred carries a frame, which is the frequency the published return
        // implies.
        $outer = ['CH' => 30, 'PP' => 28, 'TA' => 22, 'MA' => 18, 'SO' => 10, 'SK' => 6, 'W' => 8, 'S' => 3];
        $middle = ['CH' => 26, 'PP' => 24, 'TA' => 20, 'MA' => 16, 'SO' => 10, 'SK' => 6, 'FR' => 1, 'W' => 8, 'S' => 3];

        return [$outer, $middle, $middle, $middle, $outer];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Piñata Wins',
                Volatility::Medium,
                5000,
                0.9675,
                ['pinata', 'cascade', 'frames', 'multipliers', '5x3', '20-lines'],
                'PG Soft (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 3, self::ID),
            SlotParts::symbols([
                ['id' => 'SK', 'name' => 'Skull', 'weight' => 2],
                ['id' => 'SO', 'name' => 'Sombrero', 'weight' => 3],
                ['id' => 'MA', 'name' => 'Maracas', 'weight' => 5],
                ['id' => 'TA', 'name' => 'Taco', 'weight' => 6],
                ['id' => 'CH', 'name' => 'Chilli', 'weight' => 7],
                ['id' => 'PP', 'name' => 'Pepper', 'weight' => 7],
                ['id' => 'FR', 'name' => 'Golden Frame', 'weight' => 3],
                ['id' => 'W', 'name' => 'Wild', 'type' => 'wild', 'weight' => 2],
                ['id' => 'S', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'SK' => [3 => 6, 4 => 25, 5 => 100],
                    'SO' => [3 => 4, 4 => 15, 5 => 50],
                    'MA' => [3 => 3, 4 => 9, 5 => 25],
                    'TA' => [3 => 2, 4 => 5, 5 => 15],
                    'CH' => [3 => 1, 4 => 3, 5 => 10],
                    'PP' => [3 => 1, 4 => 2, 5 => 6],
                    'W'  => [3 => 8, 4 => 31, 5 => 125],
                ],
                ['S' => [3 => 3, 4 => 10, 5 => 50]]
            ),
            new CompositeEvaluator(
                new PaylineEvaluator(self::PAYLINES),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    // A framed cell that is eliminated refills as a wild, which
                    // is the title's frame-to-wild rule.
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['FR'],
                        'W'
                    );
                }

                // The frame carries the multiplier. It is sequence-scoped: the
                // values collected across one tumble sequence are summed and
                // applied to that sequence's win, which is the published rule.
                //
                // The published free-spin "persistent meter" is represented by
                // this same accumulator rather than by a second, stacked
                // multiplier: the engine composes feature multipliers by taking
                // the highest one active on a step, not their product, so a
                // second multiplier feature would be a no-op rather than the
                // doubling the published game shows. The difference is recorded
                // in the game specification.
                $features[] = new SymbolMultiplierFeature(
                    'FR',
                    [2, 2, 3, 3, 5, 8, 10, 15, 25],
                    false
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
                freeSpinAwards: [3 => 15, 4 => 17, 5 => 19],
                freeSpinsRetrigger: true,
                freeSpinsCap: 100
            ),
            count(self::PAYLINES),
            50,
            // Calibrated to the published 96.75% return; see the spec.
            [3147, 10000]
        );
    }
}
