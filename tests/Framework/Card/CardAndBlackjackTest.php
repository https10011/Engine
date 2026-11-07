<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Framework\Card;

use CasinoEngine\Frameworks\Card\Blackjack\BlackjackHandEvaluator;
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Card\Rank;
use CasinoEngine\Frameworks\Card\Card\Suit;
use CasinoEngine\Frameworks\Card\Deck\Deck;
use CasinoEngine\Frameworks\Card\Hand\Hand;
use CasinoEngine\Services\Rng\RngFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cards, decks and blackjack hand arithmetic.
 *
 * The ace is the whole difficulty of blackjack evaluation: it is 11 or 1
 * depending on the rest of the hand, and getting that wrong changes every
 * result. These cases pin the soft/hard boundary down.
 */
final class CardAndBlackjackTest extends TestCase
{
    private function hand(string ...$codes): Hand
    {
        $hand = new Hand();
        foreach ($codes as $code) {
            $hand->add(Card::fromCode($code));
        }

        return $hand;
    }

    public function testCardParsesCodeAndValue(): void
    {
        $card = Card::fromCode('AS');

        self::assertSame(Suit::Spades, $card->suit);
        self::assertSame(Rank::Ace, $card->rank);
        self::assertSame(11, $card->value());
        self::assertTrue($card->isAce());
        self::assertFalse($card->isRed());
    }

    public function testFaceCardsAreRedOrBlack(): void
    {
        self::assertTrue(Card::fromCode('KH')->isRed());
        self::assertTrue(Card::fromCode('QD')->isRed());
        self::assertFalse(Card::fromCode('KC')->isRed());
    }

    /** @return iterable<string, array{list<string>, int, bool}> */
    public static function handTotals(): iterable
    {
        yield 'plain 20' => [['KH', 'QD'], 20, false];
        yield 'soft 17' => [['AS', '6H'], 17, true];
        yield 'hard 17 from two aces and a five' => [['AS', 'AH', '5H'], 17, true];
        yield 'two aces and a nine' => [['AS', 'AH', '9H'], 21, true];
        yield 'three aces is soft 13' => [['AS', 'AH', 'AC'], 13, true];
        yield 'blackjack' => [['AS', 'KH'], 21, true];
        yield 'bust' => [['KH', 'QH', '5D'], 25, false];
        yield 'ace demoted to avoid bust is hard 21' => [['AS', 'KH', 'QD'], 21, false];
    }

    #[DataProvider('handTotals')]
    public function testHandTotalAndSoftness(array $codes, int $total, bool $soft): void
    {
        $hand = $this->hand(...$codes);

        self::assertSame($total, BlackjackHandEvaluator::total($hand));
        self::assertSame($soft, BlackjackHandEvaluator::isSoft($hand));
    }

    public function testNaturalOnlyCountsOnTwoCards(): void
    {
        self::assertTrue(BlackjackHandEvaluator::isNatural($this->hand('AS', 'KH'), false));
        self::assertFalse(BlackjackHandEvaluator::isNatural($this->hand('AS', '5H', '5D'), false));
        // A 21 produced after a split is not a natural.
        self::assertFalse(BlackjackHandEvaluator::isNatural($this->hand('AS', 'KH'), true));
    }

    public function testBustDetection(): void
    {
        self::assertTrue(BlackjackHandEvaluator::isBust($this->hand('KH', 'QH', '5D')));
        self::assertFalse(BlackjackHandEvaluator::isBust($this->hand('AS', 'KH')));
    }

    public function testPairDetection(): void
    {
        self::assertTrue(BlackjackHandEvaluator::isPair($this->hand('8H', '8S')));
        self::assertFalse(BlackjackHandEvaluator::isPair($this->hand('8H', '9S')));
        self::assertFalse(BlackjackHandEvaluator::isPair($this->hand('8H', '8S', '8D')));
    }

    public function testSingleDeckHasFiftyTwoUniqueCards(): void
    {
        $deck = Deck::shuffled(RngFactory::seeded(1), 1);

        self::assertSame(52, $deck->initialSize());

        $codes = [];
        while (!$deck->isEmpty()) {
            $codes[] = $deck->draw()->code();
        }

        self::assertCount(52, array_unique($codes));
    }

    public function testSixDecksGiveThreeHundredAndTwelveCards(): void
    {
        $deck = Deck::shuffled(RngFactory::seeded(1), 6);

        self::assertSame(312, $deck->initialSize());
    }

    public function testShuffleIsReproducibleUnderASeed(): void
    {
        $a = Deck::shuffled(RngFactory::seeded(99), 1);
        $b = Deck::shuffled(RngFactory::seeded(99), 1);

        for ($i = 0; $i < 52; $i++) {
            self::assertSame($a->draw()->code(), $b->draw()->code());
        }
    }

    public function testDrawingFromAnEmptyDeckFails(): void
    {
        $deck = Deck::shuffled(RngFactory::seeded(1), 1);
        $deck->drawMany(52);

        $this->expectException(\CasinoEngine\Core\Exception\GameRuleException::class);

        $deck->draw();
    }

    public function testPenetrationTracksTheCutCard(): void
    {
        $deck = Deck::shuffled(RngFactory::seeded(1), 1);
        $deck->drawMany(40);

        self::assertGreaterThan(0.75, $deck->penetration());
        self::assertTrue($deck->needsReshuffle());
    }
}
