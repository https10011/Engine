<?php

declare(strict_types=1);

namespace CasinoEngine\Games\Synthetic;

use CasinoEngine\Core\Contract\GameStateInterface;
use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Frameworks\Card\Blackjack\BlackjackRules;
use CasinoEngine\Frameworks\Card\Card\Card;
use CasinoEngine\Frameworks\Card\Deck\Deck;
use CasinoEngine\Frameworks\Card\Hand\Hand;

/**
 * State for the synthetic blackjack fixture.
 *
 * The shoe is serialised as its remaining cards so a reloaded session resumes
 * with the same sequence. This is what makes the fixture a real test of session
 * persistence: a round that spans several requests must keep the shoe intact.
 */
final class SyntheticBlackjackState implements GameStateInterface
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param list<string> $playerCards
     * @param list<string> $dealerCards
     * @param list<string> $deckCards
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        private readonly array $playerCards = [],
        private readonly array $dealerCards = [],
        private readonly array $deckCards = [],
        private readonly bool $inProgress = false,
        private readonly ?array $result = null,
        private readonly int $handsPlayed = 0,
        private readonly int $stakeMinor = 0
    ) {
    }

    /**
     * The total stake committed to the hand in progress.
     *
     * A blackjack round spans several actions, so the completing action (a
     * stand) carries no bet of its own. The stake is recorded when the hand is
     * dealt and returned to the engine as the outcome's stake basis, which is
     * what lets the round settle correctly.
     */
    public function stakeMinor(): int
    {
        return $this->stakeMinor;
    }

    public static function initial(): self
    {
        return new self();
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function isInProgress(): bool
    {
        return $this->inProgress;
    }

    public function handsPlayed(): int
    {
        return $this->handsPlayed;
    }

    /** @return array<string, mixed>|null */
    public function result(): ?array
    {
        return $this->result;
    }

    public function playerHand(): Hand
    {
        return new Hand(array_map(
            static fn (string $code): Card => Card::fromCode($code),
            $this->playerCards
        ));
    }

    public function dealerHand(): Hand
    {
        return new Hand(array_map(
            static fn (string $code): Card => Card::fromCode($code),
            $this->dealerCards
        ));
    }

    /** Rebuilds the shoe from its remaining cards, preserving draw order. */
    public function deck(): Deck
    {
        $deck = new Deck(1);
        $deck->reset();

        // Empty the fresh deck and push the stored cards so the shoe is exactly
        // what it was when the hand started.
        while (!$deck->isEmpty()) {
            $deck->draw();
        }

        foreach ($this->deckCards as $code) {
            $deck->push(Card::fromCode($code));
        }

        return $deck;
    }

    /**
     * @param list<Card> $deckCards Remaining shoe in draw order
     */
    public function withHands(
        Hand $player,
        Hand $dealer,
        array $deckCards,
        BlackjackRules $rules,
        ?int $stakeMinor = null
    ): self {
        return new self(
            array_map(static fn (Card $c): string => $c->code(), $player->cards()),
            array_map(static fn (Card $c): string => $c->code(), $dealer->cards()),
            array_map(static fn (Card $c): string => $c->code(), $deckCards),
            true,
            null,
            $this->handsPlayed,
            $stakeMinor ?? $this->stakeMinor
        );
    }

    /** @param array<string, mixed> $result */
    public function completed(array $result): self
    {
        return new self([], [], [], false, $result, $this->handsPlayed + 1, 0);
    }

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'player_cards'   => $this->playerCards,
            'dealer_cards'   => $this->dealerCards,
            'deck_cards'     => $this->deckCards,
            'in_progress'    => $this->inProgress,
            'result'         => $this->result,
            'hands_played'   => $this->handsPlayed,
            'stake_minor'    => $this->stakeMinor,
            'player_total'   => $this->playerCards === [] ? 0 : $this->playerHand()->blackjackTotal(),
            'dealer_total'   => $this->dealerCards === [] ? 0 : $this->dealerHand()->blackjackTotal(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $version = (int) ($data['schema_version'] ?? 1);
        if ($version > self::SCHEMA_VERSION) {
            throw new ValidationException('This session was created by a newer engine version.');
        }

        /** @var list<string> $player */
        $player = is_array($data['player_cards'] ?? null) ? array_values(array_map('strval', $data['player_cards'])) : [];
        /** @var list<string> $dealer */
        $dealer = is_array($data['dealer_cards'] ?? null) ? array_values(array_map('strval', $data['dealer_cards'])) : [];
        /** @var list<string> $deck */
        $deck = is_array($data['deck_cards'] ?? null) ? array_values(array_map('strval', $data['deck_cards'])) : [];

        /** @var array<string, mixed>|null $result */
        $result = is_array($data['result'] ?? null) ? $data['result'] : null;

        return new self(
            $player,
            $dealer,
            $deck,
            (bool) ($data['in_progress'] ?? false),
            $result,
            (int) ($data['hands_played'] ?? 0),
            (int) ($data['stake_minor'] ?? 0)
        );
    }

    public function isComplete(): bool
    {
        return !$this->inProgress;
    }
}
