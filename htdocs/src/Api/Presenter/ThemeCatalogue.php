<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Presenter;

use CasinoEngine\Core\Contract\GameMetadataInterface;

/**
 * A per-title visual identity.
 *
 * The directive is explicit that the sixteen slots must not be the same
 * interface with a different title. The mechanic is what makes each game
 * different, and the interface reflects that mechanic; the theme is what makes
 * each game *recognisable* at a glance in the lobby and on its own page.
 *
 * The palette, the accent, the emblem and the short pitch are engine data, not
 * artwork: no provider asset is copied, and the emblem is a single character or
 * a short glyph chosen to match the title's own subject. The frontend renders
 * the theme through CSS custom properties, so a new title only needs an entry
 * here.
 */
final class ThemeCatalogue
{
    /**
     * Title => palette. Keys are the registered game ids.
     *
     * `base` and `panel` are the page background and the reel cabinet; `accent`
     * and `accent2` drive buttons, borders and win glows; `emblem` is the
     * glyph shown on the game card and in the cabinet header.
     *
     * @var array<string, array<string, string>>
     */
    private const THEMES = [
        'jili.super-ace' => [
            'name'    => 'Super Ace',
            'emblem'  => '♠',
            'base'    => '#0b1f14',
            'panel'   => '#123021',
            'accent'  => '#2fd07a',
            'accent2' => '#f7d54a',
            'ink'     => '#eafff3',
            'pitch'   => 'Gold Cards turn wild as the reels cascade.',
        ],
        'pgsoft.pinata-wins' => [
            'name'    => 'Piñata Wins',
            'emblem'  => '★',
            'base'    => '#2b0d3a',
            'panel'   => '#43165a',
            'accent'  => '#ff5fa2',
            'accent2' => '#ffd166',
            'ink'     => '#fff2fb',
            'pitch'   => 'Smash the piñata for expanding wilds.',
        ],
        'pgsoft.wild-bounty-showdown' => [
            'name'    => 'Wild Bounty Showdown',
            'emblem'  => '⚑',
            'base'    => '#2a1a08',
            'panel'   => '#3f2910',
            'accent'  => '#e0a03c',
            'accent2' => '#ff6b35',
            'ink'     => '#fff6e6',
            'pitch'   => 'Wanted posters pay; the sheriff is wild.',
        ],
        'slots.mahjong-hit' => [
            'name'    => 'Mahjong Hit',
            'emblem'  => '中',
            'base'    => '#0d2b2b',
            'panel'   => '#144040',
            'accent'  => '#38d9c2',
            'accent2' => '#ffd166',
            'ink'     => '#eafffb',
            'pitch'   => 'Match the tiles; dragons multiply.',
        ],
        'jili.golden-empire' => [
            'name'    => 'Golden Empire',
            'emblem'  => '♛',
            'base'    => '#231a05',
            'panel'   => '#3a2c0a',
            'accent'  => '#ffcf40',
            'accent2' => '#c8901f',
            'ink'     => '#fff9e6',
            'pitch'   => 'Stack the imperial coins for free spins.',
        ],
        'slots.sugar-bang-bang-2' => [
            'name'    => 'Sugar Bang Bang 2',
            'emblem'  => '✹',
            'base'    => '#3a0f2c',
            'panel'   => '#571a41',
            'accent'  => '#ff4d9d',
            'accent2' => '#7bdff2',
            'ink'     => '#fff0f8',
            'pitch'   => 'Cluster candies explode into multipliers.',
        ],
        'jili.fortune-gems-3' => [
            'name'    => 'Fortune Gems 3',
            'emblem'  => '◆',
            'base'    => '#071e2e',
            'panel'   => '#0d2f47',
            'accent'  => '#35c1f1',
            'accent2' => '#ff5d8f',
            'ink'     => '#e9faff',
            'pitch'   => 'Hold the gems; the jackpot climbs.',
        ],
        'slots.swerte-fiesta' => [
            'name'    => 'Swerte Fiesta',
            'emblem'  => '✽',
            'base'    => '#2b0a1c',
            'panel'   => '#45102e',
            'accent'  => '#ffc93c',
            'accent2' => '#ff477e',
            'ink'     => '#fff6ec',
            'pitch'   => 'A town fiesta of wilds and free games.',
        ],
        'jili.super-ace-deluxe' => [
            'name'    => 'Super Ace Deluxe',
            'emblem'  => '♤',
            'base'    => '#0a1526',
            'panel'   => '#122039',
            'accent'  => '#4d9fff',
            'accent2' => '#f7d54a',
            'ink'     => '#eaf3ff',
            'pitch'   => 'Deluxe Gold Cards with a rising multiplier.',
        ],
        'jili.fortune-garuda-1000' => [
            'name'    => 'Fortune Garuda 1000',
            'emblem'  => '⛰',
            'base'    => '#2b1a06',
            'panel'   => '#43290a',
            'accent'  => '#ffa41b',
            'accent2' => '#ffd166',
            'ink'     => '#fff7ea',
            'pitch'   => 'Ways wins up to a thousand times.',
        ],
        'pragmatic.sugar-rush-1000' => [
            'name'    => 'Sugar Rush 1000',
            'emblem'  => '●',
            'base'    => '#3d0b28',
            'panel'   => '#5a1039',
            'accent'  => '#ff2d78',
            'accent2' => '#59e0b4',
            'ink'     => '#fff0f7',
            'pitch'   => 'Tumble the sweets; multipliers accumulate.',
        ],
        'jili.fortune-gems-2' => [
            'name'    => 'Fortune Gems 2',
            'emblem'  => '◈',
            'base'    => '#1d0b2e',
            'panel'   => '#2c1145',
            'accent'  => '#b06bff',
            'accent2' => '#4de1c1',
            'ink'     => '#f4ecff',
            'pitch'   => 'Hold-and-win gems with a four-tier jackpot.',
        ],
        'pgsoft.treasure-of-aztec' => [
            'name'    => 'Treasure of Aztec',
            'emblem'  => '☀',
            'base'    => '#0f2a12',
            'panel'   => '#173d1b',
            'accent'  => '#e2b13c',
            'accent2' => '#4caf50',
            'ink'     => '#f6ffe9',
            'pitch'   => 'Sun-stone wilds expand across the temple.',
        ],
        'pragmatic.sweet-bonanza-1000' => [
            'name'    => 'Sweet Bonanza 1000',
            'emblem'  => '✿',
            'base'    => '#3a0f3f',
            'panel'   => '#55175c',
            'accent'  => '#ff7ad9',
            'accent2' => '#8be9fd',
            'ink'     => '#fff3ff',
            'pitch'   => 'Scatter pays with candy bomb multipliers.',
        ],
        'jili.super-ace-2' => [
            'name'    => 'Super Ace 2',
            'emblem'  => '♧',
            'base'    => '#14210f',
            'panel'   => '#1e3216',
            'accent'  => '#9be15d',
            'accent2' => '#ffd166',
            'ink'     => '#f4ffe9',
            'pitch'   => 'The Gold Card returns with a wild streak.',
        ],
        'pragmatic.gates-of-olympus-1000' => [
            'name'    => 'Gates Of Olympus 1000',
            'emblem'  => '⚡',
            'base'    => '#131a35',
            'panel'   => '#1d2750',
            'accent'  => '#ffd93d',
            'accent2' => '#7aa5ff',
            'ink'     => '#f0f4ff',
            'pitch'   => 'Zeus strikes; multiplier orbs rain down.',
        ],
        'table.blackjack' => [
            'name'    => 'Blackjack',
            'emblem'  => '♠',
            'base'    => '#0a2a1c',
            'panel'   => '#0f3a28',
            'accent'  => '#f4d35e',
            'accent2' => '#7ee0b8',
            'ink'     => '#f2fff8',
            'pitch'   => 'Beat the dealer to 21 without busting.',
        ],
        'table.roulette' => [
            'name'    => 'Roulette',
            'emblem'  => '◉',
            'base'    => '#1b0d0d',
            'panel'   => '#2b1414',
            'accent'  => '#d7263d',
            'accent2' => '#f2f2f2',
            'ink'     => '#fff4f4',
            'pitch'   => 'Inside and outside bets on a European wheel.',
        ],
    ];

    /** Fallback palette for a game that has no entry yet. */
    private const DEFAULT_THEME = [
        'name'    => '',
        'emblem'  => '◆',
        'base'    => '#111827',
        'panel'   => '#1f2937',
        'accent'  => '#60a5fa',
        'accent2' => '#fbbf24',
        'ink'     => '#f9fafb',
        'pitch'   => '',
    ];

    /** @return array<string, string> */
    public static function for(GameMetadataInterface $metadata): array
    {
        $theme = self::THEMES[$metadata->id()] ?? self::DEFAULT_THEME;

        // The metadata name is authoritative for the displayed title; the theme
        // only supplies the visual identity around it.
        $theme['name'] = $metadata->name();
        if ($theme['pitch'] === '') {
            $theme['pitch'] = 'A ' . $metadata->category()->value . ' game.';
        }

        return $theme;
    }

    /** @return list<string> Every game id with a themed identity. */
    public static function themedIds(): array
    {
        return array_keys(self::THEMES);
    }
}
