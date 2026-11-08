<?php

declare(strict_types=1);

namespace CasinoEngine\Api\Controller;

use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\RequestContext;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Engine;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Registry\GameRegistryInterface;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Persistence\Repository\ProbabilityRepository;
use CasinoEngine\Services\Probability\ProbabilityConfigService;
use CasinoEngine\Services\Probability\ProbabilityConfigServiceInterface;
use CasinoEngine\Services\Probability\ProbabilityProfile;
use CasinoEngine\Simulation\GameDriver;
use CasinoEngine\Simulation\SimulationStatistics;

/**
 * The administrator's probability configuration surface.
 *
 * Every route here is behind `auth` **and** `admin`, and the administrator
 * identity used for the audit trail comes from the authenticated request
 * context, never from the body. A player cannot reach this controller, and a
 * request that reaches it cannot claim to have been made by somebody else.
 *
 * The controller is deliberately thin: it validates the shape of a request and
 * delegates to {@see ProbabilityConfigService}, which owns resolution,
 * versioning and the audit trail. The simulation endpoint runs the same
 * {@see GameDriver} the CLI harness uses, so an administrator's verification
 * measures the engine's real mathematics rather than a second implementation of
 * it.
 */
final class AdminController extends AbstractController
{
    /** The six metrics the dashboard configures, and how to read each one. */
    private const PROBABILITY_FIELDS = [
        'any_win_target',
        'net_positive_target',
        'rtp_target',
        'jackpot_probability',
        'bonus_trigger_probability',
        'free_spin_trigger_probability',
        'payout_scale',
    ];

    public function __construct(
        private readonly Engine $engine,
        private readonly GameRegistryInterface $games,
        private readonly ProbabilityConfigServiceInterface $probability,
        private readonly ProbabilityRepository $repository
    ) {
    }

    /**
     * The dashboard overview: how many games are configured, and how.
     *
     * @param array<string, string> $parameters
     */
    public function overview(array $parameters, Request $request, RequestContext $context): Response
    {
        $global = $this->probability->globalProfile();
        $overrides = $this->probability->overrides();
        // The registry returns metadata for every registered game, enabled or
        // not; the count of enabled titles is reported separately so an
        // administrator can see a disabled module in the list.
        $all = $this->games->all(false);

        $enabled = 0;
        foreach ($all as $metadata) {
            if ($metadata->isEnabled()) {
                $enabled++;
            }
        }

        return Response::ok([
            'global' => $global->toArray(),
            'counts' => [
                'games'     => count($all),
                'enabled'   => $enabled,
                'overrides' => count($overrides),
            ],
            'overrides' => array_map(
                static fn (ProbabilityProfile $profile): array => [
                    'game_id' => $profile->gameId,
                    'enabled' => $profile->enabled,
                    'version' => $profile->version,
                ],
                array_values($overrides)
            ),
            'metrics' => self::metricDocumentation(),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function globalProfile(array $parameters, Request $request, RequestContext $context): Response
    {
        return Response::ok([
            'profile' => $this->probability->globalProfile()->toArray(),
            'metrics' => self::metricDocumentation(),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function saveGlobal(array $parameters, Request $request, RequestContext $context): Response
    {
        $values = $this->profileValues($request);

        $profile = $this->probability->saveGlobal(
            $values,
            $context->userId(),
            $this->adminName($context)
        );

        return Response::ok([
            'profile' => $profile->toArray(),
            'affected_games' => $this->gameIdsWithoutOverride(),
        ]);
    }

    /**
     * Every game with the configuration that actually applies to it.
     *
     * This is the answer to "which games are using global settings and which
     * have their own", resolved by the same service the round service asks, so
     * the dashboard cannot show a policy different from the one in force.
     *
     * @param array<string, string> $parameters
     */
    public function games(array $parameters, Request $request, RequestContext $context): Response
    {
        $rows = [];
        foreach ($this->games->all(false) as $metadata) {
            $effective = $this->probability->effectiveFor($metadata->id());

            $rows[] = [
                'game' => [
                    'id'         => $metadata->id(),
                    'name'       => $metadata->name(),
                    'category'   => $metadata->category()->value,
                    'enabled'    => $metadata->isEnabled(),
                    'volatility' => $metadata->volatility()->value,
                    'rtp'        => $metadata->rtp(),
                ],
                'source'    => $this->probability->hasOverride($metadata->id()) ? 'override' : 'global',
                'override'  => ($this->probability->overrides()[$metadata->id()] ?? null)?->toArray(),
                'effective' => $effective->toArray(),
                'supported' => $this->supportedMetrics($metadata->category()),
            ];
        }

        return Response::ok(['games' => $rows], 200, ['count' => count($rows)]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function gameProfile(array $parameters, Request $request, RequestContext $context): Response
    {
        $gameId = $this->gameId($parameters);
        $override = $this->probability->overrides()[$gameId] ?? null;

        return Response::ok([
            'game_id'   => $gameId,
            'override'  => $override?->toArray(),
            'effective' => $this->probability->effectiveFor($gameId)->toArray(),
            'source'    => $override !== null ? 'override' : 'global',
            'supported' => $this->supportedMetrics($this->games->get($gameId)->metadata()->category()),
            'metrics'   => self::metricDocumentation(),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function saveGame(array $parameters, Request $request, RequestContext $context): Response
    {
        $gameId = $this->gameId($parameters);
        // Resolving the game first rejects an unknown id before anything is
        // written, and confirms the id is one the engine can actually run.
        $this->games->get($gameId);

        $values = $this->profileValues($request);
        $values['enabled'] = (bool) ($request->input('enabled') ?? true);

        $profile = $this->probability->saveOverride(
            $gameId,
            $values,
            $context->userId(),
            $this->adminName($context)
        );

        return Response::ok([
            'game_id'   => $gameId,
            'override'  => $profile->toArray(),
            'effective' => $this->probability->effectiveFor($gameId)->toArray(),
            'source'    => 'override',
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function resetGame(array $parameters, Request $request, RequestContext $context): Response
    {
        $gameId = $this->gameId($parameters);
        $this->games->get($gameId);

        $this->probability->resetToGlobal($gameId, $context->userId(), $this->adminName($context));

        return Response::ok([
            'game_id'   => $gameId,
            'source'    => 'global',
            'effective' => $this->probability->effectiveFor($gameId)->toArray(),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function versions(array $parameters, Request $request, RequestContext $context): Response
    {
        $profile = (string) ($request->query('profile') ?? ProbabilityProfile::NAME_GLOBAL);
        $limit = $this->intQuery($request, 'limit', 50, 1, 200);

        $rows = $this->probability->versions($profile, $limit);

        return Response::ok([
            'profile'  => $profile,
            'versions' => array_map(static function (array $row): array {
                $snapshot = $row['snapshot'] ?? null;
                if (is_string($snapshot)) {
                    $decoded = json_decode($snapshot, true);
                    $row['snapshot'] = is_array($decoded) ? $decoded : null;
                }

                return $row;
            }, $rows),
        ], 200, ['count' => count($rows)]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function restore(array $parameters, Request $request, RequestContext $context): Response
    {
        $profileName = $request->requireString('profile');
        $version = $request->input('version');
        if (!is_int($version) && !(is_string($version) && ctype_digit($version))) {
            throw new ValidationException('The field \'version\' must be an integer.');
        }
        $version = (int) $version;

        $profile = $this->probability->restoreVersion(
            $profileName,
            $version,
            $context->userId(),
            $this->adminName($context)
        );

        return Response::ok(['profile' => $profile->toArray()]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function audit(array $parameters, Request $request, RequestContext $context): Response
    {
        $limit = $this->intQuery($request, 'limit', 100, 1, 500);
        $rows = $this->probability->auditLog($limit);

        // The JSON columns arrive as strings from the driver; decode them so the
        // dashboard can render a before/after comparison without re-parsing.
        foreach ($rows as $index => $row) {
            foreach (['affected_games', 'before_state', 'after_state'] as $column) {
                if (isset($row[$column]) && is_string($row[$column])) {
                    $decoded = json_decode($row[$column], true);
                    $rows[$index][$column] = is_array($decoded) ? $decoded : null;
                }
            }
        }

        return Response::ok(['entries' => $rows], 200, ['count' => count($rows)]);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function jackpots(array $parameters, Request $request, RequestContext $context): Response
    {
        $gameId = $request->query('game_id');

        if (!is_string($gameId) || $gameId === '') {
            // Without a game id, report the tiers configured on every profile
            // that has them, which is what an administrator is looking for.
            $rows = [];
            foreach ($this->probability->overrides() as $profile) {
                if ($profile->jackpotTiers !== []) {
                    $rows[] = ['game_id' => $profile->gameId, 'tiers' => $profile->jackpotTiers];
                }
            }
            $global = $this->probability->globalProfile();
            if ($global->jackpotTiers !== []) {
                $rows[] = ['game_id' => ProbabilityProfile::NAME_GLOBAL, 'tiers' => $global->jackpotTiers];
            }

            return Response::ok(['profiles' => $rows]);
        }

        $this->games->get($gameId);

        return Response::ok([
            'game_id'   => $gameId,
            'effective' => $this->probability->effectiveFor($gameId)->toArray(),
            'history'   => $this->repository->jackpotHistory($gameId, 50),
        ]);
    }

    /**
     * Runs a simulation against a candidate configuration.
     *
     * The simulation is a *diagnostic*: it reports what the engine observed on
     * a sample, next to what the configuration asked for. It never touches a
     * wallet, a session or a round row — {@see GameDriver} has no access to any
     * of them — so an administrator can test a 50% any-win target without
     * changing a single live balance.
     *
     * @param array<string, string> $parameters
     */
    public function simulate(array $parameters, Request $request, RequestContext $context): Response
    {
        $gameId = $request->requireString('game_id');
        $rounds = (int) ($request->input('rounds') ?? 20000);
        $seed = $request->input('seed');

        if ($rounds < 100 || $rounds > 1000000) {
            throw new ValidationException('The round count must be between 100 and 1,000,000.');
        }

        if ($seed !== null && !is_int($seed) && !(is_string($seed) && ctype_digit($seed))) {
            throw new ValidationException('The seed must be an integer when supplied.');
        }

        $game = $this->games->get($gameId);
        $metadata = $game->metadata();

        $betMinor = (int) ($request->input('bet_minor') ?? $metadata->minBet()->minorUnits());
        if ($betMinor < $metadata->minBet()->minorUnits() || $betMinor > $metadata->maxBet()->minorUnits()) {
            throw new ValidationException(sprintf(
                'The bet must be between %d and %d minor units for this game.',
                $metadata->minBet()->minorUnits(),
                $metadata->maxBet()->minorUnits()
            ));
        }

        $configuration = $this->simulationConfiguration($request, $gameId);
        $profile = $configuration === []
            ? $this->probability->effectiveFor($gameId)
            : ProbabilityProfile::fromArray($configuration, $gameId);

        $bet = Money::of($betMinor, $metadata->minBet()->currency(), $metadata->minBet()->scale());

        // The driver is handed the candidate policy directly, so the report
        // measures the configuration under test rather than whatever is saved.
        $context = $this->engine->contextFor($game, $seed === null ? null : (int) $seed)
            ->withProbability($profile);

        $statistics = $this->runSimulation($game, $context, $rounds, $bet);

        $observed = $statistics->toArray();

        return Response::ok([
            'game_id'    => $gameId,
            'game_name'  => $metadata->name(),
            'rounds'     => $rounds,
            'bet_minor'  => $betMinor,
            'seed'       => $seed === null ? null : (int) $seed,
            'configured' => $profile->toArray(),
            'observed'   => $observed,
            'comparison' => $this->compare($profile, $observed),
            'note'       => 'Observed figures are sample statistics. A configured target is a '
                . 'distribution goal the governor steers towards; it is not an instruction to produce '
                . 'a particular result, and a game whose mechanics cannot reach a target will show the gap here.',
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * The candidate configuration a simulation should run under.
     *
     * An administrator may simulate a configuration before saving it, so the
     * request may carry one. When it does not, the saved effective profile is
     * used and the endpoint becomes a verification of what is in force.
     *
     * @return array<string, mixed>
     */
    private function simulationConfiguration(Request $request, string $gameId): array
    {
        $candidate = $request->input('profile');
        if (!is_array($candidate)) {
            return [];
        }

        $values = $this->filterProfileValues($candidate);
        $values['enabled'] = true;
        $values['name'] = $gameId;

        return $values;
    }

    /**
     * Runs the rounds through the shared driver.
     *
     * Batched so a million-round request streams in chunks of a thousand rather
     * than allocating a million round records at once.
     */
    private function runSimulation(
        \CasinoEngine\Core\Contract\GameInterface $game,
        \CasinoEngine\Core\GameContext $context,
        int $rounds,
        Money $bet
    ): SimulationStatistics {
        $driver = new GameDriver($game, $context);
        $statistics = new SimulationStatistics();
        $state = $game->createInitialState($context);

        $batch = 1000;
        for ($done = 0; $done < $rounds; $done += $batch) {
            $count = min($batch, $rounds - $done);
            for ($i = 0; $i < $count; $i++) {
                $driver->playRound($state, $this->openingAction($game, $bet), $statistics, $done + $i);
            }
        }

        return $statistics;
    }

    /**
     * The action that opens one simulated round.
     *
     * Mirrors the CLI harness: a module that knows its own strategy answers for
     * itself, otherwise the category decides.
     */
    private function openingAction(
        \CasinoEngine\Core\Contract\GameInterface $game,
        Money $bet
    ): GameAction {
        if ($game instanceof \CasinoEngine\Core\Contract\SimulationStrategyInterface) {
            $state = $game->createInitialState($this->engine->contextFor($game));

            return $game->simulationAction($state, $bet);
        }

        return match ($game->metadata()->category()) {
            GameCategory::Slot     => GameAction::withBet('spin', $bet),
            GameCategory::Card     => GameAction::withBet('deal', $bet),
            GameCategory::Roulette => GameAction::withBet('spin', $bet, $this->rouletteBets($game, $bet)),
        };
    }

    /**
     * A fixed roulette slip for simulation.
     *
     * The synthetic roulette fixture is the one the harness supports, because a
     * real table's limits make a one-round-per-observation simulation
     * unrepresentative. The bet is split half on red and half straight-up on 17,
     * matching the CLI's documented pattern.
     *
     * @return array{bets: list<array{type:string,numbers:list<string>,stake_minor:int}>}
     */
    private function rouletteBets(\CasinoEngine\Core\Contract\GameInterface $game, Money $bet): array
    {
        $configuration = $game->defaultConfiguration();
        if (!$configuration instanceof \CasinoEngine\Games\Synthetic\SyntheticRouletteConfiguration) {
            throw new ValidationException(
                'Roulette simulation requires the synthetic roulette game; the table game is '
                . 'played through the API instead.'
            );
        }

        $variant = $configuration->variant();
        $reds = [];
        foreach ($variant->pockets() as $pocket) {
            if ($pocket !== '0' && $pocket !== '00' && $variant->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }
        usort($reds, static fn (string $a, string $b): int => (int) $a <=> (int) $b);

        $half = intdiv($bet->minorUnits(), 2);

        return ['bets' => [
            ['type' => 'red', 'numbers' => $reds, 'stake_minor' => $half],
            ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => $bet->minorUnits() - $half],
        ]];
    }

    /**
     * Configured target versus observed sample, per metric.
     *
     * Kept separate from the observed figures so the dashboard never presents a
     * measured frequency as though it were the configured probability.
     *
     * @param array<string, mixed> $observed
     * @return array<string, array<string, mixed>>
     */
    private function compare(ProbabilityProfile $profile, array $observed): array
    {
        $hit = $observed['observed_hit_frequency'] ?? null;
        $win = $observed['observed_win_frequency'] ?? null;
        $rtp = $observed['observed_rtp'] ?? null;

        return [
            'any_win' => [
                'configured' => $profile->anyWinTarget,
                'observed'   => $hit,
                'delta'      => $this->delta($profile->anyWinTarget, $hit),
                'sample'     => $observed['paid_rounds'] ?? null,
            ],
            'net_positive' => [
                'configured' => $profile->netPositiveTarget,
                'observed'   => $win,
                'delta'      => $this->delta($profile->netPositiveTarget, $win),
                'sample'     => $observed['rounds'] ?? null,
            ],
            'rtp' => [
                'configured' => $profile->rtpTarget,
                'observed'   => $rtp,
                'delta'      => $this->delta($profile->rtpTarget, $rtp),
                'confidence' => $observed['observed_rtp_ci95'] ?? null,
            ],
            'jackpot' => [
                'configured' => $profile->jackpotProbability,
                'observed'   => null,
                'delta'      => null,
                'note'       => $profile->jackpotProbability === null
                    ? 'No jackpot probability is configured for this game.'
                    : 'Jackpot triggers are reported by the game module; the generic driver does not count them separately.',
            ],
            'bonus' => [
                'configured' => $profile->bonusTriggerProbability,
                'observed'   => $observed['feature_trigger_rate'] ?? null,
                'delta'      => $this->delta($profile->bonusTriggerProbability, $observed['feature_trigger_rate'] ?? null),
            ],
        ];
    }

    private function delta(?float $configured, ?float $observed): ?float
    {
        if ($configured === null || $observed === null) {
            return null;
        }

        return round($observed - $configured, 6);
    }

    /**
     * The metrics that make sense for a category.
     *
     * A slot has bonus and free-spin triggers; a card game does not. Telling the
     * dashboard which metrics a game supports is what stops it offering a
     * control that could never do anything.
     *
     * @return list<string>
     */
    private function supportedMetrics(GameCategory $category): array
    {
        return match ($category) {
            GameCategory::Slot => [
                'any_win_target', 'net_positive_target', 'rtp_target',
                'jackpot_probability', 'bonus_trigger_probability', 'free_spin_trigger_probability',
                'payout_scale',
            ],
            GameCategory::Card, GameCategory::Roulette => ['net_positive_target', 'rtp_target'],
        };
    }

    /**
     * Reads and validates the probability fields from a request.
     *
     * Unknown keys are rejected rather than ignored: a dashboard that silently
     * drops a mistyped field would let an administrator believe a setting was
     * saved when it was not.
     *
     * A value that fails {@see ProbabilityProfile}'s range check arrives as a
     * {@see \CasinoEngine\Core\Exception\ConfigurationException}, which the
     * router reports as a server error. That is the wrong answer for a mistyped
     * percentage, so it is re-thrown as a validation failure.
     *
     * @return array<string, mixed>
     */
    private function profileValues(Request $request): array
    {
        $values = $this->filterProfileValues($request->json());

        if (array_key_exists('enabled', $request->json())) {
            $values['enabled'] = (bool) $request->input('enabled');
        }

        try {
            ProbabilityProfile::fromArray($values);
        } catch (\CasinoEngine\Core\Exception\ConfigurationException $e) {
            throw new ValidationException($e->getMessage());
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterProfileValues(array $values): array
    {
        $allowed = array_merge(self::PROBABILITY_FIELDS, ['enabled', 'max_resample_attempts', 'jackpot_tiers', 'name', 'game_id']);
        $unknown = array_diff(array_keys($values), $allowed);

        if ($unknown !== []) {
            throw new ValidationException(
                'Unknown configuration key(s): ' . implode(', ', $unknown) . '.'
            );
        }

        $out = [];
        foreach (self::PROBABILITY_FIELDS as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $value = $values[$field];
            if ($value === null || $value === '') {
                $out[$field] = null;
                continue;
            }

            if (!is_numeric($value)) {
                throw new ValidationException("The value for '$field' must be numeric or null.");
            }

            $out[$field] = (float) $value;
        }

        if (array_key_exists('max_resample_attempts', $values)) {
            $out['max_resample_attempts'] = (int) $values['max_resample_attempts'];
        }

        if (array_key_exists('jackpot_tiers', $values) && is_array($values['jackpot_tiers'])) {
            $out['jackpot_tiers'] = $values['jackpot_tiers'];
        }

        return $out;
    }

    /**
     * The six metrics, explained, so the dashboard can teach the difference
     * rather than presenting six sliders that look interchangeable.
     *
     * @return array<string, array<string, string>>
     */
    private static function metricDocumentation(): array
    {
        return [
            'any_win_target' => [
                'label' => 'Any-win probability',
                'unit'  => 'probability',
                'help'  => 'The share of rounds that return anything at all, even less than the '
                    . 'stake. A 50% target does not make every second spin a winner: each outcome is '
                    . 'drawn independently and the governor steers the distribution.',
            ],
            'net_positive_target' => [
                'label' => 'Net-positive probability',
                'unit'  => 'probability',
                'help'  => 'The share of rounds whose return exceeds the amount wagered. This is a '
                    . 'stricter metric than any-win and the two are configured separately.',
            ],
            'rtp_target' => [
                'label' => 'RTP target',
                'unit'  => 'ratio',
                'help'  => 'Total returned divided by total wagered over many rounds. A 96% RTP '
                    . 'means 96 is returned per 100 staked on average; it is not a per-round probability.',
            ],
            'jackpot_probability' => [
                'label' => 'Jackpot probability',
                'unit'  => 'probability',
                'help'  => 'The chance of a jackpot trigger, configured independently of ordinary '
                    . 'wins. Only meaningful for games with a jackpot mechanic.',
            ],
            'bonus_trigger_probability' => [
                'label' => 'Bonus trigger probability',
                'unit'  => 'probability',
                'help'  => 'The chance that a round starts a bonus feature. Applies only to games that have one.',
            ],
            'free_spin_trigger_probability' => [
                'label' => 'Free-spin trigger probability',
                'unit'  => 'probability',
                'help'  => 'The chance that a round awards free spins. Applies only to games that award them.',
            ],
            'payout_scale' => [
                'label' => 'Payout scale',
                'unit'  => 'ratio',
                'help'  => 'A multiplier applied to payouts before the cap. 1.0 leaves the paytable '
                    . 'untouched; this is a blunt instrument and is reported separately from the '
                    . 'probability targets.',
            ],
        ];
    }

    /** @param array<string, string> $parameters */
    private function gameId(array $parameters): string
    {
        $gameId = trim((string) ($parameters['gameId'] ?? ''));
        if ($gameId === '') {
            throw new ValidationException('A game id is required.');
        }

        return $gameId;
    }

    private function adminName(RequestContext $context): string
    {
        $user = $context->user();

        return (string) ($user['username'] ?? 'unknown');
    }

    /** @return list<string> */
    private function gameIdsWithoutOverride(): array
    {
        $ids = [];
        foreach ($this->games->all(false) as $metadata) {
            $id = $metadata->id();
            if (!$this->probability->hasOverride($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
