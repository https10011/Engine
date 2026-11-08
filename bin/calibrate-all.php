<?php

declare(strict_types=1);

/**
 * Measures every slot and reports the exact `payoutScale` that would bring it
 * to its published return.
 *
 * The payout multiplier is linear in the paytable, so one run is enough: the
 * scale is `target / measured`. The output is a ready-to-apply list of named
 * arguments, which is what the game definitions carry.
 *
 * Usage: php bin/calibrate-all.php [spins] [--json]
 */

require __DIR__ . '/../vendor/autoload.php';

use CasinoEngine\Core\Bootstrap;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;

$spins = isset($argv[1]) && is_numeric($argv[1]) ? (int) $argv[1] : 60000;
$json = in_array('--json', $argv, true);

$engine = Bootstrap::engine();
$engine->discoverGames();

$rows = [];

/**
 * Reads the payout scale a definition applies, which the calibration has to
 * undo before it can compute a new one.
 *
 * @return array{0:int,1:int}
 */
function payoutScale(GameInterface $game): array
{
    if (!method_exists($game, 'definition')) {
        return [1, 1];
    }

    $reader = \Closure::bind(
        static fn (GameInterface $g): array => $g->definition()->payoutScale(),
        null,
        $game
    );

    return $reader($game);
}

foreach ($engine->registry()->ids(false) as $id) {
    $game = $engine->registry()->get($id);
    if (!$game instanceof GameInterface || $game->category() !== GameCategory::Slot) {
        continue;
    }

    try {
        $context = $engine->contextFor($game, 20260101);
        $state = $game->createInitialState($context);
        $bet = Money::of(100, $game->metadata()->minBet()->currency());

        $payout = 0.0;
        $paid = 0;
        for ($i = 0; $i < $spins; $i++) {
            $outcome = $game->execute(GameAction::withBet('spin', $bet), $state, $context);
            $isFree = $outcome->wagerMultiplier()->isZero();
            $state = $outcome->nextState();
            if (!$isFree) {
                $paid++;
            }
            $payout += $outcome->payoutMultiplier()->toFloat();
        }

        $rtp = $paid > 0 ? $payout / $paid : 0.0;
        $target = $game->metadata()->rtp();

        // The measured return already includes whatever scale the definition
        // applies, so the new scale is the old one times target/measured.
        // Reporting target/measured alone would silently drop the existing
        // correction and overshoot on any game that had already been tuned.
        [$appliedNumerator, $appliedDenominator] = payoutScale($game);
        $applied = $appliedNumerator / $appliedDenominator;

        // Six decimal places is enough to pin the return to within a ten-
        // thousandth of a percent at any realistic sample size, and a fraction
        // of a million stays exact in the payout ratio.
        $numerator = $rtp > 0
            ? (int) round($applied * $target / $rtp * 1_000_000)
            : (int) round($applied * 1_000_000);

        $rows[] = [
            'id'       => $id,
            'rtp'      => round($rtp, 6),
            'applied'  => $applied,
            'target'   => $target,
            'scale'    => [$numerator, 1_000_000],
        ];
    } catch (\Throwable $e) {
        $rows[] = ['id' => $id, 'error' => $e->getMessage()];
    }
}

if ($json) {
    echo json_encode($rows, JSON_PRETTY_PRINT), "\n";
    exit(0);
}

foreach ($rows as $row) {
    if (isset($row['error'])) {
        printf("%-30s ERROR %s\n", $row['id'], $row['error']);
        continue;
    }

    printf(
        "%-30s rtp=%.4f target=%.4f  payoutScale: [%d, %d]\n",
        $row['id'],
        $row['rtp'],
        $row['target'],
        $row['scale'][0],
        $row['scale'][1]
    );
}
