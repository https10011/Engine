<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Roulette\Bet;

use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Core\Value\Money;

/**
 * One placed roulette bet.
 *
 * A bet is its type plus the numbers it covers. Validation happens at
 * construction so an impossible bet (a street that is not three consecutive
 * numbers, a corner that is not a 2x2 block) cannot exist as an object and
 * therefore cannot reach settlement.
 */
final class Bet
{
    /**
     * @param list<string> $numbers The pockets this bet covers
     */
    public function __construct(
        public readonly BetType $type,
        public readonly array $numbers,
        public readonly Money $stake,
        public readonly string $label = ''
    ) {
        if ($numbers === []) {
            throw new ValidationException('A roulette bet must cover at least one number.');
        }

        if ($stake->isNegative() || $stake->isZero()) {
            throw new ValidationException('A roulette bet must have a positive stake.');
        }

        $expected = match ($type) {
            BetType::Straight => 1,
            BetType::Split    => 2,
            BetType::Street   => 3,
            BetType::Corner   => 4,
            BetType::SixLine  => 6,
            BetType::Trio     => 3,
            BetType::Basket   => 5,
            default           => count($numbers),
        };

        if (count($numbers) !== $expected) {
            throw new ValidationException(sprintf(
                'A %s bet must cover exactly %d number(s); %d given.',
                $type->value,
                $expected,
                count($numbers)
            ));
        }
    }

    /**
     * Builds a straight-up bet on one number.
     */
    public static function straight(string $number, Money $stake): self
    {
        return new self(BetType::Straight, [$number], $stake, $number);
    }

    public function covers(string $pocket): bool
    {
        return in_array($pocket, $this->numbers, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type'   => $this->type->value,
            'numbers' => $this->numbers,
            'stake'  => $this->stake->jsonSerialize(),
            'label'  => $this->label,
            'inside' => $this->type->isInside(),
        ];
    }
}
