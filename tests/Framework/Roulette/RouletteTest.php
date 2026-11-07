<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Framework\Roulette;

use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Frameworks\Roulette\Bet\BetFactory;
use CasinoEngine\Frameworks\Roulette\Bet\BetType;
use CasinoEngine\Frameworks\Roulette\RouletteEngine;
use CasinoEngine\Frameworks\Roulette\Settlement\RouletteSettlement;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;
use CasinoEngine\Services\Rng\RngFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Roulette wheels, bets and settlement.
 *
 * Every payout here is a known constant of the game, so these assertions are
 * exact rather than approximate. The house edge is checked too, because a
 * settlement bug that quietly paid 37:1 would still "look right" per bet.
 */
final class RouletteTest extends TestCase
{
    public function testEuropeanWheelHasThirtySevenPockets(): void
    {
        $wheel = RouletteVariant::european();

        self::assertSame(37, $wheel->pocketCount());
        self::assertSame(1, $wheel->zeroCount());
        self::assertFalse($wheel->hasDoubleZero());
    }

    public function testAmericanWheelHasThirtyEightPockets(): void
    {
        $wheel = RouletteVariant::american();

        self::assertSame(38, $wheel->pocketCount());
        self::assertTrue($wheel->hasDoubleZero());
        self::assertSame(2, $wheel->zeroCount());
    }

    public function testZeroIsGreenAndRedIsRed(): void
    {
        $wheel = RouletteVariant::european();

        self::assertSame('green', $wheel->colourOf('0'));
        self::assertSame('red', $wheel->colourOf('1'));
        self::assertSame('black', $wheel->colourOf('2'));
    }

    /** @return iterable<string, array{BetType, list<string>, string, int}> */
    public static function payouts(): iterable
    {
        yield 'straight 35:1 returns 36x' => [BetType::Straight, ['17'], '17', 3600];
        yield 'straight loses' => [BetType::Straight, ['17'], '18', 0];
        yield 'split returns 18x' => [BetType::Split, ['16', '17'], '17', 1800];
        yield 'corner returns 9x' => [BetType::Corner, ['16', '17', '19', '20'], '17', 900];
        yield 'street returns 12x' => [BetType::Street, ['16', '17', '18'], '17', 1200];
        yield 'column returns 3x' => [BetType::Column, ['1', '4', '7', '10', '13', '16', '19', '22', '25', '28', '31', '34'], '1', 300];
    }

    #[DataProvider('payouts')]
    public function testSettlementPayout(BetType $type, array $numbers, string $pocket, int $expectedMinor): void
    {
        $variant = RouletteVariant::european();
        $bet = (new BetFactory($variant))->make($type, $numbers, Money::of(100));

        $result = (new RouletteSettlement())->settle([$bet], $pocket, $variant);

        self::assertSame($expectedMinor, $result['payout']->minorUnits());
    }

    public function testEvenMoneyBetsReturnDouble(): void
    {
        $variant = RouletteVariant::european();
        $factory = new BetFactory($variant);
        $outside = $factory->outsideBets();

        $cases = [
            'red'   => ['red', '1'],
            'black' => ['black', '2'],
            'odd'   => ['odd', '3'],
            'even'  => ['even', '4'],
            'low'   => ['low', '18'],
            'high'  => ['high', '19'],
        ];

        foreach ($cases as $name => [$key, $pocket]) {
            $bet = $factory->make(BetType::from($key), $outside[$key], Money::of(100));
            $result = (new RouletteSettlement())->settle([$bet], $pocket, $variant);

            self::assertSame(200, $result['payout']->minorUnits(), "The $name bet should return double.");
        }
    }

    public function testDozenReturnsTriple(): void
    {
        $variant = RouletteVariant::european();
        $bet = (new BetFactory($variant))->make(
            BetType::Dozen,
            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
            Money::of(100)
        );

        $result = (new RouletteSettlement())->settle([$bet], '7', $variant);

        self::assertSame(300, $result['payout']->minorUnits());
    }

    public function testZeroLosesAnEvenMoneyBet(): void
    {
        $variant = RouletteVariant::european();
        $bet = (new BetFactory($variant))->make(BetType::Red, (new BetFactory($variant))->outsideBets()['red'], Money::of(100));

        $result = (new RouletteSettlement())->settle([$bet], '0', $variant);

        self::assertSame(0, $result['payout']->minorUnits());
    }

    public function testLosingStraightLosesTheWholeStake(): void
    {
        $variant = RouletteVariant::european();
        $bet = (new BetFactory($variant))->make(BetType::Straight, ['17'], Money::of(100));

        $result = (new RouletteSettlement())->settle([$bet], '18', $variant);

        self::assertSame(0, $result['payout']->minorUnits());
        self::assertSame(100, $result['total_staked']->minorUnits());
        self::assertSame(-100, $result['net']->minorUnits());
    }

    public function testFrenchLaPartageReturnsHalfOnZero(): void
    {
        $variant = RouletteVariant::french();
        $factory = new BetFactory($variant);
        $bet = $factory->make(BetType::Red, $factory->outsideBets()['red'], Money::of(100));

        $result = (new RouletteSettlement())->settle([$bet], '0', $variant);

        self::assertSame(50, $result['payout']->minorUnits());
        self::assertTrue($result['la_partage']);
    }

    public function testSpinProducesAValidPocket(): void
    {
        $variant = RouletteVariant::european();
        $engine = new RouletteEngine($variant, new BetFactory($variant));
        $valid = $variant->pockets();

        for ($i = 0; $i < 500; $i++) {
            self::assertContains($engine->spin(RngFactory::seeded($i)), $valid);
        }
    }

    public function testSpinIsReproducibleUnderASeed(): void
    {
        $variant = RouletteVariant::european();
        $engine = new RouletteEngine($variant, new BetFactory($variant));

        for ($i = 0; $i < 20; $i++) {
            self::assertSame(
                $engine->spin(RngFactory::seeded(5)),
                $engine->spin(RngFactory::seeded(5))
            );
        }
    }

    public function testBetWithNoNumbersIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new BetFactory(RouletteVariant::european()))->make(BetType::Straight, [], Money::of(100));
    }

    public function testBetWithNumbersOutsideTheWheelIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new BetFactory(RouletteVariant::european()))->make(BetType::Straight, ['37'], Money::of(100));
    }

    public function testAmericanWheelHasTheWorseHouseEdge(): void
    {
        self::assertGreaterThan(
            RouletteVariant::european()->houseEdge(),
            RouletteVariant::american()->houseEdge()
        );
        self::assertEqualsWithDelta(1 / 37, RouletteVariant::european()->houseEdge(), 0.0001);
        self::assertEqualsWithDelta(2 / 38, RouletteVariant::american()->houseEdge(), 0.0001);
    }
}
