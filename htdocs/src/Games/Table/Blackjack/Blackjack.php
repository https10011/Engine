<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Table\Blackjack;

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
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\GameMetadata;
use CasinoEngine\Core\Value\GameOutcome;
use CasinoEngine\Core\Value\GenericOutcomeDetail;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Core\Value\Ratio;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackHandEvaluator;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackRules;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackSettlement;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackStrategy;
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Deck\Deck;
use CasinoEngine\Frameworks\Card\Hand\Hand;

/**
 * Blackjack.
 *
 * A complete table implementation on the card framework: a real shoe, the real
 * evaluator and the real settlement, plus the actions a player actually takes —
 * deal, hit, stand, double, split, insurance and surrender. Splits repeat up to
 * the configured limit and every hand settles against its own stake, which is
 * what makes the round's payout a sum rather than a single number.
 *
 * The round is a state machine. `deal` opens it and either resolves it at once
 * (a natural, or a dealer natural after the peek) or leaves it in the insurance
 * phase and then the player phase. Each subsequent action advances it, and the
 * hand's total stake — including an extra bet for a double, a second stake for
 * a split, and the insurance premium — is carried on the state so the engine
 * settles against what was really committed rather than against the stake of
 * whichever action happened to finish the round.
 *
 * Every rule that varies between tables comes from {@see BlackjackConfiguration}
 * and none of it is hard-coded here.
 */
final class Blackjack implements GameInterface, SimulationStrategyInterface
{
    public const ID = 'table.blackjack';

    /** Dealer peeks for a natural when the up-card is an ace or a ten. */
    private const PEEK_RANKS = ['A', '10', 'J', 'Q', 'K'];

    public function metadata(): GameMetadataInterface
    {
        $currency = 'PHP';

        return new GameMetadata(
            self::ID,
            'Blackjack',
            GameCategory::Card,
            '1.0.0',
            '1.0.0',
            'Engine Original',
            Money::of(100, $currency),
            Money::of(500000, $currency),
            Money::of(100, $currency),
            Volatility::Low,
            5,
            0.995,
            true,
            ['blackjack', 'table', 'cards', 'split', 'insurance', 'surrender']
        );
    }

    public function category(): GameCategory
    {
        return GameCategory::Card;
    }

    public function defaultConfiguration(): GameConfigurationInterface
    {
        return new BlackjackConfiguration();
    }

    public function validateConfiguration(GameConfigurationInterface $configuration): void
    {
        if (!$configuration instanceof BlackjackConfiguration) {
            throw new ConfigurationException('Blackjack expects a BlackjackConfiguration.');
        }

        $configuration->rules()->validate();
    }

    public function createInitialState(GameContext $context): GameStateInterface
    {
        return BlackjackState::initial();
    }

    public function hydrateState(array $data): GameStateInterface
    {
        return BlackjackState::fromArray($data);
    }

    public function validateAction(GameActionInterface $action, GameStateInterface $state): void
    {
        if (!$state instanceof BlackjackState) {
            throw new GameRuleException('The session state is not a blackjack state.');
        }

        $allowed = ['deal', 'hit', 'stand', 'double', 'split', 'insurance', 'surrender'];
        if (!in_array($action->name(), $allowed, true)) {
            throw new GameRuleException("Unknown action '{$action->name()}'.");
        }

        if ($action->name() === 'deal') {
            if ($state->isInProgress()) {
                throw new GameRuleException('A hand is already in progress.');
            }
            if ($action->bet() === null || !$action->bet()->isPositive()) {
                throw new GameRuleException('A deal requires a bet.');
            }

            return;
        }

        if (!$state->isInProgress()) {
            throw new GameRuleException('There is no hand in progress.');
        }

        if ($action->name() === 'insurance') {
            if (!$state->isAwaitingInsurance()) {
                throw new GameRuleException('Insurance is not available on this hand.');
            }

            return;
        }

        if ($state->isAwaitingInsurance()) {
            throw new GameRuleException('Take or decline insurance before playing the hand.');
        }

        // Every remaining action is a player decision on the active hand.
        if ($action->name() === 'double' && $action->bet() === null) {
            throw new GameRuleException('A double requires an additional bet.');
        }

        if ($action->name() === 'split' && $action->bet() === null) {
            throw new GameRuleException('A split requires an additional bet.');
        }
    }

    public function execute(
        GameActionInterface $action,
        GameStateInterface $state,
        GameContext $context
    ): GameOutcomeInterface {
        if (!$state instanceof BlackjackState) {
            throw new GameRuleException('The session state is not a blackjack state.');
        }

        $configuration = $context->config();
        if (!$configuration instanceof BlackjackConfiguration) {
            throw new GameRuleException('The game configuration is invalid.');
        }

        return match ($action->name()) {
            'deal'      => $this->deal($action, $state, $context, $configuration),
            'hit'       => $this->hit($state, $context, $configuration),
            'stand'     => $this->stand($state, $configuration),
            'double'    => $this->double($action, $state, $context, $configuration),
            'split'     => $this->split($action, $state, $configuration),
            'insurance' => $this->insurance($action, $state, $configuration),
            'surrender' => $this->surrender($state, $configuration),
            default     => throw new GameRuleException("Unknown action '{$action->name()}'."),
        };
    }

    // ------------------------------------------------------------------ deal

    private function deal(
        GameActionInterface $action,
        BlackjackState $state,
        GameContext $context,
        BlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $deck = Deck::shuffled($context->rng(), $rules->decks);

        $player = new Hand();
        $dealer = new Hand();

        // Player, dealer, player, dealer: the dealer's second card is the hole
        // card. The order matters because the peek looks at the up-card, which
        // is the dealer's first.
        $player->add($deck->draw());
        $dealer->add($deck->draw());
        $player->add($deck->draw());
        $dealer->add($deck->draw());

        $stake = $action->bet();
        $stakeMinor = $stake?->minorUnits() ?? 0;

        $state = $state->withRound(
            [self::codes($player->cards())],
            self::codes($dealer->cards()),
            self::codes($deck->cards()),
            [$stakeMinor],
            [false],
            [false],
            [false],
            [],
            0,
            BlackjackState::PHASE_PLAYER,
            false,
            0,
            $state->handsPlayed()
        );

        // The peek comes first, and the order matters. A dealer natural beats
        // everything except a player natural, so paying the player's natural
        // before checking the hole card would hand out 3:2 on a hand that
        // should have pushed.
        if ($rules->dealerPeeks && self::isPeekCard($dealer)) {
            if (BlackjackHandEvaluator::isNatural($dealer, false)) {
                return $this->settleRound($state, $rules, 'dealt', Ratio::one());
            }
        }

        // Otherwise a player natural ends the round: there is nothing to
        // decide and paying it now avoids a pointless round trip.
        if (BlackjackHandEvaluator::isNatural($player, false)) {
            return $this->settleRound($state, $rules, 'dealt', Ratio::one());
        }

        // Insurance is offered only when the dealer shows an ace.
        if ($rules->insuranceEnabled && self::showsAce($dealer)) {
            $state = self::rebuild($state, phase: BlackjackState::PHASE_INSURANCE);

            return $this->pending($state, 'insurance_offered', 'blackjack_insurance', Ratio::one());
        }

        return $this->pending($state, 'dealt', 'blackjack_deal', Ratio::one());
    }

    // ------------------------------------------------------------------- hit

    private function hit(
        BlackjackState $state,
        GameContext $context,
        BlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $index = $state->activeHandIndex();
        $deck = $state->deck();
        $hand = $state->handAt($index);

        $hand->add($deck->draw());

        $hands = self::replaceHand($state, $index, $hand);
        $finished = self::flags($state, 'finished');
        $finished[$index] = BlackjackHandEvaluator::isBust($hand)
            || BlackjackHandEvaluator::total($hand) === BlackjackHandEvaluator::BLACKJACK;

        $state = self::rebuild($state, $hands, $deck, finished: $finished);

        if ($finished[$index]) {
            return $this->advance($state, $rules, 'hit', Ratio::zero());
        }

        return $this->pending($state, 'hit', 'blackjack_hit', Ratio::zero());
    }

    // ----------------------------------------------------------------- stand

    private function stand(
        BlackjackState $state,
        BlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();

        $finished = self::flags($state, 'finished');
        $finished[$state->activeHandIndex()] = true;

        return $this->advance(self::rebuild($state, finished: $finished), $rules, 'stand', Ratio::zero());
    }

    // ---------------------------------------------------------------- double

    private function double(
        GameActionInterface $action,
        BlackjackState $state,
        GameContext $context,
        BlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $index = $state->activeHandIndex();

        if ($state->cameFromSplit($index) && !$rules->doubleAfterSplit) {
            throw new GameRuleException('Doubling after a split is not allowed at this table.');
        }

        if ($state->handAt($index)->count() !== 2) {
            throw new GameRuleException('Doubling is only allowed on the first two cards.');
        }

        $deck = $state->deck();
        $hand = $state->handAt($index);
        $hand->add($deck->draw());

        // A double adds to the stake and then stands automatically. The extra
        // stake is folded into the recorded total so the completed hand pays on
        // everything committed.
        $stakes = $state->stakes();
        $stakes[$index] = ($stakes[$index] ?? 0) + ($action->bet()?->minorUnits() ?? 0);

        $doubled = self::flags($state, 'doubled');
        $doubled[$index] = true;

        $finished = self::flags($state, 'finished');
        $finished[$index] = true;

        $state = self::rebuild(
            $state,
            self::replaceHand($state, $index, $hand),
            $deck,
            stakes: $stakes,
            doubled: $doubled,
            finished: $finished
        );

        return $this->advance($state, $rules, 'double', Ratio::one());
    }

    // ----------------------------------------------------------------- split

    private function split(
        GameActionInterface $action,
        BlackjackState $state,
        BlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();
        $index = $state->activeHandIndex();
        $hand = $state->handAt($index);

        if ($state->handCount() >= $rules->maxHands) {
            throw new GameRuleException('The table does not allow more hands.');
        }

        if (!BlackjackHandEvaluator::isPair($hand)) {
            throw new GameRuleException('Only a pair of equal ranks may be split.');
        }

        $wouldResplitAces = BlackjackHandEvaluator::isPairOfAces($hand) && self::hasSplitAces($state);
        if ($wouldResplitAces && !$rules->resplitAces) {
            throw new GameRuleException('Split aces may not be split again.');
        }

        $cards = $hand->cards();
        $deck = $state->deck();

        // Each half is completed with a fresh card, as at a real table.
        $left = new Hand([$cards[0], $deck->draw()]);
        $right = new Hand([$cards[1], $deck->draw()]);

        $stakes = $state->stakes();
        $stakes[$index] = $stakes[$index] ?? 0;
        // The new hand carries the same stake as the hand it came from, which
        // the player pays with this action's bet.
        $stakes[] = $action->bet()?->minorUnits() ?? 0;

        $hands = $state->hands();
        $hands[$index] = self::codes($left->cards());
        $hands[] = self::codes($right->cards());

        $fromSplit = self::flags($state, 'from_split');
        $fromSplit[$index] = true;
        $fromSplit[] = true;

        $doubled = self::flags($state, 'doubled');
        $doubled[] = false;

        $finished = self::flags($state, 'finished');
        // A split ace receives one card and stops, unless the table hits them.
        $finished[$index] = BlackjackHandEvaluator::isPairOfAces($hand) && !$rules->hitSplitAces;
        $finished[] = $finished[$index];

        $state = $state->withRound(
            $hands,
            $state->dealerCards(),
            self::codes($deck->cards()),
            array_values($stakes),
            $doubled,
            $fromSplit,
            $finished,
            [],
            $index,
            BlackjackState::PHASE_PLAYER,
            $state->insuranceTaken(),
            $state->insuranceStakeMinor(),
            $state->handsPlayed()
        );

        // If the first half is already finished (split aces), move straight on.
        if ($finished[$index]) {
            return $this->advance($state, $rules, 'split', Ratio::one());
        }

        return $this->pending($state, 'split', 'blackjack_split', Ratio::one());
    }

    // ------------------------------------------------------------- insurance

    private function insurance(
        GameActionInterface $action,
        BlackjackState $state,
        BlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();

        $take = ($action->payload()['take'] ?? true) === true
            || $action->payload()['take'] === 1
            || $action->payload()['take'] === 'true';

        $stake = $state->stakeOf(0);
        $expectedPremium = (int) floor($stake / 2);

        // The premium is a real wager, so it must be submitted as the action's
        // bet and must equal half the original stake. Taking the money from the
        // request without checking it against the hand would let a client buy
        // insurance for any amount it liked.
        $premium = 0;
        if ($take) {
            $submitted = $action->bet()?->minorUnits() ?? 0;
            if ($submitted !== $expectedPremium) {
                throw new GameRuleException(
                    'The insurance premium must be half the original stake.'
                );
            }
            $premium = $submitted;
        }

        $state = self::rebuild(
            $state,
            stakes: array_fill(0, max(1, $state->handCount()), $stake),
            phase: BlackjackState::PHASE_PLAYER
        );

        $state = $state->withRound(
            $state->hands(),
            $state->dealerCards(),
            $state->shoe(),
            $state->stakes(),
            $state->doubledFlags(),
            $state->fromSplitFlags(),
            $state->finishedFlags(),
            [],
            0,
            BlackjackState::PHASE_PLAYER,
            $take,
            $premium,
            $state->handsPlayed()
        );

        // Insurance is only worth anything when the dealer's hole card is a
        // ten-valued card. A peek already happened, so a natural is impossible
        // here; the premium is simply won or lost with the round.
        return $this->pending(
            $state,
            $take ? 'insurance_taken' : 'insurance_declined',
            'blackjack_insurance',
            $take ? Ratio::one() : Ratio::zero()
        );
    }

    // ------------------------------------------------------------ surrender

    private function surrender(
        BlackjackState $state,
        BlackjackConfiguration $configuration
    ): GameOutcomeInterface {
        $rules = $configuration->rules();

        if (!$rules->surrenderEnabled) {
            throw new GameRuleException('Surrender is not offered at this table.');
        }

        if ($state->handAt($state->activeHandIndex())->count() !== 2) {
            throw new GameRuleException('Surrender is only available before acting.');
        }

        // Late surrender returns half the stake and ends the round.
        $state = $state->withRound(
            $state->hands(),
            $state->dealerCards(),
            $state->shoe(),
            $state->stakes(),
            self::flags($state, 'doubled'),
            self::flags($state, 'from_split'),
            array_fill(0, $state->handCount(), true),
            array_fill(0, $state->handCount(), 'surrender'),
            $state->activeHandIndex(),
            BlackjackState::PHASE_COMPLETE,
            $state->insuranceTaken(),
            $state->insuranceStakeMinor(),
            $state->handsPlayed() + 1
        );

        return $this->outcome($state, Ratio::of(1, 2), 'surrender', 'blackjack_result');
    }

    // ------------------------------------------------------------- lifecycle

    /**
     * Moves to the next hand that still needs playing, or plays the dealer out.
     */
    private function advance(
        BlackjackState $state,
        BlackjackRules $rules,
        string $event,
        Ratio $wager
    ): GameOutcomeInterface {
        $finished = self::flags($state, 'finished');

        $next = null;
        for ($i = $state->activeHandIndex() + 1; $i < $state->handCount(); $i++) {
            if (!($finished[$i] ?? true)) {
                $next = $i;
                break;
            }
        }

        if ($next !== null) {
            $state = self::rebuild($state, activeHand: $next);

            return $this->pending($state, $event, 'blackjack_' . $event, $wager);
        }

        return $this->settleRound($state, $rules, $event, $wager);
    }

    /**
     * Plays the dealer out and settles every hand.
     *
     * The dealer draws only when at least one hand is still live: if the player
     * busted every hand, or surrendered, there is nothing to beat and drawing
     * would only risk changing a settled result.
     */
    private function settleRound(
        BlackjackState $state,
        BlackjackRules $rules,
        string $event,
        Ratio $wager
    ): GameOutcomeInterface {
        $dealer = $state->dealerHand();
        $deck = $state->deck();

        // The dealer draws only if at least one hand can still beat him. If
        // every hand busted there is nothing to beat, and drawing would only
        // risk turning a settled loss into a meaningless dealer bust.
        $anyLive = false;
        for ($i = 0; $i < $state->handCount(); $i++) {
            if (!BlackjackHandEvaluator::isBust($state->handAt($i))) {
                $anyLive = true;
                break;
            }
        }

        $dealerNatural = BlackjackHandEvaluator::isNatural($dealer, false);

        if ($anyLive && !$dealerNatural) {
            while (BlackjackHandEvaluator::dealerShouldHit($dealer, $rules) && !$deck->isEmpty()) {
                $dealer->add($deck->draw());
            }
        }

        $dealerBust = BlackjackHandEvaluator::isBust($dealer);

        $total = Ratio::zero();
        $results = [];
        $detailHands = [];

        for ($i = 0; $i < $state->handCount(); $i++) {
            $hand = $state->handAt($i);
            $stake = $state->stakeOf($i);

            $outcome = BlackjackSettlement::resolve(
                BlackjackHandEvaluator::total($hand),
                BlackjackHandEvaluator::total($dealer),
                BlackjackHandEvaluator::isNatural($hand, $state->cameFromSplit($i)),
                $dealerNatural,
                BlackjackHandEvaluator::isBust($hand),
                $dealerBust,
                $state->cameFromSplit($i)
            );

            $results[] = $outcome->result;

            // A hand's contribution is its multiplier weighted by its share of
            // the round's stake, so a split hand that lost does not cancel a
            // hand that won.
            $multiplier = BlackjackSettlement::multiplier($outcome, $rules);
            $weight = Ratio::of($stake, 1);
            $total = $total->add($multiplier->multiply($weight));

            $detailHands[] = [
                'index'  => $i,
                'stake'  => $stake,
                'doubled' => $state->hasDoubled($i),
                'split'  => $state->cameFromSplit($i),
                'cards'  => array_map(static fn (Card $c): string => $c->code(), $hand->cards()),
                'total'  => BlackjackHandEvaluator::total($hand),
                'soft'   => $hand->isSoft(),
            ] + $outcome->toArray();
        }

        // The insurance premium is its own wager: it pays 2:1 when the dealer
        // has a natural and is lost otherwise.
        if ($state->insuranceTaken() && $state->insuranceStakeMinor() > 0) {
            $premium = $state->insuranceStakeMinor();
            $insuranceReturn = $dealerNatural
                ? Ratio::of($premium, 1)->multiply($rules->insurancePayout)
                : Ratio::zero();
            $total = $total->add($insuranceReturn);
        }

        $state = $state->withRound(
            $state->hands(),
            self::codes($dealer->cards()),
            self::codes($deck->cards()),
            $state->stakes(),
            self::flags($state, 'doubled'),
            self::flags($state, 'from_split'),
            array_fill(0, max(1, $state->handCount()), true),
            $results,
            $state->activeHandIndex(),
            BlackjackState::PHASE_COMPLETE,
            $state->insuranceTaken(),
            $state->insuranceStakeMinor(),
            $state->handsPlayed() + 1
        );

        $detail = $state->toArray();
        $detail['event'] = $event;
        $detail['hands'] = $detailHands;
        $detail['results'] = $results;
        $detail['dealer_cards'] = array_map(static fn (Card $c): string => $c->code(), $dealer->cards());
        $detail['dealer_total'] = BlackjackHandEvaluator::total($dealer);
        $detail['dealer_bust'] = $dealerBust;
        $detail['insurance'] = [
            'taken'   => $state->insuranceTaken(),
            'stake'   => $state->insuranceStakeMinor(),
            'paid'    => $state->insuranceTaken() && $dealerNatural,
        ];

        // The round's stake is every hand's stake plus the premium; the payout
        // is the weighted sum above, expressed in the same units. The engine
        // divides one by the other, which is why the multiplier is computed
        // against the state's own total rather than against the last action's
        // bet.
        $roundStake = $state->totalStakeMinor();
        $multiplier = $roundStake > 0
            ? Ratio::of((int) round($total->toFloat()), $roundStake)
            : Ratio::zero();

        return new GameOutcome(
            $state,
            new GenericOutcomeDetail('blackjack_result', $detail),
            $multiplier,
            $wager,
            [new FeatureTransition('blackjack', FeatureTransition::ENDED, 0, ['results' => $results])],
            [],
            Money::of($roundStake, 'PHP')
        );
    }

    /**
     * An intermediate state the player must act on.
     *
     * `$wager` is what the engine should debit for this action: one for an
     * action that opens or extends a hand (a deal, a double, a split, an
     * insurance premium) and zero for a pure decision (a hit or a stand). It
     * is passed in rather than inferred from the state because the engine
     * checks it against the action's own bet, and a mismatch there is a module
     * bug the runtime is right to refuse.
     */
    private function pending(
        BlackjackState $state,
        string $event,
        string $detailType,
        Ratio $wager
    ): GameOutcomeInterface {
        $detail = $state->toArray();
        $detail['event'] = $event;

        return new GameOutcome(
            $state,
            new GenericOutcomeDetail($detailType, $detail),
            Ratio::zero(),
            $wager,
            [new FeatureTransition('blackjack', FeatureTransition::UPDATED, 1, ['event' => $event])]
        );
    }

    private function outcome(
        BlackjackState $state,
        Ratio $multiplier,
        string $event,
        string $detailType
    ): GameOutcomeInterface {
        $detail = $state->toArray();
        $detail['event'] = $event;

        return new GameOutcome(
            $state,
            new GenericOutcomeDetail($detailType, $detail),
            $multiplier,
            Ratio::one(),
            [new FeatureTransition('blackjack', FeatureTransition::ENDED, 0, ['event' => $event])],
            [],
            Money::of($state->totalStakeMinor(), 'PHP')
        );
    }

    // ------------------------------------------------------------- simulation

    public function simulationAction(GameStateInterface $state, Money $bet): GameAction
    {
        return GameAction::withBet('deal', $bet);
    }

    /**
     * Drives a simulated hand with basic strategy.
     *
     * The continuation is also where the insurance decision is made: the
     * strategy declines it, because insurance is a negative-expectation bet
     * under every standard table rule and a simulation that measured a table
     * while always buying it would report a house edge the table does not have.
     */
    public function simulationContinuation(
        GameStateInterface $state,
        GameAction $previous
    ): ?GameAction {
        if (!$state instanceof BlackjackState) {
            return null;
        }

        if ($state->isAwaitingInsurance()) {
            return GameAction::create('insurance', ['take' => false]);
        }

        $index = $state->activeHandIndex();
        if ($state->isFinished($index)) {
            return null;
        }

        $hand = $state->handAt($index);
        $rules = new BlackjackRules();

        $canDouble = $hand->count() === 2
            && (!$state->cameFromSplit($index) || $rules->doubleAfterSplit);
        $canSplit = BlackjackHandEvaluator::isPair($hand)
            && $state->handCount() < $rules->maxHands;

        $play = BlackjackStrategy::decide($hand, $state->dealerHand(), $canDouble, $canSplit);

        if ($play === BlackjackStrategy::DOUBLE) {
            return GameAction::withBet('double', Money::of($state->stakeOf($index), 'PHP'));
        }

        if ($play === BlackjackStrategy::SPLIT) {
            return GameAction::withBet('split', Money::of($state->stakeOf($index), 'PHP'));
        }

        return GameAction::create($play);
    }

    public function simulationPolicy(): string
    {
        return 'multi-deck basic strategy; insurance declined';
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param list<Card> $cards
     * @return list<string>
     */
    private static function codes(array $cards): array
    {
        return array_map(static fn (Card $card): string => $card->code(), $cards);
    }

    private static function isPeekCard(Hand $dealer): bool
    {
        $up = $dealer->first();

        return $up !== null && in_array($up->rank->value, self::PEEK_RANKS, true);
    }

    private static function showsAce(Hand $dealer): bool
    {
        $up = $dealer->first();

        return $up !== null && $up->isAce();
    }

    private static function hasSplitAces(BlackjackState $state): bool
    {
        for ($i = 0; $i < $state->handCount(); $i++) {
            if (!$state->cameFromSplit($i)) {
                continue;
            }

            $cards = $state->handAt($i)->cards();
            if ($cards !== [] && $cards[0]->isAce()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<list<string>> */
    private static function replaceHand(BlackjackState $state, int $index, Hand $hand): array
    {
        $hands = $state->hands();
        $hands[$index] = self::codes($hand->cards());

        return $hands;
    }

    /**
     * @return list<bool>
     */
    private static function flags(BlackjackState $state, string $field): array
    {
        return match ($field) {
            'doubled'    => $state->doubledFlags(),
            'from_split' => $state->fromSplitFlags(),
            'finished'   => $state->finishedFlags(),
            default      => [],
        };
    }

    /**
     * Rebuilds the state with the given parts replaced.
     *
     * @param list<list<string>>|null $hands
     * @param list<int>|null $stakes
     * @param list<bool>|null $doubled
     * @param list<bool>|null $fromSplit
     * @param list<bool>|null $finished
     */
    private static function rebuild(
        BlackjackState $state,
        ?array $hands = null,
        ?Deck $deck = null,
        ?array $stakes = null,
        ?array $doubled = null,
        ?array $fromSplit = null,
        ?array $finished = null,
        ?int $activeHand = null,
        ?string $phase = null
    ): BlackjackState {
        return $state->withRound(
            $hands ?? $state->hands(),
            $state->dealerCards(),
            $deck !== null ? self::codes($deck->cards()) : $state->shoe(),
            $stakes ?? $state->stakes(),
            $doubled ?? $state->doubledFlags(),
            $fromSplit ?? $state->fromSplitFlags(),
            $finished ?? $state->finishedFlags(),
            $state->results(),
            $activeHand ?? $state->activeHandIndex(),
            $phase ?? $state->phase(),
            $state->insuranceTaken(),
            $state->insuranceStakeMinor(),
            $state->handsPlayed()
        );
    }
}
