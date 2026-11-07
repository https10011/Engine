<?php

declare(strict_types=1);

namespace CasinoEngine\Frameworks\Roulette\Wheel;

/**
 * A roulette wheel layout.
 *
 * The wheel order is data, not a constant, because the variants genuinely
 * differ: European wheels carry a single zero, American wheels add a double
 * zero, and French wheels share the European order but add the la partage and
 * en prison rules. Encoding the order as an array means a new variant is a new
 * definition, not a new branch in the engine.
 */
final class RouletteVariant
{
    public const EUROPEAN = 'european';
    public const AMERICAN = 'american';
    public const FRENCH   = 'french';

    /**
     * @param list<string> $pocketOrder Clockwise pocket order, e.g. `['0','32','15',...]`
     * @param array<int|string, string> $colours pocket => 'red'|'black'|'green'
     *        (PHP stores the pocket '0' under the integer key 0)
     */
    public function __construct(
        private readonly string $name,
        private readonly array $pocketOrder,
        private readonly array $colours,
        private readonly bool $laPartage = false,
        private readonly bool $enPrison = false
    ) {
        if ($pocketOrder === []) {
            throw new \CasinoEngine\Core\Exception\ConfigurationException('A wheel needs at least one pocket.');
        }
    }

    public static function european(): self
    {
        return new self(self::EUROPEAN, self::EUROPEAN_ORDER, self::europeanColours());
    }

    public static function american(): self
    {
        return new self(self::AMERICAN, self::AMERICAN_ORDER, self::americanColours());
    }

    public static function french(): self
    {
        return new self(self::FRENCH, self::EUROPEAN_ORDER, self::europeanColours(), true, true);
    }

    /**
     * @param list<string> $order
     * @param array<int|string, string> $colours
     */
    public static function custom(string $name, array $order, array $colours): self
    {
        return new self($name, $order, $colours);
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function pockets(): array
    {
        return $this->pocketOrder;
    }

    public function pocketCount(): int
    {
        return count($this->pocketOrder);
    }

    public function colourOf(string $pocket): string
    {
        return $this->colours[$pocket] ?? 'green';
    }

    public function hasDoubleZero(): bool
    {
        return in_array('00', $this->pocketOrder, true);
    }

    public function zeroCount(): int
    {
        $count = 0;
        foreach ($this->pocketOrder as $pocket) {
            if ($pocket === '0' || $pocket === '00') {
                $count++;
            }
        }

        return $count;
    }

    public function laPartage(): bool
    {
        return $this->laPartage;
    }

    public function enPrison(): bool
    {
        return $this->enPrison;
    }

    /** House edge implied by the wheel geometry alone, before any rule. */
    public function houseEdge(): float
    {
        return $this->zeroCount() / $this->pocketCount();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name'         => $this->name,
            'pocket_count' => $this->pocketCount(),
            'pockets'      => $this->pocketOrder,
            'colours'      => $this->colours,
            'la_partage'   => $this->laPartage,
            'en_prison'    => $this->enPrison,
        ];
    }

    /** Standard European clockwise order, starting at 0. */
    private const EUROPEAN_ORDER = [
        '0', '32', '15', '19', '4', '21', '2', '25', '17', '34', '6', '27', '13',
        '36', '11', '30', '8', '23', '10', '5', '24', '16', '33', '1', '20', '14',
        '31', '9', '22', '18', '29', '7', '28', '12', '35', '3', '26',
    ];

    /** Standard American clockwise order, with 00 opposite 0. */
    private const AMERICAN_ORDER = [
        '0', '28', '9', '26', '30', '11', '7', '20', '32', '17', '5', '22', '34',
        '15', '3', '24', '36', '13', '1', '00', '27', '10', '25', '29', '12', '8',
        '19', '31', '18', '6', '21', '33', '16', '4', '23', '35', '14', '2',
    ];

    /** @return array<int|string, string> */
    private static function europeanColours(): array
    {
        return self::coloursFor(false);
    }

    /** @return array<int|string, string> */
    private static function americanColours(): array
    {
        return self::coloursFor(true);
    }

    /**
     * The red/black assignment is fixed by the standard layout. Deriving it
     * from a table rather than hardcoding 37 entries makes it verifiable
     * against the published layout.
     *
     * @return array<int|string, string> pocket label => colour name. PHP
     *         stores the pocket written '0' under the integer key 0.
     */
    private static function coloursFor(bool $doubleZero): array
    {
        $reds = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

        /** @var array<int|string, string> $colours */
        $colours = [];

        foreach ($reds as $number) {
            $colours[(string) $number] = 'red';
        }

        for ($number = 1; $number <= 36; $number++) {
            if (!isset($colours[(string) $number])) {
                $colours[(string) $number] = 'black';
            }
        }

        $colours['0'] = 'green';
        if ($doubleZero) {
            $colours['00'] = 'green';
        }

        return $colours;
    }
}
