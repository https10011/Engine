<?php

declare(strict_types=1);

/**
 * Runs every registered slot game and reports its measured return.
 *
 * This is the health check that catches a broken definition: a game that throws
 * on its first spin, one whose free spins never end, one whose payout is wildly
 * off its published figure. It reports the paytable scale needed to bring each
 * game to its published return, which is what the definition's `payoutScale`
 * carries.
 *
 * Usage: php bin/audit-slots.php [spins] [game id ...]
 */

require __DIR__ . '/../vendor/autoload.php';

use CasinoEngine\Core\Bootstrap;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;

$spins = isset($argv[1]) ? (int) $argv[1] : 20000;
$only = array_slice($argv, 2);

$engine = Bootstrap::engine();
$engine->discoverGames();
$ids = $engine->registry()->ids(false);
$games = array_map(static fn (string $id): GameInterface => $engine->registry()->get($id), $ids);

$rows = [];
$failures = 0;

foreach ($games as $game) {
    if (!$game instanceof GameInterface || $game->category() !== GameCategory::Slot) {
        continue;
    }

    $id = $game->metadata()->id();
    if ($only !== [] && !in_array($id, $only, true)) {
        continue;
    }

    try {
        $context = $engine->contextFor($game, 20260101);
        $state = $game->createInitialState($context);
        $bet = Money::of(100, $game->metadata()->minBet()->currency());

        $payout = 0.0;
        $paid = 0;
        $free = 0;
        $wins = 0;
        $net = 0;
        $max = 0.0;

        for ($i = 0; $i < $spins; $i++) {
            $outcome = $game->execute(GameAction::withBet('spin', $bet), $state, $context);
            $isFree = $outcome->wagerMultiplier()->isZero();
            $state = $outcome->nextState();
            $multiplier = $outcome->payoutMultiplier()->toFloat();

            $isFree ? $free++ : $paid++;
            if ($multiplier > 0.0) {
                $wins++;
            }
            if ($multiplier > 1.0) {
                $net++;
            }
            $max = max($max, $multiplier);
            $payout += $multiplier;
        }

        $rtp = $paid > 0 ? $payout / $paid : 0.0;
        $target = $game->metadata()->rtp();

        $rows[] = sprintf(
            '%-30s rtp=%.4f target=%.4f scale=%.6f any=%.1f%% net=%.1f%% free=%.1f%% max=%.0fx',
            $id,
            $rtp,
            $target,
            $rtp > 0 ? $target / $rtp : 0.0,
            $wins / max(1, $spins) * 100,
            $net / max(1, $spins) * 100,
            $free / max(1, $spins) * 100,
            $max
        );
    } catch (\Throwable $e) {
        $failures++;
        $rows[] = sprintf('%-30s FAILED: %s', $id, $e->getMessage());
    }
}

echo implode("\n", $rows), "\n";
printf("\n%d game(s) audited, %d failed.\n", count($rows), $failures);

exit($failures > 0 ? 1 : 0);
