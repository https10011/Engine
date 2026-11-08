<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Slots;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameOutcome;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Slot\Feature\FeatureInterface;
use CasinoEngine\Frameworks\Slot\SlotConfiguration;
use CasinoEngine\Frameworks\Slot\SlotEngine;
use CasinoEngine\Frameworks\Slot\SlotOutcomeDetail;
use CasinoEngine\Frameworks\Slot\SlotState;
use CasinoEngine\Frameworks\Slot\SpinResult;
use CasinoEngine\Services\Probability\OutcomeClassification;
use CasinoEngine\Services\Probability\ProbabilityProfile;
use CasinoEngine\Services\Probability\WinFrequencyGovernor;

/**
 * Shared behaviour for the slot modules.
 *
 * Every slot in this repository shares the same *lifecycle* — validate the
 * action, run a spin, turn units into a multiplier, decide the next state — and
 * differs only in its *definition*: its symbols, reel strips, paytable,
 * evaluator and features. This class implements the lifecycle once and leaves
 * the definition to {@see self::definition()}, so a new game is a definition
 * rather than a copy of a spin loop.
 *
 * It is deliberately not a "generic slot". A subclass with a different
 * evaluation model supplies a different evaluator; a subclass whose grid is not
 * rectangular supplies a ragged reel configuration; a subclass with no free
 * spins simply omits that feature. Nothing here assumes a 5x3 grid or a payline
 * model.
 *
 * Probability configuration is applied through {@see WinFrequencyGovernor}:
 * the module draws genuine outcomes and the governor decides how many draws to
 * take. The module never fabricates a grid or rewrites a payout, so an
 * administrator's target stays a distribution goal rather than an instruction
 * to produce a particular result.
 */
abstract class AbstractSlotGame implements GameInterface
{
    abstract protected function definition(): SlotGameDefinition;

    public function category(): GameCategory
    {
        return GameCategory::Slot;
    }

    public function metadata(): GameMetadataInterface
    {
        return $this->definition()->metadata();
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return $this->definition()->defaultConfiguration();
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof SlotConfiguration) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException(
                'A slot game expects a SlotConfiguration.'
            );
        }

        $configuration->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return SlotState::initial();
    }

    public function hydrateState(array $data): GameStateInterface
    {
        return SlotState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if ($action->name() !== 'spin') {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }

        if (!$state instanceof SlotState) {
            throw new GameRuleException('The session state is not a slot state.');
        }

        // A paid spin needs a bet; a spin taken during free spins must not
        // carry one, because the engine would debit it.
        if ($state->isInFreeSpins()) {
            if ($action->bet() !== null && !$action->bet()->isZero()) {
                throw new GameRuleException('Free spins are not wagered.');
            }

            return;
        }

        if ($action->bet() === null) {
            throw new GameRuleException('A spin requires a bet.');
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        if (!$state instanceof SlotState) {
            throw new GameRuleException('The session state is not a slot state.');
        }

        $configuration = $context->config();
        if (!$configuration instanceof SlotConfiguration) {
            throw new GameRuleException('The game configuration is invalid.');
        }

        $definition = $this->definition();
        $profile = $context->probabilityProfile();

        // The configuration handed to the spin carries the game's own settings
        // plus the administrator's probability policy. A feature that reads a
        // probability (the jackpot roll) therefore reads the effective value
        // for *this* round, resolved by the engine, never a cached one.
        $spinConfiguration = $configuration->toArray();
        $spinConfiguration['jackpot_probability'] = $profile->jackpotProbability ?? 0.0;
        $spinConfiguration['jackpot_tiers'] = $profile->jackpotTiers;
        // A feature factory may need to know which phase the spin belongs to —
        // a game whose multiplier ladder is steeper inside its free spins
        // builds a different feature for each. The state is authoritative, so
        // the flag is read from it rather than from the request.
        $spinConfiguration['is_free_spin'] = $state->isInFreeSpins();

        $forced = $this->forcedPositions($action);
        $engine = $this->buildEngine($configuration, $definition, $spinConfiguration);

        $spin = $this->spinWithPolicy($engine, $state, $context, $spinConfiguration, $profile, $forced);

        return $this->outcomeFor($spin, $profile, $definition);
    }

    /**
     * Runs the spin, applying the configured any-win / net-positive target.
     *
     * The governor re-draws from the *original* state on every attempt, so a
     * rejected draw consumes nothing: free spins are not spent and the grid
     * that is returned is the one whose draw was accepted.
     *
     * @param array<string, mixed> $spinConfiguration
     * @param list<int>|null $forced
     */
    private function spinWithPolicy(
        SlotEngine $engine,
        SlotState $state,
        GameContext $context,
        array $spinConfiguration,
        ProbabilityProfile $profile,
        ?array $forced
    ): SpinResult {
        $draw = fn (): SpinResult => $engine->spin($state, $context->rng(), $spinConfiguration, $forced);

        // A pinned reel position is a test hook: the caller is asserting an
        // exact grid, so re-drawing would discard the very outcome under test.
        if ($forced !== null || !$profile->isActive()) {
            return $draw();
        }

        $classify = static function (SpinResult $spin): OutcomeClassification {
            $paid = $spin->payoutMultiplier->toFloat();

            return new OutcomeClassification(
                $paid > 0.0,
                $paid > 1.0,
                false,
                false,
                $spin->hasJackpot(),
                $spin->jackpotTier()
            );
        };

        $governor = new WinFrequencyGovernor();
        $result = $governor->steer($profile, $draw, $classify);

        /** @var SpinResult $spin */
        $spin = $result['outcome'];

        return $spin;
    }

    private function outcomeFor(
        SpinResult $spin,
        ProbabilityProfile $profile,
        SlotGameDefinition $definition
    ): GameOutcomeInterface {
        $wagerMultiplier = $spin->isFreeSpin ? Ratio::zero() : Ratio::one();

        // Two adjustments can apply to a payout, and both are ratios so neither
        // loses precision. The definition's scale tunes the game's measured
        // return to its published one; the probability profile's scale is the
        // administrator's own multiplier. They multiply rather than replace one
        // another, so a configured scale does not erase the calibration.
        $payout = $spin->payoutMultiplier;
        if ($payout->numerator() > 0) {
            [$numerator, $denominator] = $definition->payoutScale();
            $payout = $payout->multiply(Ratio::of($numerator, $denominator));

            $scale = $profile->scale();
            if ($scale !== 1.0) {
                $payout = $payout->multiply(Ratio::fromNumber($scale));
            }
        }

        // Every one of the target titles publishes a maximum win, and a ways
        // game reaches it easily: 32,400 ways multiplied by any integer
        // paytable is millions of times the bet. The cap is therefore part of
        // the game's mathematics rather than a safety net — it is the
        // published figure, and applying it is what makes the measured return
        // the published one.
        $cap = Ratio::of($definition->metadata()->maxWinMultiplier(), 1);
        if ($payout->toFloat() > $cap->toFloat()) {
            $payout = $cap;
        }

        return new GameOutcome(
            $spin->nextState,
            $this->detailFor($spin),
            $payout,
            $wagerMultiplier,
            $spin->transitions,
            $this->eventsFor($spin)
        );
    }

    protected function detailFor(SpinResult $spin): SlotOutcomeDetail
    {
        return $spin->detail();
    }

    /**
     * @return list<array{name:string,payload:array<string,mixed>}>
     */
    protected function eventsFor(SpinResult $spin): array
    {
        $events = [];

        foreach ($spin->transitions as $transition) {
            if ($transition->type === 'free_spins' && $transition->status === FeatureTransition::STARTED) {
                $events[] = [
                    'name'    => 'game.free_spins.started',
                    'payload' => ['spins' => $transition->remaining],
                ];
            }
            if ($transition->type === 'jackpot' && $transition->status === FeatureTransition::STARTED) {
                $events[] = [
                    'name'    => 'game.jackpot.triggered',
                    'payload' => $transition->detail,
                ];
            }
        }

        return $events;
    }

    /** @param array<string, mixed> $spinConfiguration */
    private function buildEngine(
        SlotConfiguration $configuration,
        SlotGameDefinition $definition,
        array $spinConfiguration
    ): SlotEngine {
        return new SlotEngine(
            $definition->reels(),
            $definition->symbols(),
            $definition->paytable(),
            $definition->evaluator(),
            $definition->features($configuration, $spinConfiguration),
            $definition->unitDivisor(),
            $definition->maximumFeatureIterations()
        );
    }

    /**
     * Test hook: an action may pin the reel stop positions.
     *
     * @return list<int>|null
     */
    private function forcedPositions(GameActionInterface $action): ?array
    {
        $positions = $action->payload()['positions'] ?? null;
        if (!is_array($positions)) {
            return null;
        }

        /** @var list<int> $positions */
        return array_values(array_map('intval', $positions));
    }
}
