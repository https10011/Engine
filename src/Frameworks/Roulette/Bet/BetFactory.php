<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Roulette\Bet;

use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\Money;
use CasinoEngine\Frameworks\Roulette\Wheel\RouletteVariant;

/**
 * Builds the standard roulette bets from a wheel definition.
 *
 * Every bet is derived from the wheel rather than hardcoded, so a wheel with a
 * different pocket set produces a different, correct bet menu. Validation is
 * structural: a street must be three consecutive numbers in the same column,
 * a corner a 2x2 block, and so on — which is what stops a client from
 * submitting a nonsense bet that happens to have the right number of numbers.
 */
final class BetFactory
{
    public function __construct(private readonly RouletteVariant $variant)
    {
    }

    public function variant(): RouletteVariant
    {
        return $this->variant;
    }

    /** @return list<string> Every non-zero pocket as a string, ascending. */
    public function numbers(): array
    {
        $numbers = [];
        foreach ($this->variant->pockets() as $pocket) {
            if ($pocket !== '0' && $pocket !== '00') {
                $numbers[] = $pocket;
            }
        }

        usort($numbers, static fn (string $a, string $b): int => (int) $a <=> (int) $b);

        return $numbers;
    }

    /**
     * Validates a bet against the wheel and returns a typed Bet.
     *
     * @param list<string> $numbers
     */
    public function make(BetType $type, array $numbers, Money $stake): Bet
    {
        $this->assertNumbersExist($numbers);
        $this->assertStructure($type, $numbers);

        return new Bet($type, array_values($numbers), $stake, $type->value);
    }

    /** @param list<string> $numbers */
    private function assertNumbersExist(array $numbers): void
    {
        $pockets = $this->variant->pockets();

        foreach ($numbers as $number) {
            if (!in_array((string) $number, $pockets, true)) {
                throw new ValidationException("'$number' is not a pocket on this wheel.");
            }
        }
    }

    /** @param list<string> $numbers */
    private function assertStructure(BetType $type, array $numbers): void
    {
        $values = array_map('intval', array_filter($numbers, static fn (string $n): bool => $n !== '0' && $n !== '00'));

        switch ($type) {
            case BetType::Street:
                $sorted = $values;
                sort($sorted);
                if (count($sorted) !== 3 || $sorted[0] % 3 !== 1 || $sorted[1] !== $sorted[0] + 1 || $sorted[2] !== $sorted[0] + 2) {
                    throw new ValidationException('A street must be three consecutive numbers starting a column.');
                }
                break;

            case BetType::SixLine:
                $sorted = $values;
                sort($sorted);
                if (count($sorted) !== 6) {
                    throw new ValidationException('A six line covers six numbers.');
                }
                for ($i = 1; $i < 6; $i++) {
                    if ($sorted[$i] !== $sorted[$i - 1] + 1) {
                        throw new ValidationException('A six line must be two adjacent streets.');
                    }
                }
                break;

            case BetType::Corner:
                $sorted = $values;
                sort($sorted);
                if (count($sorted) !== 4) {
                    throw new ValidationException('A corner covers four numbers.');
                }
                // A 2x2 block: the last two are exactly 3 greater than the first two.
                if ($sorted[2] !== $sorted[0] + 3 || $sorted[3] !== $sorted[1] + 3 || $sorted[1] !== $sorted[0] + 1) {
                    throw new ValidationException('A corner must be a 2x2 block of numbers.');
                }
                break;

            case BetType::Split:
                $sorted = $values;
                sort($sorted);
                if (count($sorted) !== 2) {
                    throw new ValidationException('A split covers two numbers.');
                }
                $difference = $sorted[1] - $sorted[0];
                if ($difference !== 1 && $difference !== 3) {
                    throw new ValidationException('A split must join adjacent numbers horizontally or vertically.');
                }
                break;

            case BetType::Column:
                if (count($values) !== 12) {
                    throw new ValidationException('A column covers twelve numbers.');
                }
                $remainders = array_unique(array_map(static fn (int $n): int => $n % 3, $values));
                if (count($remainders) !== 1) {
                    throw new ValidationException('A column must cover numbers from one column only.');
                }
                break;

            case BetType::Dozen:
                sort($values);
                if (count($values) !== 12) {
                    throw new ValidationException('A dozen covers twelve numbers.');
                }
                $start = $values[0];
                if (!in_array($start, [1, 13, 25], true)) {
                    throw new ValidationException('A dozen must start at 1, 13 or 25.');
                }
                for ($i = 1; $i < 12; $i++) {
                    if ($values[$i] !== $start + $i) {
                        throw new ValidationException('A dozen must be twelve consecutive numbers.');
                    }
                }
                break;

            case BetType::Red:
            case BetType::Black:
            case BetType::Odd:
            case BetType::Even:
            case BetType::Low:
            case BetType::High:
                foreach ($numbers as $number) {
                    if (!$this->evenMoneyCovers($type, (string) $number)) {
                        throw new ValidationException("A {$type->value} bet cannot include $number.");
                    }
                }
                break;

            case BetType::Straight:
            case BetType::Trio:
            case BetType::Basket:
                // Straight is one number; trio and basket are wheel-neighbour
                // bets whose exact geometry depends on the variant, so their
                // coverage is validated only for existence above.
                break;
        }
    }

    private function evenMoneyCovers(BetType $type, string $number): bool
    {
        if ($number === '0' || $number === '00') {
            return false;
        }

        $value = (int) $number;

        return match ($type) {
            BetType::Red    => $this->variant->colourOf($number) === 'red',
            BetType::Black  => $this->variant->colourOf($number) === 'black',
            BetType::Odd    => $value % 2 === 1,
            BetType::Even   => $value % 2 === 0,
            BetType::Low    => $value >= 1 && $value <= 18,
            BetType::High   => $value >= 19 && $value <= 36,
            default         => false,
        };
    }

    /**
     * The complete menu of standard outside bets for this wheel.
     *
     * @return array<string, list<string>>
     */
    public function outsideBets(): array
    {
        $numbers = $this->numbers();
        $reds = [];
        $blacks = [];
        $odds = [];
        $evens = [];
        $low = [];
        $high = [];

        foreach ($numbers as $number) {
            $value = (int) $number;
            if ($this->variant->colourOf($number) === 'red') {
                $reds[] = $number;
            } else {
                $blacks[] = $number;
            }
            if ($value % 2 === 1) {
                $odds[] = $number;
            } else {
                $evens[] = $number;
            }
            if ($value <= 18) {
                $low[] = $number;
            } else {
                $high[] = $number;
            }
        }

        return [
            'red'   => $reds,
            'black' => $blacks,
            'odd'   => $odds,
            'even'  => $evens,
            'low'   => $low,
            'high'  => $high,
        ];
    }
}
