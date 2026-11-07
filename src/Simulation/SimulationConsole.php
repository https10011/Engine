<?php

declare(strict_types=1);

namespace CasinoEngine\Simulation;

use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;

/**
 * Runs `sim:run` and `sim:compare`.
 *
 * Kept out of `Cli\Command` because a simulation needs the game modules and
 * the RNG but never the database, so it can be deployed and run anywhere the
 * engine code is available — including a machine with no MySQL.
 */
final class SimulationConsole
{
    /** @var array<string, array<string, string>> */
    private const USAGE = [
        'sim:run' => [
            'usage' => 'php bin/engine-simulate sim:run --game=<id> [--rounds=10000] [--seed=42] [--bet=1.00] [--json] [--csv=path]',
            'about' => 'Play one registered game many times and report observed statistics.',
        ],
        'sim:compare' => [
            'usage' => 'php bin/engine-simulate sim:compare --game=<id> --seed-a=1 --seed-b=2 [--rounds=10000] [--bet=1.00]',
            'about' => 'Run the same game under two seeds and diff the observed summaries.',
        ],
    ];

    public function __construct(private readonly Engine $engine)
    {
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $args = array_slice($argv, 1);
        $command = null;
        $options = [];

        foreach ($args as $index => $arg) {
            if ($index === 0 && !str_starts_with($arg, '-')) {
                $command = $arg;
                continue;
            }

            if (str_starts_with($arg, '--')) {
                [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
                $options[$key] = $value;
            }
        }

        if ($command === null || isset($options['help'])) {
            $this->printHelp($command);

            return 0;
        }

        return match ($command) {
            'sim:run'     => $this->simRun($options),
            'sim:compare' => $this->simCompare($options),
            default       => $this->fail("Unknown simulation command '$command'."),
        };
    }

    /** @param array<string, mixed> $options */
    private function simRun(array $options): int
    {
        $gameId = $this->requireString($options, 'game');
        $rounds = $this->intOption($options, 'rounds', 10000);
        $bet = $this->betOption($options);
        $seed = $this->seedOption($options);

        $report = $this->simulate($gameId, $rounds, $seed, $bet);

        if ($this->boolOption($options, 'json')) {
            $this->writeJson($report);

            return 0;
        }

        $this->renderReport($report);

        $csvPath = $this->stringOption($options, 'csv');
        if ($csvPath !== null) {
            $this->writeCsv($csvPath, $report);
        }

        return 0;
    }

    /** @param array<string, mixed> $options */
    private function simCompare(array $options): int
    {
        $gameId = $this->requireString($options, 'game');
        $rounds = $this->intOption($options, 'rounds', 10000);
        $bet = $this->betOption($options);
        $seedA = (int) $this->requireString($options, 'seed-a');
        $seedB = (int) $this->requireString($options, 'seed-b');

        $a = $this->simulate($gameId, $rounds, $seedA, $bet);
        $b = $this->simulate($gameId, $rounds, $seedB, $bet);

        if ($this->boolOption($options, 'json')) {
            $this->writeJson(['a' => $a, 'b' => $b, 'delta' => $this->delta($a, $b)]);

            return 0;
        }

        $this->line("Comparison for {$gameId} over $rounds rounds");
        $this->line('');
        $this->printComparisonTable($a, $b, $this->delta($a, $b));

        return 0;
    }

    /**
     * Runs a simulation without touching the database.
     *
     * @return array<string, mixed>
     */
    private function simulate(string $gameId, int $rounds, ?int $seed, Money $bet): array
    {
        if ($rounds < 1) {
            throw new ValidationException('The round count must be at least 1.');
        }

        $this->engine->discoverGames();
        $game = $this->engine->registry()->get($gameId);

        // A seeded context is requested explicitly: in production the RNG is
        // the CSPRNG and a seed is ignored, which is exactly what stops a
        // reproducible stream from reaching real play.
        $context = $this->engine->contextFor($game, $seed);
        $driver = new GameDriver($game, $context);

        $statistics = new SimulationStatistics();
        $state = $game->createInitialState($context);

        $batchSize = 1000;
        for ($done = 0; $done < $rounds; $done += $batchSize) {
            $batch = min($batchSize, $rounds - $done);
            for ($i = 0; $i < $batch; $i++) {
                $action = $this->actionFor($game, $state, $bet);
                $driver->playRound($state, $action, $statistics, $done + $i);
            }
        }

        return [
            'game_id'        => $gameId,
            'game_name'      => $game->metadata()->name(),
            'category'       => $game->metadata()->category()->value,
            'rounds'         => $rounds,
            'bet_minor'      => $bet->minorUnits(),
            'bet'            => $bet->toDecimalString(),
            'seed'           => $seed,
            'rng'            => $context->rng()->describe(),
            'deterministic'  => $context->rng()->isDeterministic(),
            'playing_policy' => $this->policyFor($game),
            'observed'       => $statistics->toArray(),
            'note'           => 'All figures are observations of this sample, not theoretical values.',
        ];
    }

    /**
     * The action that opens one round for a given game.
     *
     * The category decides the shape: slots and roulette take a single paid
     * action, card games deal a hand that the driver then plays out.
     */
    private function actionFor(\CasinoEngine\Core\Contract\GameInterface $game, \CasinoEngine\Core\Contract\GameStateInterface $state, Money $bet): GameAction
    {
        return match ($game->metadata()->category()) {
            \CasinoEngine\Core\Contract\GameCategory::Slot     => GameAction::withBet('spin', $bet),
            \CasinoEngine\Core\Contract\GameCategory::Card     => GameAction::withBet('deal', $bet),
            \CasinoEngine\Core\Contract\GameCategory::Roulette => GameAction::withBet('spin', $bet, $this->rouletteBets($game, $bet)),
            default => throw new ValidationException(
                'No simulation strategy is defined for this game category.'
            ),
        };
    }

    /**
     * A fixed, documented roulette betting pattern: an even-money bet and a
     * single straight-up, split so the total matches the round stake.
     *
     * @return array{bets: list<array{type:string,numbers:list<string>,stake_minor:int}>}
     */
    private function rouletteBets(\CasinoEngine\Core\Contract\GameInterface $game, Money $bet): array
    {
        $configuration = $game->defaultConfiguration();
        if (!$configuration instanceof \CasinoEngine\Games\Synthetic\SyntheticRouletteConfiguration) {
            throw new ValidationException('The roulette simulation requires the synthetic roulette configuration.');
        }

        $variant = $configuration->variant();
        $reds = [];
        foreach ($variant->pockets() as $pocket) {
            if ($pocket !== '0' && $pocket !== '00' && $variant->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }
        usort($reds, static fn (string $a, string $b): int => (int) $a <=> (int) $b);

        // Half on red, half straight-up on 17, so the total is exactly the bet.
        $half = intdiv($bet->minorUnits(), 2);
        $remainder = $bet->minorUnits() - $half;

        return ['bets' => [
            ['type' => 'red', 'numbers' => $reds, 'stake_minor' => $half],
            ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => $remainder],
        ]];
    }

    private function policyFor(\CasinoEngine\Core\Contract\GameInterface $game): string
    {
        return match ($game->metadata()->category()) {
            \CasinoEngine\Core\Contract\GameCategory::Card => 'stand on 17 or more, otherwise hit',
            \CasinoEngine\Core\Contract\GameCategory::Roulette => 'half on red, half straight-up on 17',
            default => 'single paid action per round',
        };
    }

    /** @param array<string, mixed> $report */
    private function renderReport(array $report): void
    {
        $observed = $report['observed'];

        $this->line("Simulation: {$report['game_name']} ({$report['game_id']})");
        $this->line("Category:   {$report['category']}");
        $this->line("Rounds:     {$report['rounds']}");
        $this->line("Bet:        {$report['bet']} {$this->currency()}");
        $this->line('Seed:       ' . ($report['seed'] ?? 'none (cryptographic RNG)'));
        $this->line("RNG:        {$report['rng']}" . ($report['deterministic'] ? ' (deterministic)' : ''));
        $this->line("Policy:     {$report['playing_policy']}");
        $this->line('');
        $this->line('Observed sample statistics');
        $this->line(str_repeat('-', 58));
        $this->line(sprintf('  %-32s %s', 'Total wagered', $this->money($observed['total_wagered_minor'])));
        $this->line(sprintf('  %-32s %s', 'Total returned', $this->money($observed['total_returned_minor'])));
        $this->line(sprintf('  %-32s %s', 'Net (returned - wagered)', $this->money($observed['net_minor'])));
        $this->line(sprintf('  %-32s %s', 'Observed RTP', $this->ratio($observed['observed_rtp'])));
        $this->line(sprintf('  %-32s %s', 'Observed hit frequency', $this->ratio($observed['observed_hit_frequency'])));
        $this->line(sprintf('  %-32s %s', 'Observed win frequency', $this->ratio($observed['observed_win_frequency'])));
        $this->line(sprintf('  %-32s %s', 'Observed standard error', $this->ratio($observed['observed_standard_error'])));

        if (is_array($observed['observed_rtp_ci95'] ?? null)) {
            $ci = $observed['observed_rtp_ci95'];
            $this->line(sprintf(
                '  %-32s %s',
                '95% CI for mean return',
                $this->ratio($ci['low']) . ' .. ' . $this->ratio($ci['high'])
            ));
        }

        $this->line('');
        $this->line(sprintf('  %-32s %d', 'Winning rounds', $observed['winning_rounds']));
        $this->line(sprintf('  %-32s %d', 'Push rounds', $observed['push_rounds']));
        $this->line(sprintf('  %-32s %d', 'Losing rounds', $observed['losing_rounds']));
        $this->line(sprintf('  %-32s %d', 'Rounds with a payout', $observed['hits']));
        $this->line(sprintf('  %-32s %s', 'Largest payout', $this->money($observed['max_payout_minor'])));
        $this->line(sprintf('  %-32s %s', 'Smallest payout', $this->money($observed['min_payout_minor'])));
        $this->line(sprintf('  %-32s %s', 'Largest return multiple', $this->ratio($observed['max_return_multiple'])));

        if ($observed['feature_counts'] !== []) {
            $this->line('');
            $this->line(sprintf('  %-32s %s', 'Feature trigger rate', $this->ratio($observed['feature_trigger_rate'])));
            foreach ($observed['feature_counts'] as $feature => $count) {
                $this->line(sprintf('    %-30s %d', $feature, $count));
            }
        }

        $distribution = $observed['return_distribution'];
        if ($distribution !== []) {
            $this->line('');
            $this->line('  Return distribution (multiple of stake => rounds)');
            $keys = array_keys($distribution);
            // Only the tails and the break-even bucket are interesting; a full
            // listing of a slot histogram can run to hundreds of rows.
            $show = array_slice($keys, 0, 4);
            $show = array_merge($show, array_slice($keys, -4));
            $show = array_values(array_unique($show));
            sort($show, SORT_NUMERIC);
            foreach ($show as $key) {
                $this->line(sprintf('    %-12s %d', $key . 'x', $distribution[$key]));
            }
        }

        $this->line('');
        $this->line($report['note']);
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @param array<string, mixed> $delta
     */
    private function printComparisonTable(array $a, array $b, array $delta): void
    {
        $rows = [
            'Observed RTP'          => 'observed_rtp',
            'Observed hit freq.'    => 'observed_hit_frequency',
            'Observed win freq.'    => 'observed_win_frequency',
            'Standard error'        => 'observed_standard_error',
            'Total wagered (minor)' => 'total_wagered_minor',
            'Total returned (minor)' => 'total_returned_minor',
        ];

        $this->line(sprintf('  %-24s %14s %14s %14s', 'Metric', 'Seed A', 'Seed B', 'Delta'));
        $this->line(str_repeat('-', 70));
        foreach ($rows as $label => $key) {
            $this->line(sprintf(
                '  %-24s %14s %14s %14s',
                $label,
                $this->scalar($a['observed'][$key] ?? null),
                $this->scalar($b['observed'][$key] ?? null),
                $this->scalar($delta[$key] ?? null)
            ));
        }
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private function delta(array $a, array $b): array
    {
        $delta = [];
        foreach ($a['observed'] as $key => $value) {
            $other = $b['observed'][$key] ?? null;
            $delta[$key] = (is_int($value) || is_float($value)) && (is_int($other) || is_float($other))
                ? round($other - $value, 6)
                : null;
        }

        return $delta;
    }

    /** @param array<string, mixed> $report */
    private function writeJson(array $report): void
    {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    /** @param array<string, mixed> $report */
    private function writeCsv(string $path, array $report): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new ValidationException("Cannot open '$path' for writing.");
        }

        fputcsv($handle, ['metric', 'value']);
        fputcsv($handle, ['game_id', $report['game_id']]);
        fputcsv($handle, ['rounds', $report['rounds']]);
        fputcsv($handle, ['seed', $report['seed'] ?? '']);
        foreach ($report['observed'] as $key => $value) {
            if (is_scalar($value) || $value === null) {
                fputcsv($handle, [$key, $value]);
            }
        }
        fclose($handle);

        $this->line('');
        $this->line("CSV written to $path");
    }

    private function printHelp(?string $command): void
    {
        if ($command !== null && isset(self::USAGE[$command])) {
            $this->line($command . ' — ' . self::USAGE[$command]['about']);
            $this->line('');
            $this->line('Usage: ' . self::USAGE[$command]['usage']);

            return;
        }

        $this->line('Universal Casino Game Engine — simulation harness');
        $this->line('');
        $this->line('Usage: php bin/engine-simulate <command> [--option=value]');
        $this->line('');
        $this->line('Commands:');
        foreach (self::USAGE as $name => $meta) {
            $this->line(sprintf('  %-14s %s', $name, $meta['about']));
        }
        $this->line('');
        $this->line('A seed makes a run reproducible. Without a seed the cryptographic');
        $this->line('generator is used and the run is not reproducible.');
    }

    /** @param array<string, mixed> $options */
    private function requireString(array $options, string $key): string
    {
        $value = $this->stringOption($options, $key);
        if ($value === null || $value === '') {
            throw new ValidationException("The --$key option is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $options */
    private function stringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $options */
    private function intOption(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param array<string, mixed> $options */
    private function boolOption(array $options, string $key): bool
    {
        $value = $options[$key] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /** @param array<string, mixed> $options */
    private function seedOption(array $options): ?int
    {
        $value = $options['seed'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $options */
    private function betOption(array $options): Money
    {
        $value = $this->stringOption($options, 'bet');

        return $value === null
            ? Money::of(100)
            : Money::fromDecimalString($value);
    }

    private function money(int $minor): string
    {
        return number_format($minor / 100, 2, '.', ',') . ' ' . $this->currency();
    }

    private function ratio(?float $value): string
    {
        return $value === null ? 'n/a' : number_format($value, 4, '.', '');
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        return is_float($value) ? number_format($value, 4, '.', '') : (string) $value;
    }

    private function currency(): string
    {
        return (string) $this->engine->config()->get('engine.currency', 'PHP');
    }

    private function line(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    private function fail(string $message): int
    {
        fwrite(STDERR, $message . PHP_EOL);

        return 1;
    }
}
