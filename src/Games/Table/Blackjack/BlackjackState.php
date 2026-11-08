<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Table\Blackjack;

use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Deck\Deck;
use CasinoEngine\Frameworks\Card\Hand\Hand;

/**
 * Blackjack round state.
 *
 * A round spans several actions — deal, then hit, stand, double or split on
 * each hand — so the state carries everything the next action needs: the shoe
 * as its remaining cards, the dealer's hand, one or more player hands, and the
 * stake committed to each. Serialising the shoe as cards rather than as a seed
 * is what lets a round resume correctly after a reload: the sequence that
 * follows is the one the shoe actually holds, not a re-derivation of it.
 *
 * A split turns one hand into two and can be repeated, so `hands` is a list,
 * `finished` records which hands are done and `activeHand` says which one is
 * being played. All three are persisted, which is why a split round survives a
 * reload in the middle of the second hand.
 */
final class BlackjackState implements GameStateInterface
{
    public const SCHEMA_VERSION = 1;

    /** Waiting for the player to take or decline insurance. */
    public const PHASE_INSURANCE = 'insurance';

    /** Waiting for the player to act on a hand. */
    public const PHASE_PLAYER = 'player';

    /** The round has settled. */
    public const PHASE_COMPLETE = 'complete';

    /**
     * @param list<list<string>> $hands Player hands as card codes
     * @param list<string> $dealerCards
     * @param list<string> $shoe Remaining shoe cards, in draw order
     * @param list<int> $stakes Minor units committed to each player hand
     * @param list<bool> $doubled Whether each hand has doubled
     * @param list<bool> $fromSplit Whether each hand came from a split
     * @param list<bool> $finished Whether each hand has stopped drawing
     * @param list<string> $results Settled results, one per hand
     */
    public function __construct(
        private readonly array $hands = [],
        private readonly array $dealerCards = [],
        private readonly array $shoe = [],
        private readonly array $stakes = [],
        private readonly array $doubled = [],
        private readonly array $fromSplit = [],
        private readonly array $finished = [],
        private readonly array $results = [],
        private readonly int $activeHand = 0,
        private readonly string $phase = self::PHASE_COMPLETE,
        private readonly bool $insuranceTaken = false,
        private readonly int $insuranceStakeMinor = 0,
        private readonly int $handsPlayed = 0
    ) {
    }

    public static function initial(): self
    {
        return new self();
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function isComplete(): bool
    {
        return $this->phase === self::PHASE_COMPLETE;
    }

    public function isInProgress(): bool
    {
        return !$this->isComplete();
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function isAwaitingInsurance(): bool
    {
        return $this->phase === self::PHASE_INSURANCE;
    }

    public function handAt(int $index): Hand
    {
        if (!isset($this->hands[$index])) {
            throw new ValidationException('No such player hand.');
        }

        return new Hand(array_map(Card::fromCode(...), $this->hands[$index]));
    }

    public function dealerHand(): Hand
    {
        return new Hand(array_map(Card::fromCode(...), $this->dealerCards));
    }

    /**
     * Rebuilds the shoe from its remaining cards.
     *
     * The deck is drained and refilled rather than constructed from the list,
     * because the framework's deck builds its own 52 cards and exposes no
     * constructor that takes an arbitrary sequence. Drawing from the top of the
     * refilled shoe then continues exactly where the stored one left off.
     */
    public function deck(): Deck
    {
        $deck = new Deck(1);
        $deck->reset();

        while (!$deck->isEmpty()) {
            $deck->draw();
        }

        foreach ($this->shoe as $code) {
            $deck->push(Card::fromCode($code));
        }

        return $deck;
    }

    public function activeHandIndex(): int
    {
        return $this->activeHand;
    }

    public function handCount(): int
    {
        return count($this->hands);
    }

    public function stakeOf(int $index): int
    {
        return $this->stakes[$index] ?? 0;
    }

    /** @return list<int> */
    public function stakes(): array
    {
        return $this->stakes;
    }

    /** The total committed to the round: every hand's stake, plus insurance. */
    public function totalStakeMinor(): int
    {
        return array_sum($this->stakes) + $this->insuranceStakeMinor;
    }

    public function hasDoubled(int $index): bool
    {
        return $this->doubled[$index] ?? false;
    }

    public function cameFromSplit(int $index): bool
    {
        return $this->fromSplit[$index] ?? false;
    }

    public function isFinished(int $index): bool
    {
        return $this->finished[$index] ?? true;
    }

    public function insuranceTaken(): bool
    {
        return $this->insuranceTaken;
    }

    public function insuranceStakeMinor(): int
    {
        return $this->insuranceStakeMinor;
    }

    public function handsPlayed(): int
    {
        return $this->handsPlayed;
    }

    /** @return list<string> */
    public function results(): array
    {
        return $this->results;
    }

    /** @return list<list<string>> */
    public function hands(): array
    {
        return $this->hands;
    }

    /** @return list<string> */
    public function dealerCards(): array
    {
        return $this->dealerCards;
    }

    /** @return list<string> */
    public function shoe(): array
    {
        return $this->shoe;
    }

    /** @return list<bool> */
    public function doubledFlags(): array
    {
        return $this->doubled;
    }

    /** @return list<bool> */
    public function fromSplitFlags(): array
    {
        return $this->fromSplit;
    }

    /** @return list<bool> */
    public function finishedFlags(): array
    {
        return $this->finished;
    }

    /**
     * @param list<list<string>> $hands
     * @param list<string> $dealerCards
     * @param list<string> $shoe
     * @param list<int> $stakes
     * @param list<bool> $doubled
     * @param list<bool> $fromSplit
     * @param list<bool> $finished
     * @param list<string> $results
     */
    public function withRound(
        array $hands,
        array $dealerCards,
        array $shoe,
        array $stakes,
        array $doubled,
        array $fromSplit,
        array $finished,
        array $results,
        int $activeHand,
        string $phase,
        bool $insuranceTaken,
        int $insuranceStakeMinor,
        int $handsPlayed
    ): self {
        return new self(
            $hands,
            $dealerCards,
            $shoe,
            $stakes,
            $doubled,
            $fromSplit,
            $finished,
            $results,
            $activeHand,
            $phase,
            $insuranceTaken,
            $insuranceStakeMinor,
            $handsPlayed
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema'                => self::SCHEMA_VERSION,
            'hands'                 => $this->hands,
            'dealer'                => $this->dealerCards,
            'shoe'                  => $this->shoe,
            'stakes'                => $this->stakes,
            'doubled'               => $this->doubled,
            'from_split'            => $this->fromSplit,
            'finished'              => $this->finished,
            'results'               => $this->results,
            'active_hand'           => $this->activeHand,
            'phase'                 => $this->phase,
            'insurance_taken'       => $this->insuranceTaken,
            'insurance_stake_minor' => $this->insuranceStakeMinor,
            'hands_played'          => $this->handsPlayed,
            'dealer_total'          => $this->dealerCards === [] ? 0 : $this->dealerHand()->blackjackTotal(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        $version = (int) ($data['schema'] ?? self::SCHEMA_VERSION);
        if ($version > self::SCHEMA_VERSION) {
            throw new ValidationException('This session was created by a newer engine version.');
        }

        return new self(
            self::strings2($data['hands'] ?? []),
            self::strings($data['dealer'] ?? []),
            self::strings($data['shoe'] ?? []),
            self::ints($data['stakes'] ?? []),
            self::bools($data['doubled'] ?? []),
            self::bools($data['from_split'] ?? []),
            self::bools($data['finished'] ?? []),
            self::strings($data['results'] ?? []),
            (int) ($data['active_hand'] ?? 0),
            (string) ($data['phase'] ?? self::PHASE_COMPLETE),
            (bool) ($data['insurance_taken'] ?? false),
            (int) ($data['insurance_stake_minor'] ?? 0),
            (int) ($data['hands_played'] ?? 0)
        );
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $v): string => (string) $v, $value));
    }

    /** @return list<list<string>> */
    private static function strings2(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $inner) {
            $out[] = self::strings($inner);
        }

        return $out;
    }

    /** @return list<int> */
    private static function ints(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $v): int => (int) $v, $value));
    }

    /** @return list<bool> */
    private static function bools(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $v): bool => (bool) $v, $value));
    }
}
