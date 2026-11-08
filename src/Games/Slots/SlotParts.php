<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots;

use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Frameworks\Slot\Paytable\Paytable;
use CasinoEngine\Frameworks\Slot\Reel\ReelConfiguration;
use CasinoEngine\Frameworks\Slot\Reel\ReelStrip;
use CasinoEngine\Frameworks\Slot\Symbol\Symbol;
use CasinoEngine\Frameworks\Slot\Symbol\SymbolSet;
use CasinoEngine\Services\Rng\RngFactory;

/**
 * Builders shared by the slot definitions.
 *
 * A reel strip is a *constant* of a game's mathematics: it is what makes the
 * symbol distribution, and therefore the payout distribution, stable across
 * every spin. Building a strip from weights with a fresh random shuffle on each
 * boot would re-roll the game's mathematics, so the shuffle here uses a fixed
 * seed derived from the game id. The strip is therefore identical on every
 * boot, in every process, while still being a shuffled arrangement rather than
 * a hand-written list.
 */
final class SlotParts
{
    /**
     * Builds a strip whose symbol counts match the given weights.
     *
     * @param array<string, int> $weights symbol id => count
     */
    public static function strip(array $weights, string $seedKey): ReelStrip
    {
        $seed = self::seedFor($seedKey);

        return ReelStrip::fromWeights($weights, RngFactory::seeded($seed, 'testing'));
    }

    /**
     * Builds one strip per reel from a per-reel weight map.
     *
     * @param list<array<string, int>> $perReelWeights
     * @param int|list<int> $rows
     */
    public static function reels(array $perReelWeights, int|array $rows, string $seedKey): ReelConfiguration
    {
        $strips = [];
        foreach ($perReelWeights as $index => $weights) {
            $strips[] = self::strip($weights, $seedKey . ':' . $index);
        }

        return new ReelConfiguration($strips, $rows);
    }

    /**
     * Builds a symbol set from a compact definition list.
     *
     * @param list<array{id:string,name:string,type?:string,weight?:int,multiplier?:int}> $definitions
     */
    public static function symbols(array $definitions): SymbolSet
    {
        $symbols = [];

        foreach ($definitions as $definition) {
            $type = $definition['type'] ?? 'regular';
            $weight = $definition['weight'] ?? 1;
            $multiplier = $definition['multiplier'] ?? 1;

            $symbols[] = match ($type) {
                'wild'    => Symbol::wild($definition['id'], $definition['name'], $weight, $multiplier),
                'scatter' => Symbol::scatter($definition['id'], $definition['name'], $weight),
                'bonus'   => Symbol::bonus($definition['id'], $definition['name'], $weight),
                'blank'   => Symbol::blank($definition['id']),
                default   => Symbol::regular($definition['id'], $definition['name'], $weight, $multiplier),
            };
        }

        return new SymbolSet($symbols);
    }

    /**
     * @param array<string, array<int, int>> $linePayouts
     * @param array<string, array<int, int>> $scatterPayouts
     */
    public static function paytable(array $linePayouts, array $scatterPayouts = []): Paytable
    {
        return new Paytable($linePayouts, $scatterPayouts);
    }

    /**
     * The standard 20-line set for a 5x3 grid.
     *
     * These are the conventional line shapes of the 20-line family: the three
     * straight lines, the four shallow diagonals, and the mirrored variants.
     * Sharing them keeps the line geometry identical between the games that use
     * the 20-line model, so a difference between two such games is always a
     * difference in their symbols, strips or features rather than an accidental
     * difference in what a line means.
     *
     * @return list<list<int>>
     */
    public static function paylines20x3(): array
    {
        return [
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
    }

    /**
     * The standard 5-line set for a 3x3 grid.
     *
     * @return list<list<int>>
     */
    public static function paylines3x3(): array
    {
        return [
            [1, 1, 1],
            [0, 0, 0],
            [2, 2, 2],
            [0, 1, 2],
            [2, 1, 0],
        ];
    }

    /**
     * Standard metadata for a slot.
     *
     * @param list<string> $tags
     */
    public static function metadata(
        string $id,
        string $name,
        Volatility $volatility,
        int $maxWinMultiplier,
        float $rtp,
        array $tags = [],
        string $provider = 'Engine Original'
    ): GameMetadata {
        $currency = 'PHP';

        return new GameMetadata(
            $id,
            $name,
            GameCategory::Slot,
            '1.0.0',
            '1.0.0',
            $provider,
            Money::of(20, $currency),
            Money::of(50000, $currency),
            Money::of(20, $currency),
            $volatility,
            $maxWinMultiplier,
            $rtp,
            true,
            $tags
        );
    }

    /** A stable seed for a named part, so a strip never changes between boots. */
    public static function seedFor(string $key): int
    {
        // A 32-bit FNV-1a hash: deterministic, no dependency on the platform's
        // hash order, and stable across PHP versions.
        $hash = 2166136261;
        $length = strlen($key);

        for ($i = 0; $i < $length; $i++) {
            $hash ^= ord($key[$i]);
            $hash = ($hash * 16777619) & 0xFFFFFFFF;
        }

        return $hash;
    }
}
