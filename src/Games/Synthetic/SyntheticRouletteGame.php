<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Synthetic;

use CasinoEngine\Core\Contract\GameActionInterface;
use CasinoEngine\Core\Contract\GameCategory;
use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Contract\GameInterface;
use CasinoEngine\Core\Contract\GameMetadataInterface;
use CasinoEngine\Core\Contract\GameOutcomeInterface;
use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Contract\Volatility;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\GameContext;
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
 * Synthetic roulette.
 *
 * A test fixture built on the real roulette framework. A single action places
 * a set of bets and spins once, which is how roulette is actually played: the
 * player commits every bet before the wheel turns.
 *
 * The bets arrive as a list of `{type, numbers, stake}` entries. Each is
 * validated against the wheel through the real factory, so an impossible bet
 * is rejected before any money moves.
 */
final class SyntheticRouletteGame implements GameInterface
{
    public const ID = 'synthetic.roulette';

    public function metadata(): GameMetadataInterface
    {
        $currency = 'PHP';

        return new GameMetadata(
            self::ID,
            'Synthetic Roulette',
            GameCategory::Roulette,
            '1.0.0',
            '1.0.0',
            'Engine Test Fixtures',
            Money::of(100, $currency),
            Money::of(100000, $currency),
            Money::of(100, $currency),
            Volatility::Medium,
            36,
            0.973,
            true,
            ['synthetic', 'fixture', 'roulette', 'european']
        );
    }

    public function category(): GameCategory
    {
        return GameCategory::Roulette;
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return new SyntheticRouletteConfiguration();
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof SyntheticRouletteConfiguration) {
            throw new ConfigurationException('SyntheticRouletteGame expects a SyntheticRouletteConfiguration.');
        }

        $configuration->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return SyntheticRouletteState::initial();
    }

    public function hydrateState(array $data): GameStateInterface
    {
        return SyntheticRouletteState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if ($action->name() !== 'spin') {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }

        if ($action->bet() === null) {
            throw new GameRuleException('A roulette spin requires a total stake.');
        }

        $bets = $action->payload()['bets'] ?? null;
        if (!is_array($bets) || $bets === []) {
            throw new GameRuleException('At least one bet is required.');
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        if (!$state instanceof SyntheticRouletteState) {
            throw new GameRuleException('The session state is not a roulette state.');
        }

        $configuration = $context->config();
        if (!$configuration instanceof SyntheticRouletteConfiguration) {
            throw new GameRuleException('The game configuration is invalid.');
        }

        $engine = new RouletteEngine(
            $configuration->variant(),
            new BetFactory($configuration->variant()),
            new RouletteSettlement()
        );

        $totalStake = $action->bet();
        if ($totalStake === null) {
            throw new GameRuleException('A roulette spin requires a total stake.');
        }

        $bets = $this->buildBets($action, $engine->betFactory(), $totalStake);

        // The submitted bet is the total of the individual stakes; a mismatch
        // means the client and server disagree about what is being wagered,
        // which must be rejected rather than reconciled.
        $staked = Money::zero($totalStake->currency(), $totalStake->scale());
        foreach ($bets as $bet) {
            $staked = $staked->add($bet->stake);
        }

        if (!$staked->equals($totalStake)) {
            throw new ValidationException('The submitted total does not match the sum of the individual bets.');
        }

        $spin = $engine->play($bets, $context->rng());

        $nextState = $state->withSpin($spin->toArray());

        return new GameOutcome(
            $nextState,
            new GenericOutcomeDetail('roulette_spin', $spin->toArray()),
            $spin->payoutMultiplier,
            Ratio::one()
        );
    }

    /**
     * @return list<Bet>
     */
    private function buildBets(
        GameActionInterface $action,
        BetFactory $factory,
        Money $totalStake
    ): array {
        $rawBets = $action->payload()['bets'] ?? [];
        if (!is_array($rawBets)) {
            throw new GameRuleException('The bets payload is malformed.');
        }

        $bets = [];

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

            $stake = Money::of((int) $stakeMinor, $totalStake->currency(), $totalStake->scale());

            /** @var list<string> $numbers */
            $numbers = array_values(array_map('strval', $numbers));

            $bets[] = $factory->make($type, $numbers, $stake);
        }

        return $bets;
    }
}
