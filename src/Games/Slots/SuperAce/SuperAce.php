<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots\SuperAce;

use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Evaluation\CompositeEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\PaylineEvaluator;
use CasinoEngine\Frameworks\Slot\Evaluation\ScatterEvaluator;
use CasinoEngine\Frameworks\Slot\Feature\CascadeFeature;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\Feature\FreeSpinsFeature;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Games\Slots\AbstractSlotGame;
use CasinoEngine\Games\Slots\SlotGameDefinition;
use CasinoEngine\Games\Slots\SlotParts;

/**
 * Super Ace.
 *
 * A 5x3, 20-line cascade slot. The mechanic that defines the title is the Gold
 * Card: when a Gold Card is part of a winning combination the reels cascade —
 * the winning symbols are removed and refilled — and the Gold Card becomes a
 * Joker Wild, which is why a chain often pays far more than its first step
 * suggested.
 *
 * Verified against the provider's published feature description: 5 reels, 3
 * rows, 20 paylines, left-to-right line wins, a Joker Wild substituting for all
 * symbols except the scatter, a Gold Card converting to a wild when it is part
 * of a win, and free spins triggered by three or more scatters.
 *
 * Unpublished values — the exact reel strips and the paytable's unit values —
 * are this engine's own configuration, documented as such in the game
 * specification rather than presented as the provider's figures.
 */
final class SuperAce extends AbstractSlotGame
{
    public const ID = 'jili.super-ace';

    /** 20 paylines, one row index per reel. */
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

    /**
     * Per-reel symbol weights. The Gold Card is rarer than the card ranks,
     * which is what makes a cascade chain worth chasing.
     *
     * @return list<array<string, int>>
     */
    private static function reelWeights(): array
    {
        return [
            ['T' => 5, 'J' => 5, 'Q' => 4, 'K' => 3, 'A' => 2, 'GC' => 2, 'JW' => 1, 'SC' => 1],
            ['T' => 5, 'J' => 4, 'Q' => 4, 'K' => 3, 'A' => 2, 'GC' => 2, 'JW' => 1, 'SC' => 1],
            ['T' => 4, 'J' => 4, 'Q' => 4, 'K' => 3, 'A' => 3, 'GC' => 2, 'JW' => 1, 'SC' => 1],
            ['T' => 5, 'J' => 4, 'Q' => 4, 'K' => 3, 'A' => 2, 'GC' => 2, 'JW' => 1, 'SC' => 1],
            ['T' => 5, 'J' => 5, 'Q' => 4, 'K' => 3, 'A' => 2, 'GC' => 2, 'JW' => 1, 'SC' => 1],
        ];
    }

    protected function definition(): SlotGameDefinition
    {
        return new SlotGameDefinition(
            SlotParts::metadata(
                self::ID,
                'Super Ace',
                Volatility::HighMedium,
                5000,
                0.9600,
                ['super-ace', 'cascade', 'gold-card', '5x3', '20-lines'],
                'JILI (mechanics) / Engine Original (configuration)'
            ),
            SlotParts::reels(self::reelWeights(), 3, self::ID),
            SlotParts::symbols([
                ['id' => 'T', 'name' => 'Ten', 'weight' => 5],
                ['id' => 'J', 'name' => 'Jack', 'weight' => 5],
                ['id' => 'Q', 'name' => 'Queen', 'weight' => 4],
                ['id' => 'K', 'name' => 'King', 'weight' => 3],
                ['id' => 'A', 'name' => 'Ace', 'weight' => 2],
                ['id' => 'GC', 'name' => 'Gold Card', 'weight' => 2],
                ['id' => 'JW', 'name' => 'Joker Wild', 'type' => 'wild', 'weight' => 1],
                ['id' => 'SC', 'name' => 'Scatter', 'type' => 'scatter', 'weight' => 1],
            ]),
            SlotParts::paytable(
                [
                    'JW' => [3 => 20, 4 => 100, 5 => 500],
                    'GC' => [3 => 12, 4 => 40, 5 => 200],
                    'A'  => [3 => 8, 4 => 30, 5 => 100],
                    'K'  => [3 => 5, 4 => 20, 5 => 60],
                    'Q'  => [3 => 3, 4 => 12, 5 => 40],
                    'J'  => [3 => 2, 4 => 8, 5 => 25],
                    'T'  => [3 => 1, 4 => 4, 5 => 15],
                ],
                ['SC' => [3 => 10, 4 => 60, 5 => 400]]
            ),
            new CompositeEvaluator(
                new PaylineEvaluator(self::PAYLINES),
                new ScatterEvaluator()
            ),
            static function (SlotConfiguration $configuration): array {
                /** @var list<FeatureInterface> $features */
                $features = [];

                if ($configuration->cascadesEnabled) {
                    // The Gold Card refills as the Joker Wild once it is
                    // eliminated: the title's defining mechanic.
                    $features[] = new CascadeFeature(
                        $configuration->maxCascades,
                        'reel_strip',
                        ['GC'],
                        'JW'
                    );
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
                maxCascades: 25,
                freeSpinsEnabled: true,
                freeSpinAwards: [3 => 10, 4 => 15, 5 => 20],
                freeSpinsRetrigger: true,
                freeSpinsCap: 200
            ),
            count(self::PAYLINES),
            50,
            // Calibrated against the published 96.00% return: the authored
            // table measures 0.6838 with the full feature set, so the payout is
            // scaled by 1404/1000. See the game specification.
            [1404, 1000]
        );
    }
}
