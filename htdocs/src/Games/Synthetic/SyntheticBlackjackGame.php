<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Synthetic;

use CasinoEngine\Core\Contract\FeatureTransition;
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
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\GameOutcome;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackHandEvaluator;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackRules;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackSettlement;
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Deck\Deck;
use CasinoEngine\Frameworks\Card\Hand\Hand;

/**
 * Synthetic single-hand blackjack.
 *
 * A test fixture built on the real card framework: a real shoe, the real
 * evaluator and the real settlement. It implements the actions a turn-based
 * card game needs — deal, hit, stand, double — so the engine's multi-action
 * session handling is exercised, not just its single-action path.
 *
 * Splits are deliberately out of scope for the fixture: they multiply the
 * session state and the bet structure, and the point of the fixture is to
 * prove the framework, not to reproduce every table rule.
 */
final class SyntheticBlackjackGame implements GameInterface
{
    public const ID = 'synthetic.blackjack';

    public function metadata(): GameMetadataInterface
    {
        $currency = 'PHP';

        return new GameMetadata(
            self::ID,
            'Synthetic Blackjack',
            GameCategory::Card,
            '1.0.0',
            '1.0.0',
            'Engine Test Fixtures',
            Money::of(100, $currency),
            Money::of(100000, $currency),
            Money::of(100, $currency),
            Volatility::Medium,
            5,
            0.995,
            true,
            ['synthetic', 'fixture', 'blackjack', 'single-hand']
        );
    }

    public function category(): GameCategory
    {
        return GameCategory::Card;
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return new SyntheticBlackjackConfiguration();
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof SyntheticBlackjackConfiguration) {
            throw new ConfigurationException('SyntheticBlackjackGame expects a SyntheticBlackjackConfiguration.');
        }

        $configuration->rules()->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return SyntheticBlackjackState::initial();
    }

    public function hydrateState(array $data): GameStateInterface
    {
        return SyntheticBlackjackState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if (!$state instanceof SyntheticBlackjackState) {
            throw new GameRuleException('The session state is not a blackjack state.');
        }

        $allowed = ['deal', 'hit', 'stand', 'double'];
        if (!in_array($action->name(), $allowed, true)) {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }

        if ($action->name() === 'deal') {
            if ($state->isInProgress()) {
                throw new GameRuleException('A hand is already in progress.');
            }
            if ($action->bet() === null) {
                throw new GameRuleException('A deal requires a bet.');
            }

            return;
        }

        if (!$state->isInProgress()) {
            throw new GameRuleException('There is no hand in progress.');
        }

        if ($action->name() === 'double') {
            if ($state->playerHand()->count() !== 2) {
                throw new GameRuleException('Doubling is only allowed on the first two cards.');
            }
            if ($action->bet() === null) {
                throw new GameRuleException('A double requires an additional bet.');
            }
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        if (!$state instanceof SyntheticBlackjackState) {
            throw new GameRuleException('The session state is not a blackjack state.');
        }

        $configuration = $context->config();
        if (!$configuration instanceof SyntheticBlackjackConfiguration) {
            throw new GameRuleException('The game configuration is invalid.');
        }

        return match ($action->name()) {
            'deal'   => $this->deal($action, $state, $context, $configuration),
            'hit'    => $this->hit($state, $context, $configuration),
            'stand'  => $this->stand($state, $context, $configuration),
            'double' => $this->double($action, $state, $context, $configuration),
            default  => throw new GameRuleException("Unknown action '{$action->name()}'."),
        };
    }

    private function deal(
        GameActionInterface $action,
        SyntheticBlackjackState $state,
        GameContext $context,
        SyntheticBlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $deck = Deck::shuffled($context->rng(), $rules->decks);

        $player = new Hand();
        $dealer = new Hand();

        // Real dealing order: player, dealer, player, dealer (dealer's second
        // card is the hole card).
        $player->add($deck->draw());
        $dealer->add($deck->draw());
        $player->add($deck->draw());
        $dealer->add($deck->draw());

        // The stake is recorded on the state so the hand can settle against
        // it later, when a stand or double completes the round.
        $stake = $action->bet();
        $state = $state->withHands(
            $player,
            $dealer,
            $deck->cards(),
            $rules,
            $stake?->minorUnits() ?? 0
        );

        // A natural resolves immediately: there is nothing for the player to
        // decide, and paying it here avoids a pointless round trip.
        $playerNatural = BlackjackHandEvaluator::isNatural($player, false);
        $dealerNatural = BlackjackHandEvaluator::isNatural($dealer, false);

        if ($playerNatural || $dealerNatural) {
            return $this->resolve(
                $state,
                $player,
                $dealer,
                $playerNatural,
                $dealerNatural,
                false,
                $rules
            );
        }

        $detail = $state->toArray();
        $detail['event'] = 'dealt';

        return new GameOutcome(
            $state,
            new \CasinoEngine\Core\Value\GenericOutcomeDetail('blackjack_deal', $detail),
            Ratio::zero(),
            Ratio::one(),
            [new FeatureTransition('blackjack', FeatureTransition::STARTED, 1, ['hand' => 'player'])]
        );
    }

    private function hit(
        SyntheticBlackjackState $state,
        GameContext $context,
        SyntheticBlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $deck = $state->deck();
        $player = $state->playerHand();

        $player->add($deck->draw());

        $state = $state->withHands($player, $state->dealerHand(), $deck->cards(), $rules);

        if (BlackjackHandEvaluator::isBust($player)) {
            return $this->resolve($state, $player, $state->dealerHand(), false, false, false, $rules);
        }

        $detail = $state->toArray();
        $detail['event'] = 'hit';

        return new GameOutcome(
            $state,
            new \CasinoEngine\Core\Value\GenericOutcomeDetail('blackjack_hit', $detail),
            Ratio::zero(),
            Ratio::zero(),
            [new FeatureTransition('blackjack', FeatureTransition::UPDATED, 1)]
        );
    }

    private function stand(
        SyntheticBlackjackState $state,
        GameContext $context,
        SyntheticBlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $deck = $state->deck();
        $dealer = $state->dealerHand();

        // The dealer plays out according to the configured soft-17 rule.
        while (BlackjackHandEvaluator::dealerShouldHit($dealer, $rules) && !$deck->isEmpty()) {
            $dealer->add($deck->draw());
        }

        $state = $state->withHands($state->playerHand(), $dealer, $deck->cards(), $rules);

        return $this->resolve(
            $state,
            $state->playerHand(),
            $dealer,
            false,
            BlackjackHandEvaluator::isNatural($dealer, false),
            false,
            $rules
        );
    }

    private function double(
        GameActionInterface $action,
        SyntheticBlackjackState $state,
        GameContext $context,
        SyntheticBlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $deck = $state->deck();
        $player = $state->playerHand();

        $player->add($deck->draw());

        // A double adds to the stake and then stands automatically, so the
        // dealer plays out immediately. The extra stake is folded into the
        // recorded total so the completed hand pays on everything committed.
        $additional = $action->bet()?->minorUnits() ?? 0;
        $state = $state->withHands(
            $player,
            $state->dealerHand(),
            $deck->cards(),
            $rules,
            $state->stakeMinor() + $additional
        );

        return $this->stand($state, $context, $configuration);
    }

    private function resolve(
        SyntheticBlackjackState $state,
        Hand $player,
        Hand $dealer,
        bool $playerNatural,
        bool $dealerNatural,
        bool $wasSplit,
        BlackjackRules $rules
    ): GameOutcomeInterface {
        $outcome = BlackjackSettlement::resolve(
            BlackjackHandEvaluator::total($player),
            BlackjackHandEvaluator::total($dealer),
            $playerNatural,
            $dealerNatural,
            BlackjackHandEvaluator::isBust($player),
            BlackjackHandEvaluator::isBust($dealer),
            $wasSplit
        );

        $multiplier = BlackjackSettlement::multiplier($outcome, $rules);

        $completed = $state->completed($outcome->toArray());

        $detail = $completed->toArray();
        $detail['outcome'] = $outcome->toArray();

        // The completing action (stand or double) carries no stake of its own,
        // so the outcome declares the round's total stake as the basis the
        // multiplier applies to. It comes from the state the module recorded,
        // never from the request.
        return new GameOutcome(
            $completed,
            new \CasinoEngine\Core\Value\GenericOutcomeDetail('blackjack_result', $detail),
            $multiplier,
            Ratio::one(),
            [new FeatureTransition('blackjack', FeatureTransition::ENDED, 0, $outcome->toArray())],
            [],
            Money::of($state->stakeMinor(), 'PHP')
        );
    }
}
