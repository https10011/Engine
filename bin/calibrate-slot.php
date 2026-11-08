<?php

declare(strict_types=1);

/**
 * Measures a slot's return and reports the paytable scale needed to hit a target.
 *
 * Payout multiplier is linear in the paytable's unit values, so one simulation
 * run is enough to solve for the scale: `k = target / measured`. A second run
 * confirms the rounded scale.
 *
 * Usage: php bin/calibrate-slot.php '<game class>' [target] [spins]
 */

require __DIR__ . '/../vendor/autoload.php';

use CasinoEngine\Core\Bootstrap;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;

$class = $argv[1] ?? '';
$target = isset($argv[2]) ? (float) $argv[2] : 0.96;
$spins = isset($argv[3]) ? (int) $argv[3] : 300000;

if ($class === '' || !class_exists($class)) {
    fwrite(STDERR, "Usage: php bin/calibrate-slot.php '<game class>' [target] [spins]\n");
    exit(1);
}

$engine = Bootstrap::engine();
/** @var GameInterface $game */
$game = new $class();
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
$scale = $rtp > 0.0 ? $target / $rtp : 0.0;

printf("%s\n", $game->metadata()->id());
printf("  actions=%d paid=%d free=%.1f%%\n", $spins, $paid, $free / max(1, $spins) * 100);
printf("  any-win=%.2f%% net-positive=%.2f%% max=%.1fx\n", $wins / max(1, $spins) * 100, $net / max(1, $spins) * 100, $max);
printf("  measured RTP=%.4f\n", $rtp);
printf("  paytable scale for target %.4f: %.6f\n", $target, $scale);
printf("  predicted RTP after scale: %.4f\n", $rtp * $scale);
