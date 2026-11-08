<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Games\Table;

use CasinoEngine\Core\Contract\GameConfigurationInterface;
use CasinoEngine\Core\Exception\ConfigurationException;
use CasinoEngine\Core\Exception\GameRuleException;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\GameContext;
use CasinoEngine\Core\Value\GameAction;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackHandEvaluator;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackRules;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackStrategy;
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Hand\Hand;
use CasinoEngine\Games\Table\Blackjack\Blackjack;
use CasinoEngine\Games\Table\Blackjack\BlackjackConfiguration;
use CasinoEngine\Games\Table\Blackjack\BlackjackState;
use CasinoEngine\Core\Contract\Service\SystemClock;
use CasinoEngine\Services\Rng\RngFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Blackjack module tests.
 *
 * The rules are exercised against hands that are *constructed*, not drawn, so
 * the expected settlement is known exactly: a test that only asserted "the
 * round finished" would pass on a table that paid every hand at 1:1. The
 * simulation and integration tests cover the drawn-card path.
 */
final class BlackjackTest extends TestCase
{
    private function game(): Blackjack
    {
        return new Blackjack();
    }

    /**
     * A context whose RNG is deterministic, so a test that does need to draw
     * gets the same cards every run.
     */
    private function context(?BlackjackConfiguration $configuration = null): GameContext
    {
        $game = $this->game();

        return new GameContext(
            RngFactory::seeded(1234),
            $game->metadata(),
            $configuration ?? new BlackjackConfiguration(),
            new NullLogger(),
            new SystemClock()
        );
    }

    // ------------------------------------------------------------- evaluator

    public function testAnAceCountsAsElevenUntilThatWouldBust(): void
    {
        self::assertSame(21, (new Hand([Card::fromCode('AS'), Card::fromCode('KH')]))->blackjackTotal());
        // 11 + 10 + 10 is 31, so the ace drops to 1 and the hand is 21.
        self::assertSame(21, (new Hand([Card::fromCode('AS'), Card::fromCode('KH'), Card::fromCode('QH')]))->blackjackTotal());
        // 11 + 5 + 10 is 26, so the ace drops to 1 and the hand is 16.
        self::assertSame(16, (new Hand([Card::fromCode('AS'), Card::fromCode('5H'), Card::fromCode('KH')]))->blackjackTotal());
        // Two aces cannot both be eleven: 11 + 11 is 22, so one becomes a one.
        self::assertSame(12, (new Hand([Card::fromCode('AS'), Card::fromCode('AD')]))->blackjackTotal());
    }

    public function testASoftHandIsSoftOnlyWhileTheAceIsWorthEleven(): void
    {
        $soft = new Hand([Card::fromCode('AS'), Card::fromCode('6H')]);
        self::assertTrue($soft->isSoft());
        self::assertSame(17, $soft->blackjackTotal());

        $hard = new Hand([Card::fromCode('AS'), Card::fromCode('6H'), Card::fromCode('KH')]);
        self::assertFalse($hard->isSoft());
        self::assertSame(17, $hard->blackjackTotal());
    }

    public function testASplitTwentyOneIsNotANatural(): void
    {
        $hand = new Hand([Card::fromCode('AS'), Card::fromCode('KH')]);

        self::assertTrue(BlackjackHandEvaluator::isNatural($hand, false));
        self::assertFalse(BlackjackHandEvaluator::isNatural($hand, true), 'A split 21 pays even money.');
    }

    public function testTheDealerStandsOnSoft17ByDefaultAndHitsItWhenConfigured(): void
    {
        $soft17 = new Hand([Card::fromCode('AS'), Card::fromCode('6H')]);

        self::assertFalse(BlackjackHandEvaluator::dealerShouldHit($soft17, new BlackjackRules()));
        self::assertTrue(
            BlackjackHandEvaluator::dealerShouldHit($soft17, new BlackjackRules(dealerHitsSoft17: true))
        );
    }

    public function testTheDealerDrawsBelowSeventeen(): void
    {
        $sixteen = new Hand([Card::fromCode('10S'), Card::fromCode('6H')]);

        self::assertTrue(BlackjackHandEvaluator::dealerShouldHit($sixteen, new BlackjackRules()));
    }

    // -------------------------------------------------------------- strategy

    public function testBasicStrategyStandsOnSeventeenOrMore(): void
    {
        $hand = new Hand([Card::fromCode('10S'), Card::fromCode('7H')]);
        $dealer = new Hand([Card::fromCode('9S'), Card::fromCode('2H')]);

        self::assertSame(BlackjackStrategy::STAND, BlackjackStrategy::decide($hand, $dealer));
    }

    public function testBasicStrategyAlwaysSplitsAcesAndEights(): void
    {
        $dealer = new Hand([Card::fromCode('9S'), Card::fromCode('2H')]);

        self::assertSame(
            BlackjackStrategy::SPLIT,
            BlackjackStrategy::decide(new Hand([Card::fromCode('AS'), Card::fromCode('AH')]), $dealer, true, true)
        );
        self::assertSame(
            BlackjackStrategy::SPLIT,
            BlackjackStrategy::decide(new Hand([Card::fromCode('8S'), Card::fromCode('8H')]), $dealer, true, true)
        );
    }

    public function testBasicStrategyNeverSplitsTens(): void
    {
        $dealer = new Hand([Card::fromCode('6S'), Card::fromCode('2H')]);
        $play = BlackjackStrategy::decide(
            new Hand([Card::fromCode('10S'), Card::fromCode('KH')]),
            $dealer,
            true,
            true
        );

        self::assertSame(BlackjackStrategy::STAND, $play);
    }

    public function testADisallowedDoubleFallsBackToHit(): void
    {
        $hand = new Hand([Card::fromCode('6S'), Card::fromCode('5H')]);
        $dealer = new Hand([Card::fromCode('6S'), Card::fromCode('2H')]);

        self::assertSame(BlackjackStrategy::DOUBLE, BlackjackStrategy::decide($hand, $dealer, true));
        self::assertSame(
            BlackjackStrategy::HIT,
            BlackjackStrategy::decide($hand, $dealer, false),
            'A table that forbids doubling must fall back to a hit, not stand on 11.'
        );
    }

    // ---------------------------------------------------------- configuration

    public function testConfigurationRejectsAnUnknownKey(): void
    {
        $this->expectException(ConfigurationException::class);

        BlackjackConfiguration::fromArray(['dealer_hits_soft_17' => true, 'nonsense' => 1]);
    }

    public function testConfigurationRoundTripsThroughItsArrayForm(): void
    {
        $configuration = BlackjackConfiguration::fromArray([
            'decks'                => 2,
            'dealer_hits_soft_17'  => true,
            'double_after_split'   => false,
            'insurance_enabled'    => false,
            'surrender_enabled'    => true,
            'max_hands'            => 2,
        ]);

        $rules = $configuration->rules();

        self::assertSame(2, $rules->decks);
        self::assertTrue($rules->dealerHitsSoft17);
        self::assertFalse($rules->doubleAfterSplit);
        self::assertFalse($rules->insuranceEnabled);
        self::assertTrue($rules->surrenderEnabled);
        self::assertSame(2, $rules->maxHands);
    }

    public function testAConfigurationWithAnImpossibleDeckCountIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        (new BlackjackConfiguration(new BlackjackRules(decks: 99)))->validate();
    }

    // ---------------------------------------------------------- module wiring

    public function testTheModuleRegistersUnderItsOwnId(): void
    {
        self::assertSame('table.blackjack', Blackjack::ID);
        self::assertSame('table.blackjack', $this->game()->metadata()->id());
    }

    public function testADealOpensTheRoundAndDebitsTheStake(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $action = GameAction::withBet('deal', Money::of(1000, 'PHP'));
        $game->validateAction($action, $state);

        $outcome = $game->execute($action, $state, $context);

        self::assertSame(1.0, $outcome->wagerMultiplier()->toFloat(), 'The deal wagers the submitted stake.');
        self::assertInstanceOf(BlackjackState::class, $outcome->nextState());
    }

    public function testACannotDealTwiceIntoTheSameRound(): void
    {
        // A hand that is still open, constructed rather than dealt so the test
        // does not depend on the seeded deal happening to leave the round live.
        $state = new BlackjackState(
            hands: [['2S', '3H']],
            dealerCards: ['10S', '7H'],
            shoe: ['AS', 'KH'],
            stakes: [1000],
            doubled: [false],
            fromSplit: [false],
            finished: [false],
            results: [],
            activeHand: 0,
            phase: BlackjackState::PHASE_PLAYER
        );

        $this->expectException(GameRuleException::class);
        $this->expectExceptionMessageMatches('/already in progress/i');

        $this->game()->validateAction(
            GameAction::withBet('deal', Money::of(1000, 'PHP')),
            $state
        );
    }

    public function testADealWithoutABetIsRejected(): void
    {
        $game = $this->game();
        $state = $game->createInitialState($this->context());

        $this->expectException(GameRuleException::class);

        $game->validateAction(GameAction::create('deal'), $state);
    }

    public function testAnUnknownActionIsRejected(): void
    {
        $game = $this->game();
        $state = $game->createInitialState($this->context());

        $this->expectException(GameRuleException::class);
        $this->expectExceptionMessageMatches('/unknown action/i');

        $game->validateAction(GameAction::create('cheat'), $state);
    }

    public function testAnActionWithoutAHandInProgressIsRejected(): void
    {
        $game = $this->game();
        $state = $game->createInitialState($this->context());

        $this->expectException(GameRuleException::class);
        $this->expectExceptionMessageMatches('/no hand in progress/i');

        $game->validateAction(GameAction::create('hit'), $state);
    }

    // ------------------------------------------------------ state round trip

    public function testStateSurvivesASerialisationRoundTrip(): void
    {
        $state = new BlackjackState(
            hands: [['AS', 'KH']],
            dealerCards: ['10S', '7H'],
            shoe: ['2S', '3D'],
            stakes: [1000],
            doubled: [false],
            fromSplit: [false],
            finished: [false],
            results: [],
            activeHand: 0,
            phase: BlackjackState::PHASE_PLAYER,
            insuranceTaken: false,
            insuranceStakeMinor: 0,
            handsPlayed: 3
        );

        $restored = BlackjackState::fromArray($state->toArray());

        self::assertSame($state->toArray(), $restored->toArray());
        self::assertSame(21, $restored->handAt(0)->blackjackTotal());
        self::assertSame(3, $restored->handsPlayed());
        self::assertSame(1000, $restored->stakeOf(0));
    }

    public function testARestoredShoeContinuesFromTheSameCard(): void
    {
        $state = new BlackjackState(
            hands: [['2S', '3H']],
            dealerCards: ['10S', '7H'],
            shoe: ['AS', 'KH'],
            stakes: [100],
            doubled: [false],
            fromSplit: [false],
            finished: [false],
            results: [],
            activeHand: 0,
            phase: BlackjackState::PHASE_PLAYER
        );

        $restored = BlackjackState::fromArray($state->toArray());

        self::assertSame('KH', $restored->deck()->draw()->code(), 'The shoe draws from the top, as it did before.');
    }

    public function testAStateFromANewerEngineIsRefused(): void
    {
        $this->expectException(ValidationException::class);

        BlackjackState::fromArray(['schema' => 99]);
    }

    public function testAnEmptyRoundIsComplete(): void
    {
        self::assertTrue(BlackjackState::initial()->isComplete());
        self::assertFalse(BlackjackState::initial()->isInProgress());
    }

    // ------------------------------------------------------ full round shape

    /**
     * Plays a round to completion with the module's own strategy, asserting the
     * round settles and reports a stake basis the engine can pay against.
     */
    public function testARoundPlayedWithBasicStrategySettles(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $bet = Money::of(1000, 'PHP');
        $action = GameAction::withBet('deal', $bet);
        $outcome = $game->execute($action, $state, $context);
        $state = $outcome->nextState();

        $guard = 0;
        while ($state instanceof BlackjackState && $state->isInProgress() && $guard++ < 40) {
            $next = $game->simulationContinuation($state, $action);
            self::assertNotNull($next, 'A round in progress must always have a next action.');

            $game->validateAction($next, $state);
            $outcome = $game->execute($next, $state, $context);
            $state = $outcome->nextState();
            $action = $next;
        }

        self::assertInstanceOf(BlackjackState::class, $state);
        self::assertTrue($state->isComplete(), 'The round must terminate.');
        self::assertGreaterThan(0, $state->totalStakeMinor());
        self::assertNotSame([], $state->results());
    }

    /**
     * The reported payout is a multiple of the round's total stake, which is
     * what lets the engine settle a multi-hand round from one number.
     */
    public function testTheSettledMultiplierAppliesToTheWholeRoundStake(): void
    {
        $game = $this->game();
        $context = $this->context();
        $state = $game->createInitialState($context);

        $bet = Money::of(1000, 'PHP');
        $action = GameAction::withBet('deal', $bet);
        $outcome = $game->execute($action, $state, $context);
        $state = $outcome->nextState();

        $guard = 0;
        while ($state instanceof BlackjackState && $state->isInProgress() && $guard++ < 40) {
            $next = $game->simulationContinuation($state, $action);
            self::assertNotNull($next);
            $outcome = $game->execute($next, $state, $context);
            $state = $outcome->nextState();
            $action = $next;
        }

        $basis = $outcome->stakeBasis();
        self::assertNotNull($basis);
        self::assertInstanceOf(BlackjackState::class, $state);
        self::assertSame($state->totalStakeMinor(), $basis->minorUnits());

        $paid = $outcome->payoutMultiplier()->applyToMinorUnits($basis->minorUnits());

        // Every settled result is one of the standard payouts, so the total is
        // one of a small set of multiples of the stake.
        self::assertGreaterThanOrEqual(0, $paid);
        self::assertLessThanOrEqual($basis->minorUnits() * 10, $paid);
    }

    public function testInsuranceIsOfferedOnlyWhenTheDealerShowsAnAce(): void
    {
        $game = $this->game();
        $sawAce = false;

        // Walk a range of seeds until a deal where the dealer shows an ace
        // turns up, and assert the phase on every deal seen along the way.
        for ($seed = 1; $seed <= 400 && !$sawAce; $seed++) {
            $context = new GameContext(
                RngFactory::seeded($seed),
                $game->metadata(),
                new BlackjackConfiguration(),
                new NullLogger(),
                new SystemClock()
            );

            $state = $game->createInitialState($context);
            $outcome = $game->execute(GameAction::withBet('deal', Money::of(1000, 'PHP')), $state, $context);
            $next = $outcome->nextState();

            self::assertInstanceOf(BlackjackState::class, $next);

            if ($next->isComplete()) {
                continue;
            }

            $showsAce = $next->dealerHand()->first()?->isAce() ?? false;

            if ($showsAce) {
                self::assertTrue($next->isAwaitingInsurance());
                $sawAce = true;

                continue;
            }

            self::assertFalse($next->isAwaitingInsurance(), 'Insurance is offered only against an ace.');
        }

        self::assertTrue($sawAce, 'A dealer ace should appear within 400 seeded deals.');
    }

    public function testInsuranceRequiresExactlyHalfTheStake(): void
    {
        $state = new BlackjackState(
            hands: [['10S', '7H']],
            dealerCards: ['AS', '9H'],
            shoe: ['2S', '3D'],
            stakes: [1000],
            doubled: [false],
            fromSplit: [false],
            finished: [false],
            results: [],
            activeHand: 0,
            phase: BlackjackState::PHASE_INSURANCE
        );

        $game = $this->game();
        $context = $this->context();

        $this->expectException(GameRuleException::class);
        $this->expectExceptionMessageMatches('/half the original stake/i');

        $game->execute(
            GameAction::withBet('insurance', Money::of(999, 'PHP'), ['take' => true]),
            $state,
            $context
        );
    }

    public function testInsuranceWagersThePremiumAndDecliningWagersNothing(): void
    {
        $state = new BlackjackState(
            hands: [['10S', '7H']],
            dealerCards: ['AS', '9H'],
            shoe: ['2S', '3D'],
            stakes: [1000],
            doubled: [false],
            fromSplit: [false],
            finished: [false],
            results: [],
            activeHand: 0,
            phase: BlackjackState::PHASE_INSURANCE
        );

        $game = $this->game();
        $context = $this->context();

        $taken = $game->execute(
            GameAction::withBet('insurance', Money::of(500, 'PHP'), ['take' => true]),
            $state,
            $context
        );

        self::assertSame(1.0, $taken->wagerMultiplier()->toFloat());
        $next = $taken->nextState();
        self::assertInstanceOf(BlackjackState::class, $next);
        self::assertSame(500, $next->insuranceStakeMinor());

        $declined = $game->execute(
            GameAction::create('insurance', ['take' => false]),
            $state,
            $context
        );

        self::assertSame(0.0, $declined->wagerMultiplier()->toFloat());
        $next = $declined->nextState();
        self::assertInstanceOf(BlackjackState::class, $next);
        self::assertSame(0, $next->insuranceStakeMinor());
    }

    public function testSurrenderIsRefusedWhenTheTableDoesNotOfferIt(): void
    {
        $state = new BlackjackState(
            hands: [['10S', '7H']],
            dealerCards: ['9S', '9H'],
            shoe: ['2S', '3D'],
            stakes: [1000],
            doubled: [false],
            fromSplit: [false],
            finished: [false],
            results: [],
            activeHand: 0,
            phase: BlackjackState::PHASE_PLAYER
        );

        $game = $this->game();
        $context = $this->context(new BlackjackConfiguration(new BlackjackRules(surrenderEnabled: false)));

        $this->expectException(GameRuleException::class);
        $this->expectExceptionMessageMatches('/not offered/i');

        $game->execute(GameAction::create('surrender'), $state, $context);
    }

    public function testSurrenderReturnsHalfTheStake(): void
    {
        $state = new BlackjackState(
            hands: [['10S', '7H']],
            dealerCards: ['9S', '9H'],
            shoe: ['2S', '3D'],
            stakes: [1000],
            doubled: [false],
            fromSplit: [false],
            finished: [false],
            results: [],
            activeHand: 0,
            phase: BlackjackState::PHASE_PLAYER
        );

        $game = $this->game();
        $context = $this->context(new BlackjackConfiguration(new BlackjackRules(surrenderEnabled: true)));

        $outcome = $game->execute(GameAction::create('surrender'), $state, $context);

        self::assertSame(0.5, $outcome->payoutMultiplier()->toFloat());
    }

    /**
     * The strategy tables are hand-typed strings, so they are checked for shape
     * as well as for content: a row one letter short would silently shift every
     * later up-card and misplay hands without ever failing a play test.
     */
    public function testEveryStrategyRowIsTenLettersOfKnownActions(): void
    {
        $table = BlackjackStrategy::table();
        $expected = ['up_cards' => 10, 'hard' => 14, 'soft' => 9, 'pairs' => 13];

        self::assertCount($expected['up_cards'], $table['up_cards']);
        self::assertCount($expected['hard'], $table['hard']);
        self::assertCount($expected['soft'], $table['soft']);
        self::assertCount($expected['pairs'], $table['pairs']);

        foreach (['hard', 'soft', 'pairs'] as $section) {
            foreach ($table[$section] as $key => $row) {
                self::assertSame(10, strlen($row), "$section row $key must have one letter per up-card.");
                self::assertMatchesRegularExpression('/^[HSDP]{10}$/', $row, "$section row $key has an unknown letter.");
            }
        }

        self::assertSame(array_keys($table['hard']), range(8, 21), 'Every hard total from 8 to 21 has a row.');
        self::assertSame(array_keys($table['soft']), range(13, 21), 'Every soft total from 13 to 21 has a row.');
    }

    /**
     * The strategy's own claims, spot-checked against the published chart, so a
     * transposed row is caught even though the shape check would pass.
     */
    public function testTheStrategyMatchesThePublishedChartAtKnownPoints(): void
    {
        $dealerSix = new Hand([Card::fromCode('6S'), Card::fromCode('2H')]);
        $dealerTen = new Hand([Card::fromCode('10S'), Card::fromCode('2H')]);
        $dealerFive = new Hand([Card::fromCode('5S'), Card::fromCode('2H')]);

        // 16 against a six stands; against a ten it hits.
        $sixteen = new Hand([Card::fromCode('10S'), Card::fromCode('6H')]);
        self::assertSame(BlackjackStrategy::STAND, BlackjackStrategy::decide($sixteen, $dealerSix));
        self::assertSame(BlackjackStrategy::HIT, BlackjackStrategy::decide($sixteen, $dealerTen));

        // Soft 18 doubles against a six and stands against a ten.
        $soft18 = new Hand([Card::fromCode('AS'), Card::fromCode('7H')]);
        self::assertSame(BlackjackStrategy::DOUBLE, BlackjackStrategy::decide($soft18, $dealerSix, true));
        self::assertSame(BlackjackStrategy::STAND, BlackjackStrategy::decide($soft18, $dealerTen));

        // A pair of fives is a double, not a split, against a five.
        $fives = new Hand([Card::fromCode('5S'), Card::fromCode('5H')]);
        self::assertSame(BlackjackStrategy::DOUBLE, BlackjackStrategy::decide($fives, $dealerFive, true, true));
    }

    public function testTheMaximumWinCapIsRespectedByTheMetadata(): void
    {
        self::assertSame(5, $this->game()->metadata()->maxWinMultiplier());
    }

    public function testTheModuleAdvertisesItsConfigurationObject(): void
    {
        $game = $this->game();
        $configuration = $game->defaultConfiguration();

        self::assertInstanceOf(BlackjackConfiguration::class, $configuration);

        // A wrong configuration type is a wiring error, not a player error.
        $this->expectException(ConfigurationException::class);
        $game->validateConfiguration(new class implements GameConfigurationInterface {
            public static function fromArray(array $values): static
            {
                return new static();
            }

            public function toArray(): array
            {
                return [];
            }

            public function validate(): void
            {
            }
        });
    }
}
