<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Table\Roulette;

use CasinoEngine\Core\Contract\FeatureTransition;
use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\SimulationStrategyInterface;
use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\GameOutcome;
use CasinoEngine\Core\Value\GenericOutcomeDetail;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Roulette\Bet\Bet;
use CasinoEngine\Frameworks\Roulette\Bet\BetFactory;
use CasinoEngine\Frameworks\Roulette\Bet\BetType;
use CasinoEngine\Frameworks\Roulette\RouletteEngine;
use CasinoEngine\Frameworks\Roulette\Settlement\RouletteSettlement;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;

/**
 * Roulette.
 *
 * A complete table implementation on the roulette framework, supporting the
 * three standard wheels. A round is two actions: `place_bets` validates a slip
 * and parks it on the state without wagering anything, and `spin` commits it
 * and settles the result. Splitting them is what makes the interface work the
 * way a real table does — the player can build, adjust and clear a slip before
 * committing — and it is also why a round is atomic only at the spin: nothing
 * is at risk until the wheel turns.
 *
 * Every bet is validated through the framework's factory, so an impossible bet
 * (a street that is not three consecutive numbers, a colour that does not
 * exist on this wheel) is rejected before any money moves. The submitted total
 * must equal the sum of the individual stakes, because a client and server that
 * disagree about what is being wagered must not be silently reconciled.
 */
final class Roulette implements GameInterface, SimulationStrategyInterface
{
    public const ID = 'table.roulette';

    public function metadata(): GameMetadataInterface
    {
        $currency = 'PHP';

        return new GameMetadata(
            self::ID,
            'Roulette',
            GameCategory::Roulette,
            '1.0.0',
            '1.0.0',
            'Engine Original',
            Money::of(100, $currency),
            Money::of(1000000, $currency),
            Money::of(100, $currency),
            Volatility::Medium,
            36,
            0.973,
            true,
            ['roulette', 'table', 'european', 'american', 'french', 'inside', 'outside']
        );
    }

    public function category(): GameCategory
    {
        return GameCategory::Roulette;
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return new RouletteConfiguration();
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof RouletteConfiguration) {
            throw new ConfigurationException('Roulette expects a RouletteConfiguration.');
        }

        $configuration->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return RouletteState::initial();
    }

    public function hydrateState(array $data): GameStateInterface
    {
        return RouletteState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if (!$state instanceof RouletteState) {
            throw new GameRuleException('The session state is not a roulette state.');
        }

        $allowed = ['place_bets', 'clear_bets', 'spin'];
        if (!in_array($action->name(), $allowed, true)) {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }

        if ($action->name() === 'clear_bets') {
            return;
        }

        $bets = $action->payload()['bets'] ?? null;
        if (!is_array($bets) || $bets === []) {
            throw new GameRuleException('At least one bet is required.');
        }

        if ($action->name() === 'place_bets') {
            // A slip is placed without a stake; the engine has nothing to
            // debit, so the action must not claim a bet.
            if ($action->bet() !== null && $action->bet()->isPositive()) {
                throw new GameRuleException('Placing a slip does not wager anything; the spin does.');
            }

            return;
        }

        // A spin must carry the slip's total as its stake.
        if ($action->bet() === null || !$action->bet()->isPositive()) {
            throw new GameRuleException('A spin requires the total stake.');
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        if (!$state instanceof RouletteState) {
            throw new GameRuleException('The session state is not a roulette state.');
        }

        $configuration = $context->config();
        if (!$configuration instanceof RouletteConfiguration) {
            throw new GameRuleException('The game configuration is invalid.');
        }

        return match ($action->name()) {
            'place_bets' => $this->placeBets($action, $state, $configuration),
            'clear_bets' => $this->clearBets($state),
            'spin'       => $this->spin($action, $state, $context, $configuration),
            default      => throw new GameRuleException("Unknown action '{$action->name()}'."),
        };
    }

    private function placeBets(
        GameActionInterface $action,
        RouletteState $state,
        RouletteConfiguration $configuration
    ): GameOutcomeInterface {
        $bets = $this->parseBets($action, $configuration);
        $next = $state->withBets(self::slip($bets));

        $detail = $next->toArray();
        $detail['event'] = 'bets_placed';

        return new GameOutcome(
            $next,
            new GenericOutcomeDetail('roulette_bets', $detail),
            Ratio::zero(),
            Ratio::one(),
            [new FeatureTransition('roulette', FeatureTransition::UPDATED, 1, ['bets' => count($bets)])]
        );
    }

    private function clearBets(RouletteState $state): GameOutcomeInterface
    {
        $next = $state->withBets([]);

        $detail = $next->toArray();
        $detail['event'] = 'bets_cleared';

        return new GameOutcome(
            $next,
            new GenericOutcomeDetail('roulette_bets', $detail),
            Ratio::zero(),
            Ratio::one(),
            [new FeatureTransition('roulette', FeatureTransition::UPDATED, 0)]
        );
    }

    private function spin(
        GameActionInterface $action,
        RouletteState $state,
        GameContext $context,
        RouletteConfiguration $configuration
    ): GameOutcomeInterface {
        $variant = $configuration->variant();
        $engine = new RouletteEngine($variant, new BetFactory($variant), new RouletteSettlement());

        // The slip on the state is authoritative. The payload is re-validated
        // against it rather than trusted, so a client cannot spin a different
        // slip from the one the server recorded.
        $submitted = $this->parseBets($action, $configuration);
        if ($this->fingerprint($submitted) !== $this->fingerprint($state->bets())) {
            throw new ValidationException('The submitted slip does not match the placed slip.');
        }

        $totalStake = $action->bet();
        if ($totalStake === null) {
            throw new GameRuleException('A spin requires the total stake.');
        }

        $staked = Money::zero($totalStake->currency(), $totalStake->scale());
        foreach ($submitted as $bet) {
            $staked = $staked->add($bet->stake);
        }

        if (!$staked->equals($totalStake)) {
            throw new ValidationException('The submitted total does not match the sum of the individual bets.');
        }

        $spin = $engine->play($submitted, $context->rng());
        $next = $state->settled($spin->toArray(), $spin->pocket);

        $detail = $spin->toArray();
        $detail['event'] = 'spin';
        $detail['history'] = $next->history();

        return new GameOutcome(
            $next,
            new GenericOutcomeDetail('roulette_spin', $detail),
            $spin->payoutMultiplier,
            Ratio::one(),
            [new FeatureTransition('roulette', FeatureTransition::ENDED, 0, ['pocket' => $spin->pocket])]
        );
    }

    /**
     * Parses, validates and limit-checks a slip.
     *
     * @return list<Bet>
     */
    private function parseBets(GameActionInterface $action, RouletteConfiguration $configuration): array
    {
        $variant = $configuration->variant();
        $factory = new BetFactory($variant);

        $rawBets = $action->payload()['bets'] ?? [];
        if (!is_array($rawBets)) {
            throw new GameRuleException('The bets payload is malformed.');
        }

        $currency = 'PHP';
        $scale = 2;
        $bets = [];
        $total = 0;

        foreach ($rawBets as $index => $raw) {
            if (!is_array($raw)) {
                throw new GameRuleException("Bet $index is malformed.");
            }

            $typeValue = $raw['type'] ?? null;
            if (!is_string($typeValue)) {
                throw new GameRuleException("Bet $index is missing a type.");
            }

            $type = BetType::tryFrom($typeValue);
            if ($type === null) {
                throw new GameRuleException("Bet $index has unknown type '$typeValue'.");
            }

            $numbers = $raw['numbers'] ?? null;
            if (!is_array($numbers) || $numbers === []) {
                throw new GameRuleException("Bet $index is missing its numbers.");
            }

            $stakeMinor = $raw['stake_minor'] ?? null;
            if (!is_int($stakeMinor) && !is_numeric($stakeMinor)) {
                throw new GameRuleException("Bet $index is missing its stake.");
            }

            $stake = (int) $stakeMinor;

            if ($stake < $configuration->minBetMinor()) {
                throw new ValidationException(
                    "Bet $index is below the table minimum of {$configuration->minBetMinor()}."
                );
            }

            if ($stake > $configuration->maxBetMinor()) {
                throw new ValidationException(
                    "Bet $index exceeds the table maximum of {$configuration->maxBetMinor()}."
                );
            }

            $typeLimit = $type->isInside()
                ? $configuration->maxStraightBetMinor()
                : $configuration->maxOutsideBetMinor();

            if ($stake > $typeLimit) {
                throw new ValidationException(
                    "Bet $index exceeds the {$typeValue} limit of $typeLimit."
                );
            }

            $total += $stake;
            if ($total > $configuration->maxTotalBetMinor()) {
                throw new ValidationException(
                    "The slip exceeds the table total of {$configuration->maxTotalBetMinor()}."
                );
            }

            /** @var list<string> $numbers */
            $numbers = array_values(array_map('strval', $numbers));

            $bets[] = $factory->make($type, $numbers, Money::of($stake, $currency, $scale));
        }

        if ($bets === []) {
            throw new GameRuleException('At least one bet is required.');
        }

        return $bets;
    }

    /**
     * A slip as the state stores it.
     *
     * @param list<Bet> $bets
     * @return list<array{type:string,numbers:list<string>,stake_minor:int}>
     */
    private static function slip(array $bets): array
    {
        $slip = [];
        foreach ($bets as $bet) {
            $slip[] = [
                'type'        => $bet->type->value,
                'numbers'     => array_values($bet->numbers),
                'stake_minor' => $bet->stake->minorUnits(),
            ];
        }

        return $slip;
    }

    /**
     * A stable fingerprint of a slip, used to check the payload against the
     * recorded one without depending on key order.
     *
     * @param list<Bet|array<string,mixed>> $bets
     */
    private function fingerprint(array $bets): string
    {
        $normalised = [];
        foreach ($bets as $bet) {
            if ($bet instanceof Bet) {
                $numbers = $bet->numbers;
                sort($numbers);
                $normalised[] = $bet->type->value . ':' . implode(',', $numbers) . ':' . $bet->stake->minorUnits();
                continue;
            }

            $numbers = is_array($bet['numbers'] ?? null) ? $bet['numbers'] : [];
            $numbers = array_map('strval', $numbers);
            sort($numbers);
            $normalised[] = ($bet['type'] ?? '') . ':' . implode(',', $numbers) . ':' . (int) ($bet['stake_minor'] ?? 0);
        }

        sort($normalised);

        return implode('|', $normalised);
    }

    // ------------------------------------------------------------- simulation

    public function simulationAction(GameStateInterface $state, Money $bet): GameAction
    {
        return GameAction::create('place_bets', ['bets' => self::simulationSlip($bet)]);
    }

    /**
     * @return list<array{type:string,numbers:list<string>,stake_minor:int}>
     */
    private static function simulationSlip(Money $bet): array
    {
        // A fixed, documented pattern: half on red, half straight-up on 17.
        // It is a reproducible policy, not an optimal one, and it is reported
        // as such.
        $variant = RouletteConfiguration::defaultVariant();
        $factory = new BetFactory($variant);

        $reds = [];
        foreach ($variant->pockets() as $pocket) {
            if ($pocket !== '0' && $pocket !== '00' && $variant->colourOf($pocket) === 'red') {
                $reds[] = $pocket;
            }
        }
        usort($reds, static fn (string $a, string $b): int => (int) $a <=> (int) $b);

        $half = intdiv($bet->minorUnits(), 2);
        $remainder = $bet->minorUnits() - $half;

        // A split is only legal when each half clears the table minimum. At a
        // bet too small to split, the whole stake goes on red: the pattern
        // changes, and the report says which one was used, rather than the
        // simulation quietly submitting a bet the table would reject.
        $splittable = $half >= RouletteConfiguration::fromArray([])->minBetMinor();

        $bets = $splittable
            ? [
                ['type' => 'red', 'numbers' => $reds, 'stake_minor' => $half],
                ['type' => 'straight', 'numbers' => ['17'], 'stake_minor' => $remainder],
            ]
            : [
                ['type' => 'red', 'numbers' => $reds, 'stake_minor' => $bet->minorUnits()],
            ];

        // The factory call validates the pattern against the wheel, so a change
        // to the wheel cannot silently make the simulation's bets illegal.
        foreach ($bets as $pattern) {
            $factory->make(
                BetType::from($pattern['type']),
                $pattern['numbers'],
                Money::of((int) $pattern['stake_minor'], 'PHP')
            );
        }

        return $bets;
    }

    /**
     * The slip is placed first and the wheel spun second, so the simulation
     * exercises the same two-action round a real player uses.
     */
    public function simulationContinuation(GameStateInterface $state, GameAction $previous): ?GameAction
    {
        if (!$state instanceof RouletteState) {
            return null;
        }

        $total = $state->totalStakeMinor();
        if ($total <= 0) {
            return null;
        }

        return GameAction::withBet('spin', Money::of($total, 'PHP'), ['bets' => $state->bets()]);
    }

    public function simulationPolicy(): string
    {
        return 'half on red, half straight-up on 17';
    }
}
